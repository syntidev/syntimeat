<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Despiece — {{ $business->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #fff;
            padding: 12mm 14mm;
        }

        /* ── Header ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2.5px solid #b91c1c;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header-logo {
            height: 64px;
            width: auto;
            object-fit: contain;
        }
        .header-center {
            flex: 1;
            text-align: center;
            padding: 0 10px;
        }
        .header-center h1 {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #b91c1c;
            text-transform: uppercase;
        }
        .header-center p {
            font-size: 10px;
            color: #555;
            margin-top: 2px;
        }
        .header-right {
            text-align: right;
            font-size: 9.5px;
            color: #444;
            line-height: 1.5;
            min-width: 130px;
        }
        .header-right strong { font-size: 11px; color: #1a1a1a; }

        /* ── Entry info box ── */
        .entry-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .entry-field label {
            font-size: 8.5px;
            font-weight: 700;
            color: #b91c1c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 3px;
        }
        .entry-field .val {
            border-bottom: 1px solid #d1d5db;
            min-height: 16px;
            font-size: 11px;
            padding-bottom: 1px;
        }
        .entry-field .val.prefilled {
            font-weight: 700;
            color: #1a1a1a;
        }

        /* ── Section title ── */
        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0 5px;
        }
        .section-title .icon {
            font-size: 15px;
        }
        .section-title h2 {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .section-title.res h2   { color: #dc2626; }
        .section-title.cerdo h2 { color: #ea580c; }
        .section-title.pollo h2 { color: #ca8a04; }

        .section-title .stripe {
            flex: 1;
            height: 2px;
            border-radius: 1px;
        }
        .section-title.res .stripe   { background: #dc2626; }
        .section-title.cerdo .stripe { background: #ea580c; }
        .section-title.pollo .stripe { background: #ca8a04; }

        /* ── Cut table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 10.5px;
        }
        thead th {
            padding: 4px 6px;
            text-align: left;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1.5px solid #374151;
            color: #374151;
            background: #f9fafb;
        }
        thead th.num { text-align: right; }

        tbody td {
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: bottom;
        }
        tbody td.num { text-align: right; }
        tbody tr.input-row td { height: 22px; }
        tbody tr.input-row td.write { border-bottom: 1px solid #9ca3af; }

        .subtotal-row td {
            background: #f3f4f6;
            font-weight: 700;
            font-size: 10px;
            padding: 4px 6px;
            border-top: 1.5px solid #374151;
        }
        .merma-row td {
            color: #dc2626;
            font-weight: 700;
            font-size: 10px;
            padding: 4px 6px;
            border-bottom: 1.5px solid #dc2626;
        }

        .col-corte   { width: 38%; }
        .col-kgent   { width: 16%; }
        .col-kgreal  { width: 16%; }
        .col-merma   { width: 16%; }
        .col-obs     { width: 14%; }

        /* ── Totals box ── */
        .totals-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            border: 1.5px solid #374151;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0 10px;
        }
        .total-cell {
            padding: 6px 8px;
            border-right: 1px solid #e5e7eb;
            text-align: center;
        }
        .total-cell:last-child { border-right: none; }
        .total-cell label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
            display: block;
            margin-bottom: 2px;
        }
        .total-cell .tval {
            font-size: 13px;
            font-weight: 900;
            color: #1a1a1a;
            border-bottom: 1.5px solid #374151;
            min-height: 18px;
        }
        .total-cell.merma .tval { color: #dc2626; }
        .total-cell.ok .tval    { color: #16a34a; }

        /* ── Signatures ── */
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 12px;
        }
        .sig-box {
            border-top: 1px solid #374151;
            padding-top: 4px;
            text-align: center;
        }
        .sig-box label {
            font-size: 8.5px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .sig-space { height: 32px; }

        /* ── Notes ── */
        .notes-box {
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 6px 8px;
            margin: 8px 0;
        }
        .notes-box label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
            display: block;
            margin-bottom: 18px;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 10px;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8px;
            color: #9ca3af;
        }
        .footer a { color: #b91c1c; text-decoration: none; font-weight: 700; }

        /* ── Print btn (no imprime) ── */
        .print-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            background: #b91c1c;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .print-btn:hover { background: #991b1b; }

        @media print {
            body { padding: 8mm 10mm; }
            .print-btn { display: none; }
            @page { margin: 6mm; size: A4 portrait; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Imprimir</button>

<!-- ── Header ─────────────────────────────────────────────────────── -->
<div class="header">
    @if($business->logo_path)
        <img
            src="{{ Storage::url($business->logo_path) }}"
            alt="{{ $business->name }}"
            class="header-logo"
        >
    @else
        <div style="width:64px;height:64px;background:#fef2f2;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:28px;">🥩</div>
    @endif

    <div class="header-center">
        <h1>Planilla de Despiece</h1>
        <p>Control de cortes, rendimiento y merma por pieza</p>
    </div>

    <div class="header-right">
        <strong>{{ $business->name }}</strong><br>
        @if($business->address && $business->address !== 'DIRECCIÓN')
            {{ $business->address }}<br>
        @endif
        @if($business->phone)
            {{ $business->phone }}<br>
        @endif
        <span style="color:#b91c1c;font-weight:700;">N° PLANILLA: ________</span>
    </div>
</div>

<!-- ── Datos de la pieza ───────────────────────────────────────────── -->
<div class="entry-box">
    <div class="entry-field">
        <label>Fecha</label>
        <div class="val prefilled">{{ $entry ? $entry->entered_at?->format('d/m/Y') : '' }}</div>
    </div>
    <div class="entry-field">
        <label>Tipo de pieza</label>
        <div class="val prefilled">{{ $entry ? $entry->product_type : '' }}</div>
    </div>
    <div class="entry-field">
        <label>Descripción / ID pieza</label>
        <div class="val prefilled">{{ $entry ? ($entry->description ?: '—') : '' }}</div>
    </div>
    <div class="entry-field">
        <label>Proveedor</label>
        <div class="val prefilled">{{ $entry ? ($entry->supplier ?: '—') : '' }}</div>
    </div>
    <div class="entry-field">
        <label>Kg entrada bóveda</label>
        <div class="val prefilled">{{ $entry ? number_format($entry->kg_entrada, 3) . ' kg' : '' }}</div>
    </div>
    <div class="entry-field">
        <label>Kg surtidos a fábrica</label>
        <div class="val prefilled">{{ $entry ? number_format($entry->kg_surtido_vitrina, 3) . ' kg' : '' }}</div>
    </div>
    <div class="entry-field">
        <label>Fecha de despiece</label>
        <div class="val"></div>
    </div>
    <div class="entry-field">
        <label>Carnicero responsable</label>
        <div class="val"></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════ -->
<!-- RES                                                               -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="section-title res">
    <span class="icon">🐄</span>
    <h2>Res (Bovino)</h2>
    <div class="stripe"></div>
</div>

<table>
    <thead>
        <tr>
            <th class="col-corte">Corte / Pieza</th>
            <th class="col-kgent num">Kg estimado</th>
            <th class="col-kgreal num">Kg real pesado</th>
            <th class="col-merma num">Merma corte</th>
            <th class="col-obs">Observación</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['Solomo abierto','Solomo cerrado','Punta trasera','Pulpa negra','Paleta','Costilla','Lagarto / Osobuco','Cogote / Pescuezo','Hueso (sin carne)','Descarte / Merma'] as $corte)
        <tr class="input-row">
            <td>{{ $corte }}</td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="write"></td>
        </tr>
        @endforeach
        <tr class="input-row" style="border-bottom:1px dashed #d1d5db;">
            <td style="color:#9ca3af;font-style:italic;">Otro: _______________</td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="write"></td>
        </tr>
        <tr class="subtotal-row">
            <td>SUBTOTAL RES</td>
            <td class="num">_______ kg</td>
            <td class="num">_______ kg</td>
            <td class="num merma-row" style="color:#dc2626;">_______ kg</td>
            <td></td>
        </tr>
    </tbody>
</table>

<!-- ══════════════════════════════════════════════════════════════════ -->
<!-- CERDO                                                             -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="section-title cerdo">
    <span class="icon">🐷</span>
    <h2>Cerdo (Porcino)</h2>
    <div class="stripe"></div>
</div>

<table>
    <thead>
        <tr>
            <th class="col-corte">Corte / Pieza</th>
            <th class="col-kgent num">Kg estimado</th>
            <th class="col-kgreal num">Kg real pesado</th>
            <th class="col-merma num">Merma corte</th>
            <th class="col-obs">Observación</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['Chuleta','Pierna / Pernil','Paleta','Costilla','Lomo','Tocino / Lonja','Cogote','Hueso (sin carne)','Descarte / Merma'] as $corte)
        <tr class="input-row">
            <td>{{ $corte }}</td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="write"></td>
        </tr>
        @endforeach
        <tr class="input-row" style="border-bottom:1px dashed #d1d5db;">
            <td style="color:#9ca3af;font-style:italic;">Otro: _______________</td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="write"></td>
        </tr>
        <tr class="subtotal-row">
            <td>SUBTOTAL CERDO</td>
            <td class="num">_______ kg</td>
            <td class="num">_______ kg</td>
            <td class="num" style="color:#dc2626;">_______ kg</td>
            <td></td>
        </tr>
    </tbody>
</table>

<!-- ══════════════════════════════════════════════════════════════════ -->
<!-- POLLO                                                             -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="section-title pollo">
    <span class="icon">🐔</span>
    <h2>Pollo (Ave)</h2>
    <div class="stripe"></div>
</div>

<table>
    <thead>
        <tr>
            <th class="col-corte">Corte / Pieza</th>
            <th class="col-kgent num">Kg estimado</th>
            <th class="col-kgreal num">Kg real pesado</th>
            <th class="col-merma num">Merma corte</th>
            <th class="col-obs">Observación</th>
        </tr>
    </thead>
    <tbody>
        @foreach(['Pechuga (sin hueso)','Pechuga (con hueso)','Muslo','Pierna','Ala','Espinazo / Menudos','Pollo entero (sin despiezar)','Descarte / Merma'] as $corte)
        <tr class="input-row">
            <td>{{ $corte }}</td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="write"></td>
        </tr>
        @endforeach
        <tr class="input-row" style="border-bottom:1px dashed #d1d5db;">
            <td style="color:#9ca3af;font-style:italic;">Otro: _______________</td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="num write"></td>
            <td class="write"></td>
        </tr>
        <tr class="subtotal-row">
            <td>SUBTOTAL POLLO</td>
            <td class="num">_______ kg</td>
            <td class="num">_______ kg</td>
            <td class="num" style="color:#dc2626;">_______ kg</td>
            <td></td>
        </tr>
    </tbody>
</table>

<!-- ── Totales generales ───────────────────────────────────────────── -->
<div class="totals-box">
    <div class="total-cell">
        <label>Kg surtidos (entrada)</label>
        <div class="tval">{{ $entry ? number_format($entry->kg_surtido_vitrina, 3) : '______' }} kg</div>
    </div>
    <div class="total-cell ok">
        <label>Kg cortes documentados</label>
        <div class="tval">____________ kg</div>
    </div>
    <div class="total-cell merma">
        <label>Merma total (diferencia)</label>
        <div class="tval">____________ kg</div>
    </div>
    <div class="total-cell">
        <label>% Rendimiento</label>
        <div class="tval">____________ %</div>
    </div>
</div>

<!-- ── Observaciones ──────────────────────────────────────────────── -->
<div class="notes-box">
    <label>Observaciones generales</label>
</div>

<!-- ── Firmas ─────────────────────────────────────────────────────── -->
<div class="signatures">
    <div class="sig-box">
        <div class="sig-space"></div>
        <label>Carnicero / Responsable del despiece</label>
    </div>
    <div class="sig-box">
        <div class="sig-space"></div>
        <label>Supervisor / Encargado</label>
    </div>
    <div class="sig-box">
        <div class="sig-space"></div>
        <label>Fecha y hora de registro</label>
    </div>
</div>

<!-- ── Footer ─────────────────────────────────────────────────────── -->
<div class="footer">
    <span>{{ $business->name }} &copy; {{ date('Y') }} — Uso interno. Todos los derechos reservados.</span>
    <span>Desarrollado por <a href="https://synti.dev" target="_blank">synti.dev</a></span>
</div>

</body>
</html>
