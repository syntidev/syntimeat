<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\InventoryEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\DollarRateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContingencyController extends Controller
{
    public function __construct(
        private readonly DollarRateService $rateService,
    ) {}

    // ─── Página ────────────────────────────────────────────────────────────────

    public function index(): InertiaResponse
    {
        return Inertia::render('Contingency/Index');
    }

    // ─── Descarga hoja de papel (PDF) ─────────────────────────────────────────

    public function downloadForm(): Response
    {
        /** @var \App\Models\User $user */
        $user       = Auth::user();
        $business   = Business::findOrFail($user->business_id);
        $todayRate  = $this->rateService->getTodayRate();
        $today      = now()->format('d/m/Y');
        $cashierName = $user->name;

        $html = $this->buildPdfHtml($business, $today, $cashierName, $todayRate);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait')
            ->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => false]);

        return $pdf->download('contingencia-' . now()->format('Y-m-d') . '.pdf');
    }

    // ─── Plantilla Excel: ventas ───────────────────────────────────────────────

    public function downloadTemplate(): BinaryFileResponse
    {
        $headings = ['hora', 'product_id', 'product_name', 'quantity_value',
                     'input_type', 'price_bs', 'total_bs', 'payment_method'];

        $example = collect([
            ['08:30', 1, 'Carne Molida', 1.500, 'weight', 150.00, 225.00, 'Efectivo Bs.'],
        ]);

        $export = new ContingencyExport($example, $headings, 'Ventas');

        return Excel::download($export, 'plantilla-ventas-contingencia.xlsx');
    }

    // ─── Plantilla Excel: inventario ──────────────────────────────────────────

    public function downloadInventoryTemplate(): BinaryFileResponse
    {
        $headings = ['hora', 'product_id', 'product_name', 'quantity_kg',
                     'waste_kg', 'cost_per_kg_usd', 'supplier'];

        $example = collect([
            ['07:00', 1, 'Carne Molida', 50.000, 2.500, 3.50, 'Proveedor SA'],
        ]);

        $export = new ContingencyExport($example, $headings, 'Inventario');

        return Excel::download($export, 'plantilla-inventario-contingencia.xlsx');
    }

    // ─── Importar ventas ──────────────────────────────────────────────────────

    public function importSales(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        /** @var \App\Models\User $user */
        $user        = Auth::user();
        $businessId  = $user->business_id;
        $business    = Business::findOrFail($businessId);
        $todayRate   = $this->rateService->getTodayRate();
        $prefix      = $business->ticket_prefix ?? 'VEN';

        // Leer Excel
        $rows = Excel::toCollection(new EmptyImport(), $request->file('file'))
            ->first();

        if ($rows === null || $rows->isEmpty()) {
            return response()->json(['error' => 'Archivo vacío o sin datos.'], 422);
        }

        // Verificar columnas
        $requiredCols = ['hora', 'product_id', 'quantity_value', 'input_type',
                         'price_bs', 'total_bs', 'payment_method'];
        $headers = array_map('strtolower', $rows->first()->keys()->toArray());
        foreach ($requiredCols as $col) {
            if (!in_array($col, $headers, true)) {
                return response()->json(['error' => "Columna requerida faltante: {$col}"], 422);
            }
        }

        // Omitir fila de encabezado si primera celda = 'hora'
        $data = strtolower($rows->first()['hora'] ?? '') === 'hora'
            ? $rows->skip(1)
            : $rows;

        $imported    = [];
        $warnings    = [];
        $importedAt  = now()->toDateString();

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $row       = array_change_key_case($row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row, CASE_LOWER);
                $hora      = trim((string) ($row['hora'] ?? ''));
                $productId = (int) ($row['product_id'] ?? 0);
                $qty       = (float) ($row['quantity_value'] ?? 0);
                $inputType = trim(strtolower((string) ($row['input_type'] ?? 'weight')));
                $priceBs   = (float) ($row['price_bs'] ?? 0);
                $totalBs   = (float) ($row['total_bs'] ?? 0);
                $pmName    = trim((string) ($row['payment_method'] ?? ''));
                $productName = trim((string) ($row['product_name'] ?? ''));

                if ($productId === 0 || $qty <= 0) {
                    $warnings[] = "Fila " . ($index + 2) . ": product_id o cantidad inválidos — omitida.";
                    continue;
                }

                // Cargar producto
                $product = Product::where('id', $productId)
                    ->where('business_id', $businessId)
                    ->first();

                if ($product === null) {
                    $warnings[] = "Fila " . ($index + 2) . ": producto ID {$productId} no encontrado — omitida.";
                    continue;
                }

                // Detectar duplicado: mismo día + producto + cantidad
                $duplicate = Sale::where('business_id', $businessId)
                    ->where('status', 'paid')
                    ->whereDate('sold_at', $importedAt)
                    ->whereHas('items', function ($q) use ($productId, $qty) {
                        $q->where('product_id', $productId)
                          ->where('quantity_value', $qty);
                    })
                    ->exists();

                if ($duplicate) {
                    $warnings[] = "Fila " . ($index + 2) . ": posible duplicado (producto ID {$productId}, cantidad {$qty}) — importada de todas formas.";
                }

                // Calcular precio en USD
                $priceUsd = $todayRate > 0 ? round($priceBs / $todayRate, 2) : 0;
                $totalUsd = $todayRate > 0 ? round($totalBs / $todayRate, 2) : 0;

                // Número de ticket
                $count  = Sale::where('business_id', $businessId)->count() + 1;
                $ticket = 'C-' . $prefix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

                // Construir sold_at
                $soldAt = now()->setDateFrom(now())->setTimeFromTimeString(
                    preg_match('/^\d{1,2}:\d{2}/', $hora) ? $hora : '00:00'
                );

                // Crear Sale
                $sale = Sale::create([
                    'business_id'  => $businessId,
                    'branch_id'    => Auth::user()->branch_id ?? (session('current_branch_id') ?? null),
                    'ticket_number' => $ticket,
                    'status'       => 'paid',
                    'total_usd'    => $totalUsd,
                    'rate_used'    => $todayRate,
                    'total_bs'     => $totalBs,
                    'payment_method' => $pmName,
                    'cashier_id'   => $user->id,
                    'sold_at'      => $soldAt,
                    'notes'        => 'Importado contingencia offline',
                ]);

                // Crear SaleItem
                $itemData = [
                    'sale_id'      => $sale->id,
                    'product_id'   => $productId,
                    'product_name' => $productName ?: $product->name,
                    'input_type'   => $inputType,
                    'quantity_value' => $qty,
                    'unit_label'   => $inputType === 'weight' ? 'kg' : 'und',
                    'subtotal_usd' => $totalUsd,
                    'discount_usd' => 0,
                ];

                if ($inputType === 'weight') {
                    $itemData['price_per_kg_usd']   = $priceUsd;
                    $itemData['price_per_unit_usd']  = 0;
                } else {
                    $itemData['price_per_unit_usd']  = $priceUsd;
                    $itemData['price_per_kg_usd']    = 0;
                }

                SaleItem::create($itemData);

                // Descontar inventario si es peso
                if ($inputType === 'weight') {
                    InventoryEntry::create([
                        'business_id'   => $businessId,
                        'product_id'    => $productId,
                        'quantity_kg'   => -abs($qty),
                        'waste_kg'      => 0,
                        'cost_per_kg_usd' => 0,
                        'notes'         => 'Contingencia offline',
                        'entered_at'    => $soldAt,
                        'created_by'    => $user->id,
                    ]);
                }

                $imported[] = [
                    'ticket'       => $ticket,
                    'producto'     => $productName ?: $product->name,
                    'cantidad'     => $qty,
                    'input_type'   => $inputType,
                    'total_bs'     => $totalBs,
                    'metodo_pago'  => $pmName,
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ContingencyController::importSales', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al importar: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'imported' => $imported,
            'warnings' => $warnings,
            'total'    => count($imported),
        ]);
    }

    // ─── Importar inventario ───────────────────────────────────────────────────

    public function importInventory(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        /** @var \App\Models\User $user */
        $user       = Auth::user();
        $businessId = $user->business_id;

        $rows = Excel::toCollection(new EmptyImport(), $request->file('file'))
            ->first();

        if ($rows === null || $rows->isEmpty()) {
            return response()->json(['error' => 'Archivo vacío o sin datos.'], 422);
        }

        $requiredCols = ['product_id', 'quantity_kg'];
        $headers      = array_map('strtolower', $rows->first()->keys()->toArray());
        foreach ($requiredCols as $col) {
            if (!in_array($col, $headers, true)) {
                return response()->json(['error' => "Columna requerida faltante: {$col}"], 422);
            }
        }

        $data = strtolower($rows->first()['product_id'] ?? '') === 'product_id'
            ? $rows->skip(1)
            : $rows;

        $imported = [];
        $warnings = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $row        = array_change_key_case((array) $row, CASE_LOWER);
                $hora       = trim((string) ($row['hora'] ?? ''));
                $productId  = (int) ($row['product_id'] ?? 0);
                $qtyKg      = (float) ($row['quantity_kg'] ?? 0);
                $wasteKg    = (float) ($row['waste_kg'] ?? 0);
                $costUsd    = (float) ($row['cost_per_kg_usd'] ?? 0);
                $supplier   = trim((string) ($row['supplier'] ?? ''));
                $productName = trim((string) ($row['product_name'] ?? ''));

                if ($productId === 0 || $qtyKg <= 0) {
                    $warnings[] = "Fila " . ($index + 2) . ": product_id o quantity_kg inválidos — omitida.";
                    continue;
                }

                $product = Product::where('id', $productId)
                    ->where('business_id', $businessId)
                    ->first();

                if ($product === null) {
                    $warnings[] = "Fila " . ($index + 2) . ": producto ID {$productId} no encontrado — omitida.";
                    continue;
                }

                $enteredAt = now()->setDateFrom(now())->setTimeFromTimeString(
                    preg_match('/^\d{1,2}:\d{2}/', $hora) ? $hora : '00:00'
                );

                InventoryEntry::create([
                    'business_id'    => $businessId,
                    'product_id'     => $productId,
                    'quantity_kg'    => $qtyKg,
                    'waste_kg'       => $wasteKg,
                    'cost_per_kg_usd' => $costUsd,
                    'supplier'       => $supplier ?: null,
                    'notes'          => 'Importado contingencia offline',
                    'entered_at'     => $enteredAt,
                    'created_by'     => $user->id,
                ]);

                $imported[] = [
                    'producto'  => $productName ?: $product->name,
                    'cantidad'  => $qtyKg,
                    'merma'     => $wasteKg,
                    'neto'      => round($qtyKg - $wasteKg, 3),
                    'proveedor' => $supplier,
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ContingencyController::importInventory', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al importar: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'imported' => $imported,
            'warnings' => $warnings,
            'total'    => count($imported),
        ]);
    }

    // ─── HTML del PDF ─────────────────────────────────────────────────────────

    private function buildPdfHtml(Business $business, string $today, string $cashier, float $rate): string
    {
        $logo = '';
        if ($business->logo_path && file_exists(storage_path('app/public/' . $business->logo_path))) {
            $imgData = base64_encode(file_get_contents(storage_path('app/public/' . $business->logo_path)));
            $mime    = mime_content_type(storage_path('app/public/' . $business->logo_path)) ?: 'image/png';
            $logo    = "<img src=\"data:{$mime};base64,{$imgData}\" style=\"height:60px;\" alt=\"logo\">";
        }

        $rows = '';
        for ($i = 0; $i < 20; $i++) {
            $rows .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
        }

        $name = htmlspecialchars($business->name ?? 'Mi Negocio', ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 16px; }
  h1 { font-size: 16px; margin: 4px 0; }
  .header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; border-bottom: 2px solid #111; padding-bottom: 8px; }
  .meta { font-size: 10px; color: #444; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th { background: #111; color: #fff; padding: 5px 6px; text-align: left; font-size: 10px; }
  td { border-bottom: 1px solid #ccc; padding: 8px 6px; height: 22px; font-size: 10px; }
  .footer { margin-top: 20px; border-top: 1px solid #555; padding-top: 8px; font-size: 10px; display: flex; justify-content: space-between; }
  .footer-field { border-bottom: 1px solid #333; min-width: 120px; display: inline-block; padding-bottom: 2px; }
</style>
</head>
<body>
<div class="header">
  {$logo}
  <div>
    <h1>{$name}</h1>
    <div>PLAN DE CONTINGENCIA — HOJA DE VENTAS MANUAL</div>
  </div>
</div>
<div class="meta">
  Fecha: <strong>{$today}</strong> &nbsp;|&nbsp;
  Cajero: <strong>{$cashier}</strong> &nbsp;|&nbsp;
  Tasa USD: <strong>Bs. {$rate}</strong>
</div>
<table>
  <thead>
    <tr>
      <th>Hora</th>
      <th>Producto</th>
      <th>Cantidad (kg/und)</th>
      <th>Precio Bs.</th>
      <th>Total Bs.</th>
      <th>Pago</th>
    </tr>
  </thead>
  <tbody>
    {$rows}
  </tbody>
</table>
<div class="footer">
  <div>TOTAL GENERAL: Bs. <span class="footer-field">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></div>
  <div>FIRMA CAJERO: <span class="footer-field">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></div>
</div>
</body>
</html>
HTML;
    }
}

// ─── Export helper ─────────────────────────────────────────────────────────────

class ContingencyExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly \Illuminate\Support\Collection $data,
        private readonly array $headings,
        private readonly string $title,
    ) {}

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }
}

// ─── Empty import helper ───────────────────────────────────────────────────────

class EmptyImport implements \Maatwebsite\Excel\Concerns\ToCollection, \Maatwebsite\Excel\Concerns\WithHeadingRow
{
    public function collection(\Illuminate\Support\Collection $rows): void {}
}
