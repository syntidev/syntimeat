<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import HelpModal      from '@/Components/HelpModal.vue'
import { ref }        from 'vue'
import { useForm }    from '@inertiajs/vue3'

const props = defineProps({
    business:  { type: Object, default: () => ({}) },
    products:  { type: Array,  default: () => [] },
    todayRate: { type: Number, default: 1 },
})

const hwForm = useForm({
    printer_name: props.business?.settings?.printer_name ?? '',
})

function submitHardware() {
    hwForm.post(route('settings.hardware.update'), { preserveScroll: true })
}

// ─── Scanner test ────────────────────────────────────────────────────────────
const testCode      = ref('')
const testResult    = ref(null)
const SCANNER_BURST = 50

let _lastTime  = 0
let _buffer    = ''

function onTestKeydown(e) {
    const now  = Date.now()
    const diff = _lastTime ? now - _lastTime : 9999

    if (e.key === 'Enter') {
        const buf = _buffer
        _buffer = ''; _lastTime = 0
        if (buf.length === 13 && /^\d{13}$/.test(buf)) {
            e.preventDefault()
            analyzeCode(buf)
        }
        return
    }

    if (e.key.length === 1 && /\d/.test(e.key)) {
        _buffer   = diff < SCANNER_BURST ? _buffer + e.key : e.key
        _lastTime = now
    } else {
        _buffer = ''; _lastTime = 0
    }
}

function analyzeCode(code) {
    testCode.value = code
    if (code[0] === '2') {
        const pluRaw  = code.slice(2, 7)
        const priceRaw = code.slice(7, 12)
        const plu      = parseInt(pluRaw, 10)
        const priceBs  = priceRaw.endsWith('00') ? parseInt(priceRaw, 10) / 100 : parseInt(priceRaw, 10) / 10

        const product = props.products.find(p =>
            (p.barcode && parseInt(p.barcode, 10) === plu) ||
            (p.sku     && parseInt(p.sku,     10) === plu)
        ) ?? null

        const priceBsKg = product?.sale_mode === 'weight'
            ? parseFloat(product.price_per_kg_usd || 0) * props.todayRate
            : 0
        const kg = priceBsKg > 0
            ? Math.round((priceBs / priceBsKg) * 1000) / 1000
            : null

        testResult.value = {
            type:        'EAN-13 Precio (balanza)',
            plu,
            priceBs,
            check:       code[12],
            productName: product?.name ?? null,
            kg,
            priceBsKg:   priceBsKg || null,
        }
    } else {
        testResult.value = { type: 'EAN-13 Estándar (no precio)', plu: null, priceBs: null, check: code[12], productName: null, kg: null }
    }
}

function clearTest() {
    testCode.value  = ''
    testResult.value = null
    _buffer = ''; _lastTime = 0
}

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Scanner de código de barras',
        body:  'El scanner se conecta por USB y se comporta como un teclado. No requiere drivers. Al escanear un producto, el código llega al sistema en menos de 50 milisegundos — más rápido de lo que cualquier persona puede escribir.',
        tip:   'El campo de búsqueda del POS detecta automáticamente si el input viene de un scanner o de una persona escribiendo.',
    },
    {
        title: 'Formato EAN-13 de balanza (prefijo 2)',
        body:  'Las básculas modernas (Mettler Toledo, Revelo, etc.) generan códigos EAN-13 que empiezan con "2". Contienen el PLU del producto (dígitos 2–6) y el precio en Bs. (dígitos 7–11). El cajero solo escanea: el sistema detecta el producto y el monto automáticamente.',
        tip:   'Los códigos de barras de empaque que NO empiezan con "2" harán una búsqueda normal por nombre en el POS.',
    },
    {
        title: 'Impresora térmica',
        body:  'Las impresoras térmicas de 57mm o 80mm no requieren drivers. Basta con configurar el papel correcto en Windows/macOS e imprimir desde el POS. El sistema genera el ticket optimizado para papel angosto.',
        tip:   'Si tu impresora imprime con márgenes incorrectos, verifica que el ancho de papel en las preferencias de la impresora coincida con el papel instalado.',
    },
]

const helpFaqs = [
    {
        q: '¿Qué scanner debo comprar?',
        a: 'Cualquier scanner HID (que se conecte por USB y simule teclado) funciona. Marcas comunes: Honeywell, Zebra, Datalogic. Verifica que lea EAN-13.',
    },
    {
        q: '¿Puedo probar el scanner sin productos configurados?',
        a: 'Sí. Usa el campo de prueba en esta página. Escanea cualquier código y verás el análisis: tipo, PLU y precio extraído (si aplica).',
    },
    {
        q: '¿Funciona con balanzas que generan códigos EAN-13?',
        a: 'Sí. El sistema detecta automáticamente el prefijo "2" y extrae el precio en Bs. y el PLU para buscar el producto. Los precios vienen de la balanza, no del sistema.',
    },
    {
        q: '¿La impresora necesita internet?',
        a: 'No. La impresión es local: el sistema abre una ventana de impresión del navegador y el sistema operativo se encarga del resto. Solo necesitas que la impresora esté configurada en tu computadora.',
    },
]
</script>

<template>
    <SettingsLayout>
        <div class="hw-page">

            <!-- Header -->
            <div class="hw-head">
                <div class="hw-head-left">
                    <div class="hw-title-row">
                        <h2 class="hw-title">Hardware</h2>
                        <span class="badge-wip">En desarrollo</span>
                    </div>
                    <p class="hw-sub">Scanner de código de barras e impresora térmica</p>
                </div>
                <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- Sección Scanner ───────────────────────────────────────────── -->
            <section class="hw-section">
                <div class="hw-section-head">
                    <svg class="hw-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2v16H3V4zm4 0h1v16H7V4zm3 0h2v16h-2V4zm4 0h1v16h-1V4zm3 0h1v16h-1V4zm3 0h1v16h-1V4z"/>
                    </svg>
                    <div>
                        <h3 class="hw-section-title">Scanner de código de barras</h3>
                        <p class="hw-section-sub">Detección automática HID — sin drivers, conecta y usa</p>
                    </div>
                </div>

                <!-- Formato EAN-13 -->
                <div class="info-card">
                    <p class="info-card__label">Formato EAN-13 de balanza</p>
                    <div class="ean-diagram">
                        <div class="ean-seg ean-seg--prefix">
                            <span class="ean-digit">2</span>
                            <span class="ean-seg-lbl">Prefijo</span>
                        </div>
                        <div class="ean-seg ean-seg--plu">
                            <span class="ean-digit">P P P P P</span>
                            <span class="ean-seg-lbl">PLU / SKU</span>
                        </div>
                        <div class="ean-seg ean-seg--price">
                            <span class="ean-digit">B B B B B</span>
                            <span class="ean-seg-lbl">Precio Bs. (÷100)</span>
                        </div>
                        <div class="ean-seg ean-seg--check">
                            <span class="ean-digit">C</span>
                            <span class="ean-seg-lbl">Verificador</span>
                        </div>
                    </div>
                    <p class="info-card__example">
                        Ej: <code>2001230125034</code> → PLU 123 · Bs. 12,50
                    </p>
                </div>

                <!-- Campo de prueba -->
                <div class="test-block">
                    <p class="test-label">Campo de prueba</p>
                    <p class="test-hint">Haz clic en el campo y escanea un código EAN-13 con tu scanner.</p>
                    <div class="test-input-row">
                        <input
                            v-model="testCode"
                            class="test-input"
                            placeholder="Escanea aquí…"
                            autocomplete="off"
                            @keydown="onTestKeydown"
                            @input="testResult = null"
                        />
                        <button v-if="testCode" class="test-clear" @click="clearTest">Limpiar</button>
                    </div>

                    <Transition name="test-result">
                        <div v-if="testResult" class="test-result" :class="testResult.plu !== null ? 'test-result--ok' : 'test-result--std'">
                            <div class="tr-row">
                                <span class="tr-lbl">Tipo</span>
                                <span class="tr-val">{{ testResult.type }}</span>
                            </div>
                            <template v-if="testResult.plu !== null">
                                <div class="tr-row">
                                    <span class="tr-lbl">PLU / SKU</span>
                                    <span class="tr-val tr-val--accent">{{ testResult.plu }}</span>
                                </div>
                                <div class="tr-row">
                                    <span class="tr-lbl">Precio extraído</span>
                                    <span class="tr-val tr-val--accent">Bs. {{ testResult.priceBs?.toFixed(2) }}</span>
                                </div>
                                <div class="tr-row">
                                    <span class="tr-lbl">Producto</span>
                                    <span class="tr-val" :class="testResult.productName ? 'tr-val--accent' : 'tr-val--warn'">
                                        {{ testResult.productName ?? 'No encontrado' }}
                                    </span>
                                </div>
                                <template v-if="testResult.productName && testResult.kg !== null">
                                    <div class="tr-row">
                                        <span class="tr-lbl">Kg calculado</span>
                                        <span class="tr-val tr-val--accent">{{ testResult.kg }} kg</span>
                                    </div>
                                    <div class="tr-row">
                                        <span class="tr-lbl">Verificación Bs</span>
                                        <span class="tr-val tr-val--accent">
                                            Bs. {{ testResult.priceBs?.toFixed(2) }} ÷ {{ testResult.priceBsKg?.toFixed(2) }} Bs/kg
                                        </span>
                                    </div>
                                </template>
                            </template>
                            <div class="tr-row">
                                <span class="tr-lbl">Dígito verificador</span>
                                <span class="tr-val">{{ testResult.check }}</span>
                            </div>
                        </div>
                    </Transition>
                </div>
            </section>

            <!-- Sección Impresora ─────────────────────────────────────────── -->
            <section class="hw-section">
                <div class="hw-section-head">
                    <svg class="hw-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <polyline stroke-linecap="round" stroke-linejoin="round" points="6 9 6 2 18 2 18 9"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                        <rect stroke-linecap="round" stroke-linejoin="round" x="6" y="14" width="12" height="8"/>
                    </svg>
                    <div>
                        <h3 class="hw-section-title">Impresora térmica</h3>
                        <p class="hw-section-sub">Impresión directa desde el navegador — sin drivers adicionales</p>
                    </div>
                </div>

                <!-- Campo: nombre de impresora para QZ Tray -->
                <div class="hw-printer-form">
                    <p class="info-steps__title">Nombre de impresora (impresión silenciosa)</p>
                    <p class="hw-printer-hint">
                        Escribe el nombre exacto como aparece en
                        <em>Windows → Impresoras y escáneres</em>.
                        Ejemplo: <code>POS-58-ROCCIA</code>
                    </p>
                    <form @submit.prevent="submitHardware" class="hw-printer-row">
                        <input
                            v-model="hwForm.printer_name"
                            type="text"
                            class="hw-printer-input"
                            placeholder="Nombre exacto de la impresora"
                            maxlength="100"
                        />
                        <button type="submit" class="btn btn-primary" :disabled="hwForm.processing">
                            {{ hwForm.processing ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </form>
                    <p v-if="hwForm.wasSuccessful" class="hw-printer-ok">Guardado correctamente.</p>
                </div>

                <!-- Instrucciones QZ Tray -->
                <div class="info-card hw-qz-card">
                    <p class="info-card__label">Impresión silenciosa con QZ Tray</p>
                    <p class="hw-printer-hint" style="margin-bottom:0.5rem">
                        QZ Tray envía el ticket directo a la impresora sin abrir el diálogo de Chrome.
                        Solo se instala una vez en la PC de la caja.
                    </p>
                    <ol class="info-steps__list" style="margin-left:1.25rem">
                        <li>Descarga e instala QZ Tray desde <strong>https://qz.io/download</strong></li>
                        <li>Abre QZ Tray — queda en la bandeja del sistema.</li>
                        <li>Escribe el nombre de la impresora en el campo de arriba y guarda.</li>
                        <li>Al imprimir desde el POS, el ticket saldrá directamente a la impresora.</li>
                    </ol>
                    <p class="info-card__example">
                        Si QZ Tray no está instalado o activo, el sistema usa el diálogo de Chrome como respaldo.
                    </p>
                </div>

                <div class="info-steps">
                    <p class="info-steps__title">Cómo configurar tu impresora térmica</p>
                    <ol class="info-steps__list">
                        <li>Conecta la impresora por USB y enciéndela.</li>
                        <li>En Windows: ve a <em>Impresoras y escáneres</em> → configura el papel como 80mm × continuo.</li>
                        <li>En el POS, al finalizar una venta, usa el botón <strong>Imprimir</strong> en el modal de confirmación.</li>
                        <li>Selecciona tu impresora térmica y confirma.</li>
                    </ol>
                </div>
            </section>

        </div>

        <!-- Panel de ayuda ───────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Hardware — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </SettingsLayout>
</template>

<style scoped>
.hw-page    { display: flex; flex-direction: column; gap: 1.5rem; }

/* Header */
.hw-head    { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.hw-head-left { display: flex; flex-direction: column; gap: 2px; }
.hw-title-row { display: flex; align-items: center; gap: 0.625rem; }
.hw-title   { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.hw-sub     { font-size: .8rem; color: var(--text-muted); }

.badge-wip  {
    font-size: .65rem; font-weight: 700; letter-spacing: .04em;
    padding: 2px 8px; border-radius: 20px;
    background: #78350f22; color: #f59e0b;
    border: 1px solid #f59e0b44;
}

.help-btn {
    border-radius: 50%; width: 44px; height: 44px; padding: 0; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700;
    border: 1.5px solid var(--border);
    background: none; color: var(--text-muted); cursor: pointer;
    transition: background .15s, color .15s, border-color .15s;
}
.help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* Secciones */
.hw-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.hw-section-head {
    display: flex; align-items: center; gap: .875rem;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.hw-section-icon { width: 20px; height: 20px; color: var(--brand); flex-shrink: 0; }
.hw-section-title { font-size: .9375rem; font-weight: 700; color: var(--text-primary); }
.hw-section-sub   { font-size: .75rem; color: var(--text-muted); margin-top: 1px; }

/* Info card */
.info-card {
    margin: 1.1rem 1.25rem;
    background: var(--hover);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .875rem 1rem;
}
.info-card__label { font-size: .75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .625rem; }
.info-card__example { font-size: .78rem; color: var(--text-muted); margin-top: .625rem; }
.info-card__example code { font-family: 'Courier New', monospace; color: var(--brand); font-size: .8rem; }

/* EAN diagram */
.ean-diagram { display: flex; gap: 3px; }
.ean-seg     { display: flex; flex-direction: column; align-items: center; border-radius: 5px; padding: .35rem .5rem; }
.ean-seg--prefix { background: #1e293b; }
.ean-seg--plu    { background: color-mix(in srgb, var(--brand) 15%, transparent); flex: 1; }
.ean-seg--price  { background: #16a34a22; flex: 1; }
.ean-seg--check  { background: #1e293b; }
.ean-digit { font-family: 'Courier New', monospace; font-size: .78rem; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
.ean-seg-lbl { font-size: .63rem; color: var(--text-muted); margin-top: 3px; text-align: center; }

/* Test block */
.test-block { padding: 0 1.25rem 1.25rem; }
.test-label { font-size: .78rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 2px; }
.test-hint  { font-size: .75rem; color: var(--text-muted); margin-bottom: .5rem; }
.test-input-row { display: flex; gap: .5rem; align-items: center; }
.test-input {
    flex: 1; background: var(--hover); border: 1px solid var(--border);
    color: var(--text-primary); border-radius: 8px;
    padding: .5rem .75rem; font-size: .875rem; outline: none; font-family: inherit;
}
.test-input:focus { border-color: var(--brand); }
.test-clear {
    font-size: 0.875rem; color: var(--text-muted); background: none;
    border: 1px solid var(--border); border-radius: 6px; padding: .35rem .625rem;
    min-height: 44px; min-width: 44px; cursor: pointer;
}
.test-clear:hover { color: var(--text-primary); border-color: var(--text-secondary); }

.test-result {
    margin-top: .625rem; border-radius: 8px; padding: .75rem 1rem;
    display: flex; flex-direction: column; gap: .35rem;
    border: 1px solid;
}
.test-result--ok  { background: #16a34a14; border-color: #16a34a44; }
.test-result--std { background: var(--hover); border-color: var(--border); }
.tr-row   { display: flex; justify-content: space-between; align-items: center; }
.tr-lbl   { font-size: .75rem; color: var(--text-muted); }
.tr-val   { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.tr-val--accent { color: #16a34a; }
.tr-val--warn   { color: var(--red, #ef4444); }

.test-result-enter-active, .test-result-leave-active { transition: opacity .2s, transform .2s; }
.test-result-enter-from,   .test-result-leave-to     { opacity: 0; transform: translateY(-4px); }

/* Formulario printer_name */
.hw-printer-form { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.hw-printer-hint { font-size: .78rem; color: var(--text-muted); margin-bottom: .5rem; line-height: 1.5; }
.hw-printer-hint code { font-family: 'Courier New', monospace; color: var(--brand); font-size: .8rem; }
.hw-printer-hint em  { font-style: normal; color: var(--text-primary); }
.hw-printer-row  { display: flex; gap: .5rem; margin-top: .5rem; }
.hw-printer-input {
    flex: 1; background: var(--hover); border: 1px solid var(--border);
    color: var(--text-primary); border-radius: 8px;
    padding: .5rem .75rem; font-size: .875rem; outline: none; font-family: inherit;
}
.hw-printer-input:focus { border-color: var(--brand); }
.hw-printer-ok { font-size: .75rem; color: #16a34a; margin-top: .35rem; }

/* QZ Tray card */
.hw-qz-card { margin: 1rem 1.25rem; border-bottom: 1px solid var(--border); }

/* Info steps */
.info-steps { padding: 0 1.25rem 1.25rem; }
.info-steps__title { font-size: .78rem; font-weight: 700; color: var(--text-secondary); margin-bottom: .5rem; }
.info-steps__list  {
    padding-left: 1.25rem;
    display: flex; flex-direction: column; gap: .35rem;
    font-size: .8125rem; color: var(--text-secondary); line-height: 1.5;
}
.info-steps__list em { font-style: normal; color: var(--text-primary); }

/* ─── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
    .hw-page { gap: 1rem; }
    /* Sections: padding reducido */
    .hw-section-head { padding: 1rem; }
    .info-card { margin: 1rem; }
    /* Test block: ajustar */
    .test-block { padding: 0 1rem 1rem; }
    /* Info steps: ajustar */
    .info-steps { padding: 0 1rem 1rem; }
    /* Header apila en tablet chico */
    .hw-head { flex-wrap: wrap; gap: 0.75rem; }
}

@media (max-width: 640px) {
    /* hw-head apila */
    .hw-head { flex-direction: column; align-items: flex-start; }
    /* EAN diagram: scroll horizontal */
    .ean-diagram { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    /* Test input row: apila */
    .test-input-row { flex-direction: column; }
    .test-input { width: 100%; font-size: 1rem; }
    .test-clear { width: 100%; min-height: 44px; justify-content: center; }
}
</style>
