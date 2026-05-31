<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>SYNTImeat — Guía de Instalación Impresora + QZ Tray</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: #0a0a0a;
    color: #e0e0e0;
    line-height: 1.6;
    padding: 2rem 1rem 4rem;
  }

  .wrap { max-width: 780px; margin: 0 auto; }

  /* Header */
  .site-header {
    display: flex; align-items: center; gap: 0.75rem;
    margin-bottom: 2.5rem; padding-bottom: 1.25rem;
    border-bottom: 1px solid #222;
  }
  .logo-synti { color: #e0e0e0; font-weight: 800; font-size: 1.25rem; }
  .logo-meat  { color: #B91C1C; font-weight: 800; font-size: 1.25rem; }
  .header-sep { flex: 1; }
  .badge-private {
    font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;
    background: rgba(185,28,28,0.15); color: #f87171;
    border: 1px solid rgba(185,28,28,0.3);
    padding: 3px 10px; border-radius: 20px;
  }

  h1 { font-size: 1.5rem; font-weight: 700; color: #f5f5f5; margin-bottom: 0.35rem; }
  .subtitle { color: #888; font-size: 0.9rem; margin-bottom: 2.5rem; }

  /* Sections */
  .section { margin-bottom: 2.5rem; }

  .section-title {
    display: flex; align-items: center; gap: 0.6rem;
    font-size: 1rem; font-weight: 700; color: #f5f5f5;
    margin-bottom: 1rem; padding-bottom: 0.5rem;
    border-bottom: 1px solid #1e1e1e;
  }
  .step-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%;
    background: #B91C1C; color: #fff;
    font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
  }

  /* Download cards */
  .download-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
  @media (max-width: 540px) { .download-grid { grid-template-columns: 1fr; } }

  .dl-card {
    background: #141414; border: 1px solid #252525;
    border-radius: 10px; padding: 1.25rem;
  }
  .dl-card__title { font-weight: 600; font-size: 0.9rem; color: #f5f5f5; margin-bottom: 0.25rem; }
  .dl-card__meta  { font-size: 0.78rem; color: #666; margin-bottom: 1rem; }
  .btn-dl {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.55rem 1.1rem; border-radius: 7px;
    background: #B91C1C; color: #fff;
    font-size: 0.85rem; font-weight: 600;
    text-decoration: none; border: none; cursor: pointer;
    transition: background 0.15s;
  }
  .btn-dl:hover { background: #991b1b; }
  .btn-dl svg { flex-shrink: 0; }

  /* Steps */
  .steps { display: flex; flex-direction: column; gap: 0.75rem; }
  .step {
    display: flex; gap: 1rem; align-items: flex-start;
    background: #111; border: 1px solid #1e1e1e;
    border-radius: 8px; padding: 0.85rem 1rem;
  }
  .step__n {
    min-width: 22px; height: 22px; border-radius: 50%;
    background: #1e1e1e; border: 1px solid #333;
    color: #888; font-size: 0.7rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    margin-top: 2px;
  }
  .step__body { flex: 1; font-size: 0.875rem; color: #ccc; }
  .step__body strong { color: #f5f5f5; }

  /* Code */
  code {
    font-family: 'Consolas', 'Courier New', monospace;
    background: #1a1a1a; border: 1px solid #2a2a2a;
    border-radius: 4px; padding: 1px 6px;
    font-size: 0.83rem; color: #a3e635;
  }
  .code-block {
    font-family: 'Consolas', 'Courier New', monospace;
    background: #0f0f0f; border: 1px solid #222;
    border-radius: 8px; padding: 0.9rem 1rem;
    font-size: 0.82rem; color: #a3e635;
    margin: 0.75rem 0; overflow-x: auto; white-space: pre;
  }

  /* Alert */
  .alert {
    display: flex; gap: 0.75rem;
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25);
    border-radius: 8px; padding: 0.85rem 1rem;
    font-size: 0.85rem; color: #fcd34d; margin: 0.75rem 0;
  }
  .alert svg { flex-shrink: 0; margin-top: 1px; }

  .alert-info {
    background: rgba(59,130,246,0.08); border-color: rgba(59,130,246,0.25); color: #93c5fd;
  }

  /* Test link */
  .test-card {
    background: #0f1a0f; border: 1px solid #1a3a1a;
    border-radius: 10px; padding: 1.25rem 1.5rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  }
  .test-card__text h3 { font-size: 0.95rem; color: #4ade80; margin-bottom: 0.2rem; }
  .test-card__text p  { font-size: 0.82rem; color: #6b7280; }
  .btn-test {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.55rem 1.1rem; border-radius: 7px;
    background: #166534; color: #4ade80;
    font-size: 0.85rem; font-weight: 600;
    text-decoration: none; white-space: nowrap;
    border: 1px solid #15803d;
    transition: background 0.15s;
  }
  .btn-test:hover { background: #14532d; }

  /* Config path */
  .config-path {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: #141414; border: 1px solid #252525;
    border-radius: 6px; padding: 0.4rem 0.75rem;
    font-size: 0.82rem; color: #93c5fd; font-family: monospace;
  }

  footer {
    margin-top: 3rem; padding-top: 1.5rem;
    border-top: 1px solid #1a1a1a;
    font-size: 0.78rem; color: #444;
    text-align: center;
  }
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="site-header">
    <span class="logo-synti">SYNTI</span><span class="logo-meat">meat</span>
    <div class="header-sep"></div>
    <span class="badge-private">Acceso restringido</span>
  </div>

  <h1>Guía de Instalación — Impresora + QZ Tray</h1>
  <p class="subtitle">Impresora térmica 58 mm · Windows 10/11 · Impresión silenciosa desde el POS</p>

  <!-- ── PASO 1: Descargas ───────────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <span class="step-num">1</span>
      Descargar los archivos necesarios
    </div>

    <div class="download-grid">
      <div class="dl-card">
        <div class="dl-card__title">Driver de impresora 58 mm</div>
        <div class="dl-card__meta">58MM Thermal Printer Driver &amp; Tools · Windows</div>
        <a href="/soporte/driver-impresora" class="btn-dl">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Descargar Driver
        </a>
      </div>
      <div class="dl-card">
        <div class="dl-card__title">QZ Tray 2.2.6</div>
        <div class="dl-card__meta">qz-tray-2.2.6-x86_64.exe · Windows 64 bits</div>
        <a href="/soporte/qz-tray" class="btn-dl">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Descargar QZ Tray
        </a>
      </div>
    </div>

    <div class="alert alert-info">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>Ambos archivos deben instalarse en la <strong>PC de la caja</strong> donde está conectada la impresora.</span>
    </div>
  </div>

  <!-- ── PASO 2: Driver ─────────────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <span class="step-num">2</span>
      Instalar el driver de la impresora
    </div>

    <div class="steps">
      <div class="step">
        <span class="step__n">1</span>
        <div class="step__body">
          Descomprime el <code>.zip</code> descargado. Verás una carpeta con los drivers y herramientas.
        </div>
      </div>
      <div class="step">
        <span class="step__n">2</span>
        <div class="step__body">
          Conecta la impresora al PC por <strong>USB</strong> antes de instalar.
        </div>
      </div>
      <div class="step">
        <span class="step__n">3</span>
        <div class="step__body">
          Ejecuta el instalador del driver (busca <code>Setup.exe</code> o <code>Install.exe</code> dentro del zip). Sigue el asistente → <strong>Next → Install → Finish</strong>.
        </div>
      </div>
      <div class="step">
        <span class="step__n">4</span>
        <div class="step__body">
          Al terminar, ve a <strong>Panel de Control → Dispositivos e impresoras</strong>. Deberías ver la impresora listada (nombre similar a <code>POS-58</code> o <code>Thermal Printer</code>).
        </div>
      </div>
      <div class="step">
        <span class="step__n">5</span>
        <div class="step__body">
          <strong>Anota el nombre exacto</strong> tal como aparece — lo necesitarás en el Paso 5.
        </div>
      </div>
    </div>

    <div class="alert">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Si Windows no reconoce la impresora, instala primero el driver y luego conecta el USB.</span>
    </div>
  </div>

  <!-- ── PASO 3: QZ Tray ────────────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <span class="step-num">3</span>
      Instalar QZ Tray
    </div>

    <div class="steps">
      <div class="step">
        <span class="step__n">1</span>
        <div class="step__body">
          Ejecuta <code>qz-tray-2.2.6-x86_64.exe</code> como administrador (clic derecho → <em>Ejecutar como administrador</em>).
        </div>
      </div>
      <div class="step">
        <span class="step__n">2</span>
        <div class="step__body">
          Acepta la licencia y completa la instalación con los valores por defecto. <strong>No cambies el puerto</strong> (debe quedar en <code>8181</code>).
        </div>
      </div>
      <div class="step">
        <span class="step__n">3</span>
        <div class="step__body">
          Al finalizar, QZ Tray queda ejecutándose en la bandeja del sistema (ícono en la barra de tareas, cerca del reloj). Si no arrancó solo, búscalo en el menú Inicio y ábrelo.
        </div>
      </div>
      <div class="step">
        <span class="step__n">4</span>
        <div class="step__body">
          <strong>Configurar inicio automático:</strong> clic derecho en el ícono de QZ Tray → <em>Auto Start</em> → activar. Así arranca solo con Windows.
        </div>
      </div>
    </div>
  </div>

  <!-- ── PASO 4: Certificado ────────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <span class="step-num">4</span>
      Certificado de seguridad (ya está instalado en el servidor)
    </div>

    <div class="alert alert-info">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>No hay acción manual requerida. El certificado digital ya está configurado en el servidor de SYNTImeat. QZ Tray lo descargará automáticamente al conectarse.</span>
    </div>

    <p style="font-size:0.85rem;color:#666;margin-top:0.5rem;">
      Si QZ Tray muestra un diálogo de <em>"Allow site"</em> la primera vez que usas el POS, haz clic en <strong>Allow</strong> y marca <em>Remember this decision</em>.
    </p>
  </div>

  <!-- ── PASO 5: Configurar nombre en SYNTImeat ─────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <span class="step-num">5</span>
      Configurar el nombre de la impresora en SYNTImeat
    </div>

    <div class="steps">
      <div class="step">
        <span class="step__n">1</span>
        <div class="step__body">
          En SYNTImeat, ve a
          <span class="config-path">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            Configuración → Hardware
          </span>
        </div>
      </div>
      <div class="step">
        <span class="step__n">2</span>
        <div class="step__body">
          En el campo <strong>"Nombre de impresora"</strong>, escribe el nombre exacto tal como aparece en <em>Dispositivos e impresoras</em> de Windows (sensible a mayúsculas y espacios).
        </div>
      </div>
      <div class="step">
        <span class="step__n">3</span>
        <div class="step__body">
          Guarda. A partir de ese momento, el botón <strong>Imprimir</strong> en el POS enviará directo a esa impresora sin diálogos.
        </div>
      </div>
    </div>

    <div class="alert">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>El nombre de impresora debe coincidir <em>exactamente</em>. Usa la herramienta de diagnóstico (Paso 6) para ver el nombre que QZ Tray detecta.</span>
    </div>
  </div>

  <!-- ── PASO 6: Test ────────────────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <span class="step-num">6</span>
      Verificar que todo funciona
    </div>

    <p style="font-size:0.875rem;color:#aaa;margin-bottom:1rem;">
      Usa la herramienta de diagnóstico para verificar la conexión con QZ Tray y hacer una impresión de prueba:
    </p>

    <div class="test-card">
      <div class="test-card__text">
        <h3>Herramienta de diagnóstico QZ Tray</h3>
        <p>Verifica conexión, detecta impresoras y prueba impresión silenciosa</p>
      </div>
      <a href="/qz-test" target="_blank" class="btn-test">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Abrir diagnóstico
      </a>
    </div>

    <div class="steps" style="margin-top:1rem;">
      <div class="step">
        <span class="step__n">1</span>
        <div class="step__body">
          Haz clic en <strong>Test Conexión</strong>. Debe mostrar <code style="color:#4ade80">✓ CONECTADO a QZ Tray</code> y listar las impresoras detectadas.
        </div>
      </div>
      <div class="step">
        <span class="step__n">2</span>
        <div class="step__body">
          Copia el nombre exacto de la impresora que aparece en el log (entre comillas en la línea <em>"Impresoras detectadas"</em>).
        </div>
      </div>
      <div class="step">
        <span class="step__n">3</span>
        <div class="step__body">
          Pégalo en el campo de nombre, haz clic en <strong>Test Imprimir</strong>. La impresora debe imprimir y cortar el papel.
        </div>
      </div>
      <div class="step">
        <span class="step__n">4</span>
        <div class="step__body">
          Si imprime correctamente, pega ese nombre en <span class="config-path">Configuración → Hardware</span> y guarda. ¡Listo!
        </div>
      </div>
    </div>
  </div>

  <!-- ── Solución de problemas ──────────────────────────────────────────────── -->
  <div class="section">
    <div class="section-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Solución de problemas comunes
    </div>

    <div class="steps">
      <div class="step">
        <span class="step__n" style="color:#f59e0b;border-color:#4a3000;background:#1a1200">?</span>
        <div class="step__body">
          <strong>Error "QZ Tray not running":</strong> QZ Tray no está en la bandeja. Búscalo en el menú Inicio y ábrelo. Verifica que el puerto <code>8181</code> no esté bloqueado por el firewall.
        </div>
      </div>
      <div class="step">
        <span class="step__n" style="color:#f59e0b;border-color:#4a3000;background:#1a1200">?</span>
        <div class="step__body">
          <strong>Error de certificado / firma:</strong> Verifica que el PC tenga acceso a internet y pueda llegar a <code>https://meat.synti.cloud</code>. El certificado se descarga en cada conexión.
        </div>
      </div>
      <div class="step">
        <span class="step__n" style="color:#f59e0b;border-color:#4a3000;background:#1a1200">?</span>
        <div class="step__body">
          <strong>Impresora no aparece en la lista:</strong> El driver no está instalado o la impresora no está conectada por USB. Revisa <em>Dispositivos e impresoras</em> en Windows.
        </div>
      </div>
      <div class="step">
        <span class="step__n" style="color:#f59e0b;border-color:#4a3000;background:#1a1200">?</span>
        <div class="step__body">
          <strong>Imprime pero no corta:</strong> El nombre de impresora en Configuración → Hardware no coincide exactamente con el detectado por QZ. Cópialo directamente del log de diagnóstico.
        </div>
      </div>
      <div class="step">
        <span class="step__n" style="color:#f59e0b;border-color:#4a3000;background:#1a1200">?</span>
        <div class="step__body">
          <strong>Windows Defender bloquea el instalador de QZ:</strong> Clic en <em>"Más información"</em> → <em>"Ejecutar de todas formas"</em>. QZ Tray es software legítimo, firmado.
        </div>
      </div>
    </div>
  </div>

  <footer>
    SYNTImeat — Documento interno · {{ now()->format('Y') }} · Solo para administradores
  </footer>

</div>
</body>
</html>
