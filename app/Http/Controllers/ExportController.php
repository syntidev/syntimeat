<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Models\InventoryEntry;
use App\Models\Product;
use App\Services\DollarRateService;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly DollarRateService $rates) {}

    public function catalogo(): StreamedResponse
    {
        $user       = Auth::user();
        $businessId = $user->business_id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $branch     = $branchId
            ? \App\Models\Branch::find($branchId)
            : \App\Models\Branch::where('business_id', $businessId)->first();
        $branchName = $branch?->name ?? 'Sucursal';
        $business   = $user->business;

        $rate = $this->rates->getTodayRate();

        $products = Product::with(['category'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('location', '!=', 'boveda')
            ->where('active', true)
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $stockIn = InventoryEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('product_id, SUM(net_kg) as total_net')
            ->groupBy('product_id')
            ->pluck('total_net', 'product_id');

        $stockMap = [];
        foreach ($products as $p) {
            $poolId = $p->stock_product_id ?? $p->id;
            $stockMap[$p->id] = round((float) ($stockIn[$poolId] ?? 0), 3);
        }

        $lastEntryMap = InventoryEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('quantity_kg', '>', 0)
            ->selectRaw('product_id, MAX(entered_at) as last_at')
            ->groupBy('product_id')
            ->pluck('last_at', 'product_id');

        $sp = new Spreadsheet();
        $sp->getProperties()
            ->setCreator('SYNTImeat')
            ->setTitle("Exportacion {$branchName}")
            ->setDescription('Catalogo e Inventario');

        // HOJA 1: Catalogo
        $ws1 = $sp->getActiveSheet();
        $ws1->setTitle('Catalogo');

        $ws1->mergeCells('A1:H1');
        $ws1->setCellValue('A1', strtoupper($business->name) . ' - ' . strtoupper($branchName));
        $ws1->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws1->getRowDimension(1)->setRowHeight(28);

        $ws1->mergeCells('A2:H2');
        $ws1->setCellValue('A2', 'Catalogo de Productos - Tasa BCV: Bs. ' . number_format($rate, 2) . ' / USD - ' . now()->format('d/m/Y'));
        $ws1->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 10, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers1 = ['Cod. Barra', 'Categoria', 'Producto', 'Tipo', 'Precio USD/kg', 'Precio Bs/kg', 'Costo USD/kg', 'Margen %'];
        foreach ($headers1 as $i => $h) {
            $col = chr(65 + $i);
            $ws1->setCellValue("{$col}3", $h);
        }
        $ws1->getStyle('A3:H3')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
        $ws1->getRowDimension(3)->setRowHeight(20);

        $row = 4;
        foreach ($products as $p) {
            $priceUsd = $p->sale_mode === 'weight' ? (float) ($p->price_per_kg_usd ?? 0) : (float) ($p->price_per_unit_usd ?? 0);
            $priceBs  = round($priceUsd * $rate, 2);
            $costUsd  = (float) ($p->cost_per_kg_usd ?? 0);
            $margin   = $priceUsd > 0 && $costUsd > 0 ? round((($priceUsd - $costUsd) / $priceUsd) * 100, 1) : null;

            $ws1->setCellValue("A{$row}", $p->barcode ?? '-');
            $ws1->setCellValue("B{$row}", $p->category?->name ?? '-');
            $ws1->setCellValue("C{$row}", $p->name);
            $ws1->setCellValue("D{$row}", $p->sale_mode === 'weight' ? 'Kg' : 'Und');
            $ws1->setCellValue("E{$row}", $priceUsd > 0 ? $priceUsd : null);
            $ws1->setCellValue("F{$row}", $priceBs > 0 ? $priceBs : null);
            $ws1->setCellValue("G{$row}", $costUsd > 0 ? $costUsd : null);
            $ws1->setCellValue("H{$row}", $margin);

            $ws1->getStyle("E{$row}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $ws1->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $ws1->getStyle("G{$row}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $ws1->getStyle("H{$row}")->getNumberFormat()->setFormatCode('0.0"%"');

            if ($row % 2 === 0) {
                $ws1->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F4FA');
            }

            if ($margin !== null && $margin < 0) {
                $ws1->getStyle("H{$row}")->getFont()->getColor()->setRGB('C00000');
                $ws1->getStyle("H{$row}")->getFont()->setBold(true);
            }

            $row++;
        }

        foreach (['A' => 12, 'B' => 18, 'C' => 30, 'D' => 8, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 12] as $col => $w) {
            $ws1->getColumnDimension($col)->setWidth($w);
        }

        // HOJA 2: Inventario
        $ws2 = $sp->createSheet();
        $ws2->setTitle('Inventario');

        $ws2->mergeCells('A1:G1');
        $ws2->setCellValue('A1', strtoupper($business->name) . ' - ' . strtoupper($branchName));
        $ws2->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws2->getRowDimension(1)->setRowHeight(28);

        $ws2->mergeCells('A2:G2');
        $ws2->setCellValue('A2', 'Inventario Actual - ' . now()->format('d/m/Y H:i'));
        $ws2->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 10, 'italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers2 = ['Producto', 'Categoria', 'Tipo', 'Stock Actual', 'Unidad', 'Ultimo Ingreso', 'Estado'];
        foreach ($headers2 as $i => $h) {
            $col = chr(65 + $i);
            $ws2->setCellValue("{$col}3", $h);
        }
        $ws2->getStyle('A3:G3')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws2->getRowDimension(3)->setRowHeight(20);

        $row = 4;
        foreach ($products as $p) {
            $stock    = $stockMap[$p->id] ?? 0;
            $minStock = (float) ($p->min_stock ?? 0);
            $unit     = $p->sale_mode === 'weight' ? 'kg' : 'und';
            $lastAt   = $lastEntryMap[$p->id] ?? null;

            if ($stock <= 0) {
                $estado = 'Sin stock';
            } elseif ($minStock > 0 && $stock < $minStock) {
                $estado = 'Stock bajo';
            } else {
                $estado = 'OK';
            }

            $ws2->setCellValue("A{$row}", $p->name);
            $ws2->setCellValue("B{$row}", $p->category?->name ?? '-');
            $ws2->setCellValue("C{$row}", $p->sale_mode === 'weight' ? 'Kg' : 'Und');
            $ws2->setCellValue("D{$row}", $stock);
            $ws2->setCellValue("E{$row}", $unit);
            $ws2->setCellValue("F{$row}", $lastAt ? \Carbon\Carbon::parse($lastAt)->format('d/m/Y') : '-');
            $ws2->setCellValue("G{$row}", $estado);

            $ws2->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.000');

            $color = match ($estado) {
                'Sin stock'  => 'FFDCE0',
                'Stock bajo' => 'FFF2CC',
                default      => $row % 2 === 0 ? 'F0F4FA' : 'FFFFFF',
            };
            $ws2->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);

            $row++;
        }

        foreach (['A' => 30, 'B' => 18, 'C' => 8, 'D' => 14, 'E' => 8, 'F' => 16, 'G' => 12] as $col => $w) {
            $ws2->getColumnDimension($col)->setWidth($w);
        }

        // HOJA 3: Resumen
        $ws3 = $sp->createSheet();
        $ws3->setTitle('Resumen');

        $totalProductos = $products->count();
        $sinStock       = $products->filter(fn ($p) => ($stockMap[$p->id] ?? 0) <= 0)->count();
        $bajoMinimo     = $products->filter(fn ($p) => ($p->min_stock ?? 0) > 0 && ($stockMap[$p->id] ?? 0) < $p->min_stock && ($stockMap[$p->id] ?? 0) > 0)->count();
        $valorInvUsd    = $products->sum(fn ($p) => ($stockMap[$p->id] ?? 0) * (float) ($p->price_per_kg_usd ?? $p->price_per_unit_usd ?? 0));
        $valorInvBs     = round($valorInvUsd * $rate, 2);

        $ws3->setCellValue('A1', 'RESUMEN');
        $ws3->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws3->setCellValue('A3', 'Sucursal');          $ws3->setCellValue('B3', $branchName);
        $ws3->setCellValue('A4', 'Fecha');             $ws3->setCellValue('B4', now()->format('d/m/Y H:i'));
        $ws3->setCellValue('A5', 'Tasa BCV');          $ws3->setCellValue('B5', $rate);
        $ws3->setCellValue('A7', 'Total productos');   $ws3->setCellValue('B7', $totalProductos);
        $ws3->setCellValue('A8', 'Sin stock');         $ws3->setCellValue('B8', $sinStock);
        $ws3->setCellValue('A9', 'Stock bajo minimo'); $ws3->setCellValue('B9', $bajoMinimo);
        $ws3->setCellValue('A11', 'Valor inv. USD');   $ws3->setCellValue('B11', round($valorInvUsd, 2));
        $ws3->setCellValue('A12', 'Valor inv. Bs');    $ws3->setCellValue('B12', $valorInvBs);

        $ws3->getStyle('B11')->getNumberFormat()->setFormatCode('"$"#,##0.00');
        $ws3->getStyle('B12')->getNumberFormat()->setFormatCode('#,##0.00');
        $ws3->getColumnDimension('A')->setWidth(22);
        $ws3->getColumnDimension('B')->setWidth(20);

        $sp->setActiveSheetIndex(0);
        $filename = 'SYNTImeat_' . str_replace(' ', '_', $branchName) . '_' . now()->format('Ymd_Hi') . '.xlsx';

        return response()->streamDownload(function () use ($sp) {
            $writer = new Xlsx($sp);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
