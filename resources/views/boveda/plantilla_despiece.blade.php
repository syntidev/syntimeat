<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Despiece — {{ $business->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #fff;
            padding: 10mm 12mm;
        }

        /* ── Header ─────────────────────────────────────────────────── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #b91c1c;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-logo {
            height: 60px;
            width: auto;
            object-fit: contain;
        }
        .logo-placeholder {
            width: 60px; height: 60px;
            background: #fef2f2;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
        }
        .header-center {
            flex: 1;
            text-align: center;
            padding: 0 12px;
        }
        .header-center h1 {
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #b91c1c;
            text-transform: uppercase;
        }
        .header-center p { font-size: 9.5px; color: #555; margin-top: 2px; }
        .header-right {
            text-align: right;
            font-size: 9.5px;
            color: #444;
            line-height: 1.6;
            min-width: 140px;
        }
        .header-right strong { font-size: 11px; color: #1a1a1a; display: block; }
        .planilla-num {
            margin-top: 4px;
            font-weight: 700;
            color: #b91c1c;
            font-size: 10px;
        }

        /* ── Info de la pieza ───────────────────────────────────────── */
        .entry-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 4px;
            padding: 7px 10px;
            margin-bottom: 10px;
        }
        .ef label {
            font-size: 8px;
            font-weight: 700;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: block;
            margin-bottom: 2px;
        }
        .ef .val {
            border-bottom: 1px solid #d1d5db;
            min-height: 15px;
            font-size: 11px;
            padding-bottom: 1px;
            font-weight: 700;
        }
        .ef .val.blank { font-weight: 400; }

        /* ── Sección por categoría ──────────────────────────────────── */
        .section { margin-bottom: 8px; }
        .section-head {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .section-head h2 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            white-space: nowrap;
        }
        .section-head .line {
            flex: 1;
            height: 2px;
            border-radius: 1px;
        }

        /* colores por categoría */
        .cat-res       h2, .cat-res       .subtotal td { color: #dc2626; }
        .cat-res       .line { background: #dc2626; }
        .cat-cerdo     h2, .cat-cerdo     .subtotal td { color: #ea580c; }
        .cat-cerdo     .line { background: #ea580c; }
        .cat-pollo     h2, .cat-pollo     .subtotal td { color: #ca8a04; }
        .cat-pollo     .line { background: #ca8a04; }
        .cat-charcutería h2, .cat-charcutería .subtotal td { color: #7c3aed; }
        .cat-charcutería .line { background: #7c3aed; }
        .cat-embutidos h2, .cat-embutidos .subtotal td { color: #0369a1; }
        .cat-embutidos .line { background: #0369a1; }
        .cat-trastes   h2, .cat-trastes   .subtotal td { color: #4b5563; }
        .cat-trastes   .line { background: #4b5563; }

        /* ── Tabla de cortes ────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead th {
            padding: 3px 5px;
            text-align: left;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #374151;
            background: #f9fafb;
            border-bottom: 1.5px solid #374151;
        }
        thead th.r { text-align: right; }
        tbody td {
            padding: 4px 5px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: bottom;
            height: 20px;
        }
        tbody td.r { text-align: right; }
        .product-name { font-weight: 600; }
        .write  { border-bottom: 1px solid #9ca3af !important; background: #fafafa; }
        .filled { border-bottom: 2px solid #15803d !important; background: #f0fdf4; font-weight: 700; color: #15803d; }
        .subtotal td {
            padding: 3px 5px;
            font-size: 9.5px;
            font-weight: 700;
            border-top: 1.5px solid currentColor;
            background: #f9fafb;
        }
        .subtotal td.r { text-align: right; }

        .col-prod  { width: 35%; }
        .col-est   { width: 16%; }
        .col-real  { width: 16%; }
        .col-merma { width: 16%; }
        .col-obs   { width: 17%; }

        /* ── Totales ────────────────────────────────────────────────── */
        .totals {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border: 1.5px solid #374151;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        .tc {
            padding: 5px 7px;
            text-align: center;
            border-right: 1px solid #e5e7eb;
        }
        .tc:last-child { border-right: none; }
        .tc label { font-size: 7.5px; font-weight: 700; text-transform: uppercase; color: #6b7280; display: block; margin-bottom: 2px; }
        .tc .v { font-size: 12px; font-weight: 900; border-bottom: 1.5px solid #374151; min-height: 17px; }
        .tc.red   .v { color: #dc2626; }
        .tc.green .v { color: #16a34a; }

        /* ── Observaciones ──────────────────────────────────────────── */
        .obs-box {
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 5px 7px;
            margin-bottom: 8px;
        }
        .obs-box label { font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6b7280; display: block; margin-bottom: 20px; }

        /* ── Firmas ─────────────────────────────────────────────────── */
        .sigs { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 8px; }
        .sig { border-top: 1px solid #374151; padding-top: 3px; text-align: center; }
        .sig label { font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6b7280; }
        .sig-space { height: 28px; }

        /* ── Footer ─────────────────────────────────────────────────── */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            display: flex;
            justify-content: space-between;
            font-size: 7.5px;
            color: #9ca3af;
        }
        .footer a { color: #b91c1c; font-weight: 700; text-decoration: none; }

        /* ── Botón imprimir ─────────────────────────────────────────── */
        .print-btn {
            position: fixed; top: 14px; right: 14px;
            background: #b91c1c; color: #fff;
            border: none; border-radius: 7px;
            padding: 7px 16px; font-size: 12px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .print-btn:hover { background: #991b1b; }

        @media print {
            body { padding: 6mm 8mm; }
            .print-btn { display: none; }
            @page { margin: 5mm; size: A4 portrait; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Imprimir</button>

{{-- ── Header ──────────────────────────────────────────────────────── --}}
<div class="header">
    @if($business->logo_path)
        <img src="{{ Storage::url($business->logo_path) }}" alt="{{ $business->name }}" class="header-logo">
    @else
        <div class="logo-placeholder">🥩</div>
    @endif

    <div class="header-center">
        <h1>Planilla de Despiece</h1>
        <p>Control de cortes, rendimiento y merma por pieza</p>
    </div>

    <div class="header-right">
        <strong>{{ $business->name }}</strong>
        @if($business->address && $business->address !== 'DIRECCIÓN')
            {{ $business->address }}<br>
        @endif
        @if($business->phone)
            {{ $business->phone }}
        @endif
        <div class="planilla-num">N° PLANILLA: _________</div>
    </div>
</div>

{{-- ── Datos de la pieza ───────────────────────────────────────────── --}}
<div class="entry-box">
    <div class="ef">
        <label>Fecha entrada bóveda</label>
        <div class="val">{{ $entry->entered_at?->format('d/m/Y') }}</div>
    </div>
    <div class="ef">
        <label>Tipo de pieza</label>
        <div class="val">{{ $entry->product_type }}</div>
    </div>
    <div class="ef">
        <label>Descripción / ID pieza</label>
        <div class="val">{{ $entry->description ?: '—' }}</div>
    </div>
    <div class="ef">
        <label>Proveedor</label>
        <div class="val">{{ $entry->supplier ?: '—' }}</div>
    </div>
    <div class="ef">
        <label>Kg entrada bóveda</label>
        <div class="val">{{ number_format((float)$entry->kg_entrada, 3) }} kg</div>
    </div>
    <div class="ef">
        <label>Kg surtidos a despiece</label>
        <div class="val">{{ number_format((float)$entry->kg_surtido_vitrina, 3) }} kg</div>
    </div>
    <div class="ef">
        <label>Fecha de despiece</label>
        <div class="val {{ $entry->despiece_completado_at ? '' : 'blank' }}">
            {{ $entry->despiece_completado_at?->format('d/m/Y H:i') ?? '&nbsp;' }}
        </div>
    </div>
    <div class="ef">
        <label>Estado</label>
        <div class="val" style="color: {{ $entry->despiece_completado_at ? '#16a34a' : '#b91c1c' }}; font-weight:700">
            {{ $entry->despiece_completado_at ? '✓ Registrado en sistema' : 'Pendiente de registro' }}
        </div>
    </div>
</div>

{{-- ── Cortes de la pieza ───────────────────────────────────────────── --}}
@php
    $icons = ['Res' => '🐄', 'Cerdo' => '🐷', 'Pollo' => '🐔'];
    $colors = ['Res' => '#dc2626', 'Cerdo' => '#ea580c', 'Pollo' => '#ca8a04'];
    $icon  = $icons[$catName]  ?? '📦';
    $color = $colors[$catName] ?? '#374151';
@endphp
<div class="section" style="--cat-color: {{ $color }}">
    <div class="section-head">
        <span>{{ $icon }}</span>
        <h2 style="color: var(--cat-color)">{{ $catName ?? $entry->product_type }}</h2>
        <div class="line" style="background: var(--cat-color)"></div>
    </div>
    <table>
        <thead>
            <tr>
                <th class="col-prod">Producto del catálogo</th>
                <th class="col-est r">Kg estimado</th>
                <th class="col-real r">Kg real pesado</th>
                <th class="col-merma r">Merma corte</th>
                <th class="col-obs">Observación</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productos as $producto)
            @php $kgReal = isset($kgRegistrados[$producto->id]) ? (float)$kgRegistrados[$producto->id] : null; @endphp
            <tr>
                <td class="product-name">{{ $producto->name }}</td>
                <td class="r write"></td>
                <td class="r {{ $kgReal !== null ? 'filled' : 'write' }}">
                    {{ $kgReal !== null ? number_format($kgReal, 3) : '' }}
                </td>
                <td class="r write"></td>
                <td class="write"></td>
            </tr>
            @endforeach
            <tr class="subtotal" style="color: var(--cat-color)">
                <td>TOTAL DOCUMENTADO</td>
                <td class="r">________ kg</td>
                <td class="r">________ kg</td>
                <td class="r">________ kg</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>

{{-- ── Totales generales ────────────────────────────────────────────── --}}
<div class="totals">
    <div class="tc">
        <label>Kg surtidos (entrada)</label>
        <div class="v">{{ number_format((float)$entry->kg_surtido_vitrina, 3) }} kg</div>
    </div>
    <div class="tc green">
        <label>Kg cortes documentados</label>
        <div class="v">{{ $totalDocumentado > 0 ? number_format($totalDocumentado, 3) . ' kg' : '____________ kg' }}</div>
    </div>
    <div class="tc red">
        <label>Merma total (diferencia)</label>
        <div class="v">{{ $totalDocumentado > 0 ? number_format($mermaReal, 3) . ' kg' : '____________ kg' }}</div>
    </div>
    <div class="tc">
        <label>% Rendimiento</label>
        <div class="v">{{ $rendimiento !== null ? $rendimiento . ' %' : '____________ %' }}</div>
    </div>
</div>

{{-- ── Observaciones ────────────────────────────────────────────────── --}}
<div class="obs-box">
    <label>Observaciones generales del despiece</label>
</div>

{{-- ── Firmas ───────────────────────────────────────────────────────── --}}
<div class="sigs">
    <div class="sig">
        <div class="sig-space"></div>
        <label>Carnicero / Responsable del despiece</label>
    </div>
    <div class="sig">
        <div class="sig-space"></div>
        <label>Supervisor / Encargado</label>
    </div>
    <div class="sig">
        <div class="sig-space"></div>
        <label>Fecha y hora de registro en sistema</label>
    </div>
</div>

{{-- ── Footer ──────────────────────────────────────────────────────── --}}
<div class="footer">
    <span>{{ $business->name }} &copy; {{ date('Y') }} — Uso interno. Prohibida su reproducción.</span>
    <span>Desarrollado por <a href="https://synti.dev">synti.dev</a></span>
</div>

</body>
</html>
