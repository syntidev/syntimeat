<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>SYNTImeat — Certificación Servicio de Impresión</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #0a0a0a; color: #e0e0e0;
    padding: 2rem 1rem 4rem; line-height: 1.6;
}
.wrap { max-width: 820px; margin: 0 auto; }

.site-header {
    display: flex; align-items: center; gap: .75rem;
    margin-bottom: 2rem; padding-bottom: 1.25rem;
    border-bottom: 1px solid #1e1e1e;
}
.logo-synti { color: #e0e0e0; font-weight: 800; font-size: 1.2rem; }
.logo-meat  { color: #B91C1C; font-weight: 800; font-size: 1.2rem; }
.header-sep { flex: 1; }
.tag-pill {
    font-size: .7rem; font-weight: 700; letter-spacing: .5px;
    background: rgba(37,99,235,.15); color: #93c5fd;
    border: 1px solid rgba(37,99,235,.3);
    padding: 3px 10px; border-radius: 20px; font-family: monospace;
}

h1 { font-size: 1.4rem; font-weight: 700; color: #f5f5f5; margin-bottom: .25rem; }
.subtitle { color: #666; font-size: .875rem; margin-bottom: 2rem; }

/* ── Sección ── */
.section { margin-bottom: 2rem; }
.section-title {
    font-size: .8rem; font-weight: 700; letter-spacing: .08em;
    color: #555; text-transform: uppercase;
    margin-bottom: .75rem; padding-bottom: .4rem;
    border-bottom: 1px solid #1a1a1a;
}

/* ── Check cards ── */
.checks { display: flex; flex-direction: column; gap: .5rem; }
.check-row {
    display: flex; align-items: center; gap: .85rem;
    background: #111; border: 1px solid #1e1e1e;
    border-radius: 8px; padding: .7rem 1rem;
}
.check-icon {
    width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .75rem; font-weight: 700;
}
.check-ok   { background: rgba(16,185,129,.15); color: #4ade80; border: 1px solid rgba(16,185,129,.3); }
.check-fail { background: rgba(239,68,68,.12);  color: #f87171; border: 1px solid rgba(239,68,68,.25); }
.check-label { flex: 1; font-size: .875rem; color: #ccc; }
.check-detail { font-size: .75rem; color: #555; }

/* ── Tests en vivo ── */
.live-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
@media (max-width: 560px) { .live-grid { grid-template-columns: 1fr; } }

.live-card {
    background: #111; border: 1px solid #1e1e1e;
    border-radius: 10px; padding: 1rem;
}
.live-card__title { font-size: .8rem; font-weight: 700; color: #888; margin-bottom: .4rem; }
.live-status {
    font-size: .82rem; font-family: monospace;
    min-height: 22px; color: #555;
}
.live-status.ok   { color: #4ade80; }
.live-status.fail { color: #f87171; }
.live-status.running { color: #fbbf24; }

.btn-run {
    margin-top: .65rem; display: inline-flex; align-items: center; gap: .4rem;
    padding: .4rem .9rem; border-radius: 6px; font-size: .8rem; font-weight: 600;
    background: #1e1e1e; color: #aaa; border: 1px solid #2a2a2a;
    cursor: pointer; font-family: inherit; transition: all .15s;
}
.btn-run:hover { background: #252525; color: #e0e0e0; border-color: #333; }

/* ── Printer input ── */
.printer-row {
    display: flex; gap: .65rem; align-items: center;
    background: #111; border: 1px solid #1e1e1e;
    border-radius: 10px; padding: .85rem 1rem;
    margin-bottom: .75rem;
}
.printer-row input {
    flex: 1; background: #0a0a0a; border: 1px solid #2a2a2a;
    color: #e0e0e0; font-family: monospace; font-size: .875rem;
    border-radius: 6px; padding: .45rem .75rem; outline: none;
}
.printer-row input:focus { border-color: #2563eb; }
.btn-print-test {
    padding: .45rem 1rem; border-radius: 6px; font-size: .82rem; font-weight: 600;
    background: #166534; color: #4ade80; border: 1px solid #15803d;
    cursor: pointer; font-family: inherit; white-space: nowrap; transition: background .15s;
}
.btn-print-test:hover { background: #14532d; }
.btn-print-test:disabled { opacity: .5; cursor: not-allowed; }

/* ── Log ── */
.log-box {
    background: #080808; border: 1px solid #1a1a1a; border-radius: 8px;
    padding: .85rem 1rem; min-height: 100px; max-height: 220px;
    overflow-y: auto; font-family: monospace; font-size: .8rem;
    line-height: 1.7; white-space: pre-wrap;
}
.log-ok   { color: #4ade80; }
.log-err  { color: #f87171; }
.log-inf  { color: #60a5fa; }

/* ── Restore point banner ── */
.restore-banner {
    display: flex; align-items: center; gap: 1rem;
    background: rgba(37,99,235,.07); border: 1px solid rgba(37,99,235,.2);
    border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 2rem;
}
.restore-banner__icon { font-size: 1.5rem; flex-shrink: 0; }
.restore-banner__body h3 { font-size: .9rem; color: #93c5fd; margin-bottom: .15rem; }
.restore-banner__body p  { font-size: .8rem; color: #555; }
.restore-banner__body code {
    font-family: monospace; background: #111;
    border: 1px solid #222; border-radius: 4px; padding: 1px 6px;
    font-size: .8rem; color: #a3e635;
}

/* ── Links ── */
.action-links { display: flex; gap: .65rem; flex-wrap: wrap; margin-top: 1.5rem; }
.link-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .45rem 1rem; border-radius: 7px; font-size: .82rem; font-weight: 600;
    text-decoration: none; border: 1px solid #2a2a2a;
    background: #141414; color: #aaa; transition: all .15s;
}
.link-btn:hover { border-color: #444; color: #e0e0e0; }

footer {
    margin-top: 3rem; padding-top: 1.5rem;
    border-top: 1px solid #1a1a1a;
    font-size: .75rem; color: #333; text-align: center;
}
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="site-header">
    <span class="logo-synti">SYNTI</span><span class="logo-meat">meat</span>
    <div class="header-sep"></div>
    @if($tag)
    <span class="tag-pill">{{ $tag }} · {{ $commit }}</span>
    @endif
  </div>

  <h1>Certificación — Servicio de Impresión</h1>
  <p class="subtitle">Punto de restauración activo · Verificación completa del stack QZ Tray + Impresora 58 mm</p>

  <!-- Restore point banner -->
  <div class="restore-banner">
    <div class="restore-banner__icon">🔖</div>
    <div class="restore-banner__body">
      <h3>Punto de restauración: <code>{{ $tag ?: 'sin tag' }}</code></h3>
      <p>
        Commit: <code>{{ $commit }}</code> &nbsp;·&nbsp;
        Para restaurar: <code>git checkout {{ $tag }}</code> en el VPS + <code>npm run build</code>
      </p>
    </div>
  </div>

  <!-- ── 1. Checks del servidor ─────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">1 — Archivos del servidor</div>
    <div class="checks">
      <div class="check-row">
        <span class="check-icon {{ $checks['cert_file'] ? 'check-ok' : 'check-fail' }}">
          {{ $checks['cert_file'] ? '✓' : '✗' }}
        </span>
        <span class="check-label">Certificado digital QZ</span>
        <span class="check-detail">storage/app/qz/digital-certificate.txt</span>
      </div>
      <div class="check-row">
        <span class="check-icon {{ $checks['key_file'] ? 'check-ok' : 'check-fail' }}">
          {{ $checks['key_file'] ? '✓' : '✗' }}
        </span>
        <span class="check-label">Clave privada RSA</span>
        <span class="check-detail">storage/app/qz/private-key.pem</span>
      </div>
      <div class="check-row">
        <span class="check-icon {{ $checks['driver_zip'] ? 'check-ok' : 'check-fail' }}">
          {{ $checks['driver_zip'] ? '✓' : '✗' }}
        </span>
        <span class="check-label">Driver impresora 58 mm</span>
        <span class="check-detail">Referencias/soporte/58MM*.zip</span>
      </div>
      <div class="check-row">
        <span class="check-icon {{ $checks['qz_installer'] ? 'check-ok' : 'check-fail' }}">
          {{ $checks['qz_installer'] ? '✓' : '✗' }}
        </span>
        <span class="check-label">Instalador QZ Tray 2.2.6</span>
        <span class="check-detail">Referencias/soporte/qz-tray-2.2.6-x86_64.exe</span>
      </div>
    </div>
  </div>

  <!-- ── 2. Tests en vivo de endpoints ─────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">2 — Endpoints del servidor (tests en vivo)</div>
    <div class="live-grid">

      <div class="live-card">
        <div class="live-card__title">GET /qz/certificate</div>
        <div class="live-status" id="st-cert">Sin probar</div>
        <button class="btn-run" onclick="testEndpoint('cert', '/qz/certificate', 'GET')">Probar</button>
      </div>

      <div class="live-card">
        <div class="live-card__title">POST /qz/sign</div>
        <div class="live-status" id="st-sign">Sin probar</div>
        <button class="btn-run" onclick="testSign()">Probar</button>
      </div>

    </div>
  </div>

  <!-- ── 3. Test QZ Tray (requiere QZ activo en este PC) ───────────────────── -->
  <div class="section">
    <div class="section-title">3 — Conexión QZ Tray (requiere QZ corriendo en este PC)</div>

    <div class="printer-row">
      <input id="printerName" type="text" value="POS-58-ROCCIA" placeholder="Nombre exacto de impresora" />
      <button class="btn-print-test" id="btnPrint" onclick="runFullTest()">Imprimir ticket de prueba</button>
    </div>

    <div class="log-box" id="log">Esperando…</div>
  </div>

  <!-- ── 4. Links de acción ─────────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">4 — Acciones rápidas</div>
    <div class="action-links">
      <a href="/qz-test" target="_blank" class="link-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Diagnóstico completo
      </a>
      <a href="/soporte/guia" class="link-btn">Guía instalación</a>
      <a href="/soporte/driver-impresora" class="link-btn">Descargar driver</a>
      <a href="/soporte/qz-tray" class="link-btn">Descargar QZ Tray</a>
      <a href="/configuracion/hardware" class="link-btn">Configuración hardware</a>
    </div>
  </div>

  <footer>
    SYNTImeat · Certificación de impresión · Acceso restringido · {{ now()->format('d/m/Y H:i') }}
  </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js"></script>
<script>
const logEl = document.getElementById('log')

function addLog(msg, cls) {
    const d = document.createElement('div')
    if (cls) d.className = cls
    d.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg
    logEl.appendChild(d)
    logEl.scrollTop = logEl.scrollHeight
}
function logOk(m)  { addLog('✓ ' + m, 'log-ok') }
function logErr(m) { addLog('✗ ' + m, 'log-err') }
function logInf(m) { addLog('→ ' + m, 'log-inf') }

function setStatus(id, msg, cls) {
    const el = document.getElementById('st-' + id)
    el.textContent = msg
    el.className = 'live-status ' + (cls || '')
}

async function getCsrf() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    return m ? decodeURIComponent(m[1])
             : document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function testEndpoint(id, url, method) {
    setStatus(id, 'Probando…', 'running')
    try {
        const r = await fetch(url, { method, credentials: 'include' })
        const txt = await r.text()
        if (r.ok && txt.length > 10) {
            setStatus(id, '✓ OK — ' + txt.length + ' bytes', 'ok')
        } else {
            setStatus(id, '✗ HTTP ' + r.status, 'fail')
        }
    } catch(e) {
        setStatus(id, '✗ ' + e.message, 'fail')
    }
}

async function testSign() {
    setStatus('sign', 'Probando…', 'running')
    try {
        const csrf = await getCsrf()
        const r = await fetch('/qz/sign', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ toSign: 'test-payload-certificacion' }),
        })
        const txt = await r.text()
        if (r.ok && txt.length > 10) {
            setStatus('sign', '✓ Firma válida — ' + txt.length + ' chars base64', 'ok')
        } else {
            setStatus('sign', '✗ HTTP ' + r.status + ' — ' + txt.slice(0,60), 'fail')
        }
    } catch(e) {
        setStatus('sign', '✗ ' + e.message, 'fail')
    }
}

function configSecurity() {
    qz.security.setSignatureAlgorithm('SHA512')
    qz.security.setCertificatePromise((resolve, reject) =>
        fetch('/qz/certificate')
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(resolve).catch(reject)
    )
    qz.security.setSignaturePromise(toSign => (resolve, reject) => {
        getCsrf().then(csrf => fetch('/qz/sign', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ toSign }),
        })).then(r => r.text()).then(resolve).catch(reject)
    })
}

async function runFullTest() {
    logEl.innerHTML = ''
    const btn = document.getElementById('btnPrint')
    const printer = document.getElementById('printerName').value.trim()
    if (!printer) { logErr('Escribe el nombre de la impresora'); return; }

    btn.disabled = true
    try {
        if (typeof qz === 'undefined') throw new Error('qz-tray.js no cargó')
        logOk('qz-tray.js cargado')

        configSecurity()
        logInf('Seguridad configurada')

        if (!qz.websocket.isActive()) {
            logInf('Conectando a QZ Tray…')
            await qz.websocket.connect()
        }
        logOk('Conectado a QZ Tray')

        const printers = await qz.printers.find()
        logOk('Impresoras: ' + JSON.stringify(printers))

        const config = qz.configs.create(printer, { scaleContent: false, rasterize: false })
        logInf('Config creada para "' + printer + '"')

        const now = new Date().toLocaleString('es-VE')
        await qz.print(config, [
            { type: 'raw', format: 'command', data: '\x1b\x40' },
            { type: 'raw', format: 'command', data: '\x1b\x61\x01' },
            { type: 'raw', format: 'command', data: 'SYNTIMEAT\n' },
            { type: 'raw', format: 'command', data: 'CERTIFICACION DE IMPRESION\n' },
            { type: 'raw', format: 'command', data: '\x1b\x61\x00' },
            { type: 'raw', format: 'command', data: '--------------------------------\n' },
            { type: 'raw', format: 'command', data: 'Tag:     {{ $tag ?: "sin-tag" }}\n' },
            { type: 'raw', format: 'command', data: 'Commit:  {{ $commit }}\n' },
            { type: 'raw', format: 'command', data: 'Fecha:   ' + now + '\n' },
            { type: 'raw', format: 'command', data: 'Impresora: ' + printer + '\n' },
            { type: 'raw', format: 'command', data: '--------------------------------\n' },
            { type: 'raw', format: 'command', data: '\x1b\x45\x01' },
            { type: 'raw', format: 'command', data: 'SERVICIO OPERATIVO OK\n' },
            { type: 'raw', format: 'command', data: '\x1b\x45\x00' },
            { type: 'raw', format: 'command', data: '\n\n\n' },
            { type: 'raw', format: 'command', data: '\x1d\x56\x42\x01' },
        ])
        logOk('IMPRESION CERTIFICADA — ticket físico generado')

    } catch(e) {
        logErr('FALLO: ' + (e?.message ?? String(e)))
    } finally {
        btn.disabled = false
    }
}
</script>
</body>
</html>
