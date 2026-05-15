<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive } from 'vue'
import axios from 'axios'
import { Printer, BarChart2, Package, Lightbulb, AlertTriangle, Check, FolderOpen } from 'lucide-vue-next'

// ─── Estado ───────────────────────────────────────────────────────────────────
const loadingSales = ref(false)
const loadingInv   = ref(false)

const salesResult = reactive({ imported: [], warnings: [], total: 0, error: '' })
const invResult   = reactive({ imported: [], warnings: [], total: 0, error: '' })

const salesFile = ref(null)
const invFile   = ref(null)

// ─── Importar inventario ──────────────────────────────────────────────────────
async function uploadInventory() {
    if (!invFile.value) return
    loadingInv.value = true
    invResult.imported = []
    invResult.warnings = []
    invResult.error    = ''

    const form = new FormData()
    form.append('file', invFile.value)

    try {
        const res = await axios.post(route('contingency.import-inventory'), form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        invResult.imported = res.data.imported
        invResult.warnings = res.data.warnings
        invResult.total    = res.data.total
    } catch (e) {
        invResult.error = e.response?.data?.error ?? 'Error al importar inventario.'
    } finally {
        loadingInv.value = false
    }
}

// ─── Importar ventas ──────────────────────────────────────────────────────────
async function uploadSales() {
    if (!salesFile.value) return
    loadingSales.value = true
    salesResult.imported = []
    salesResult.warnings = []
    salesResult.error    = ''

    const form = new FormData()
    form.append('file', salesFile.value)

    try {
        const res = await axios.post(route('contingency.import-sales'), form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        salesResult.imported = res.data.imported
        salesResult.warnings = res.data.warnings
        salesResult.total    = res.data.total
    } catch (e) {
        salesResult.error = e.response?.data?.error ?? 'Error al importar ventas.'
    } finally {
        loadingSales.value = false
    }
}

function onInvFile(e)   { invFile.value   = e.target.files[0] ?? null }
function onSalesFile(e) { salesFile.value = e.target.files[0] ?? null }

function fmtBs(n) {
    return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function fmtKg(n) {
    return Number(n ?? 0).toFixed(3) + ' kg'
}
</script>

<template>
    <AppLayout title="Plan de Contingencia">
        <div class="cont-wrap">

            <!-- ─── Encabezado ──────────────────────────────────────────────── -->
            <div class="page-header">
                <div class="page-title-row">
                    <span class="icon-lightning">⚡</span>
                    <h1 class="page-title">Plan de Contingencia</h1>
                </div>
                <p class="page-subtitle">
                    Protocolo para cortes eléctricos — sigue vendiendo sin sistema y recupera los datos al volver la luz.
                </p>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- SECCIÓN 1 — RECURSOS PARA EL APAGÓN                           -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <section>
                <h2 class="section-title">
                    <span class="badge-step">1</span>
                    Cuando se vaya la luz — Descarga estos recursos
                </h2>

                <div class="cards-row">

                    <!-- Hoja de papel -->
                    <div class="resource-card resource-card--paper">
                        <div class="resource-card__icon"><Printer :size="28" /></div>
                        <div class="resource-card__content">
                            <h3>Hoja de registro en papel</h3>
                            <p>
                                Imprime esta hoja antes de un apagón y tenla lista.
                                Contiene 20 filas con columnas: Hora, Producto, Cantidad, Precio Bs., Total Bs. y Pago.
                            </p>
                            <div class="instruction-tip">
                                <Lightbulb :size="13" class="tip-inline" /> Imprime al inicio de cada jornada o tenla en el cajón del mostrador.
                            </div>
                        </div>
                        <a :href="route('contingency.form')" class="btn-download" target="_blank" rel="noopener">
                            ↓ Descargar hoja de papel (PDF)
                        </a>
                    </div>

                    <!-- Plantilla ventas -->
                    <div class="resource-card resource-card--excel">
                        <div class="resource-card__icon"><BarChart2 :size="28" /></div>
                        <div class="resource-card__content">
                            <h3>Plantilla de ventas Excel</h3>
                            <p>
                                Rellena esta plantilla con las ventas anotadas en papel
                                <em>antes</em> de importar al sistema.
                                Columnas exactas para importación directa.
                            </p>
                            <div class="instruction-tip">
                                <Lightbulb :size="13" class="tip-inline" /> Necesitas: hora, product_id, quantity_value, input_type, price_bs, total_bs, payment_method.
                            </div>
                        </div>
                        <a :href="route('contingency.sales-template')" class="btn-download" target="_blank" rel="noopener">
                            ↓ Descargar plantilla ventas
                        </a>
                    </div>

                    <!-- Plantilla inventario -->
                    <div class="resource-card resource-card--inv">
                        <div class="resource-card__icon"><Package :size="28" /></div>
                        <div class="resource-card__content">
                            <h3>Plantilla de inventario Excel</h3>
                            <p>
                                Usa esta plantilla si recibiste mercancía durante el apagón.
                                Registra entradas con cantidad, merma y costo.
                            </p>
                            <div class="instruction-tip">
                                <Lightbulb :size="13" class="tip-inline" /> Necesitas: hour, product_id, quantity_kg, waste_kg, cost_per_kg_usd, supplier.
                            </div>
                        </div>
                        <a :href="route('contingency.inventory-template')" class="btn-download" target="_blank" rel="noopener">
                            ↓ Descargar plantilla inventario
                        </a>
                    </div>

                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- SECCIÓN 2 — IMPORTAR AL VOLVER LA LUZ                         -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <section>
                <h2 class="section-title">
                    <span class="badge-step">2</span>
                    Al volver la luz — Importa los registros
                </h2>

                <div class="alert-order">
                    <AlertTriangle :size="16" style="vertical-align:middle;margin-right:6px;flex-shrink:0;" /><strong>Orden importante:</strong>
                    Importa primero el inventario y luego las ventas.
                    Las ventas descuentan stock — si no hay stock previo el sistema puede quedar en negativo.
                </div>

                <div class="import-grid">

                    <!-- ── Upload Inventario ────────────────────────────────── -->
                    <div class="import-card">
                        <div class="import-card__header">
                            <span class="import-step">Paso A</span>
                            <h3>Importar entradas de inventario</h3>
                        </div>
                        <p class="import-desc">
                            Sube el Excel de inventario con las entradas registradas durante el apagón.
                        </p>
                        <div class="upload-zone">
                            <input id="inv-file" type="file" accept=".xlsx,.xls,.csv"
                                   class="file-input-hidden" @change="onInvFile" />
                            <label for="inv-file" class="file-label">
                                <span class="file-icon"><FolderOpen :size="22" /></span>
                                <span v-if="invFile">{{ invFile.name }}</span>
                                <span v-else>Seleccionar archivo Excel (.xlsx)</span>
                            </label>
                        </div>
                        <button class="btn-import btn-import--inv"
                                :disabled="!invFile || loadingInv"
                                @click="uploadInventory">
                            {{ loadingInv ? 'Importando…' : 'Importar Inventario' }}
                        </button>

                        <!-- Resultado inventario -->
                        <div v-if="invResult.error" class="import-error">
                            {{ invResult.error }}
                        </div>
                        <div v-if="invResult.warnings.length" class="import-warnings">
                            <p class="warn-title">Advertencias ({{ invResult.warnings.length }}):</p>
                            <ul>
                                <li v-for="(w, i) in invResult.warnings" :key="i">{{ w }}</li>
                            </ul>
                        </div>
                        <div v-if="invResult.imported.length" class="import-result">
                            <p class="result-title"><Check :size="14" class="result-check" />{{ invResult.total }} entradas importadas</p>
                            <div class="result-table-wrap">
                                <table class="result-table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th class="right">Cantidad</th>
                                            <th class="right">Merma</th>
                                            <th class="right">Neto</th>
                                            <th>Proveedor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(r, i) in invResult.imported" :key="i">
                                            <td>{{ r.producto }}</td>
                                            <td class="right">{{ fmtKg(r.cantidad) }}</td>
                                            <td class="right muted">{{ fmtKg(r.merma) }}</td>
                                            <td class="right">{{ fmtKg(r.neto) }}</td>
                                            <td class="muted">{{ r.proveedor || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ── Upload Ventas ─────────────────────────────────────── -->
                    <div class="import-card">
                        <div class="import-card__header">
                            <span class="import-step import-step--b">Paso B</span>
                            <h3>Importar ventas del apagón</h3>
                        </div>
                        <p class="import-desc">
                            Sube el Excel de ventas completado con los datos de la hoja de papel.
                            Cada fila crea una venta con status=paid y descuenta el stock.
                        </p>
                        <div class="upload-zone">
                            <input id="sales-file" type="file" accept=".xlsx,.xls,.csv"
                                   class="file-input-hidden" @change="onSalesFile" />
                            <label for="sales-file" class="file-label">
                                <span class="file-icon"><FolderOpen :size="22" /></span>
                                <span v-if="salesFile">{{ salesFile.name }}</span>
                                <span v-else>Seleccionar archivo Excel (.xlsx)</span>
                            </label>
                        </div>
                        <button class="btn-import btn-import--sales"
                                :disabled="!salesFile || loadingSales"
                                @click="uploadSales">
                            {{ loadingSales ? 'Importando…' : 'Importar Ventas' }}
                        </button>

                        <!-- Resultado ventas -->
                        <div v-if="salesResult.error" class="import-error">
                            {{ salesResult.error }}
                        </div>
                        <div v-if="salesResult.warnings.length" class="import-warnings">
                            <p class="warn-title">Advertencias ({{ salesResult.warnings.length }}):</p>
                            <ul>
                                <li v-for="(w, i) in salesResult.warnings" :key="i">{{ w }}</li>
                            </ul>
                        </div>
                        <div v-if="salesResult.imported.length" class="import-result">
                            <p class="result-title"><Check :size="14" class="result-check" />{{ salesResult.total }} ventas importadas</p>
                            <div class="result-table-wrap">
                                <table class="result-table">
                                    <thead>
                                        <tr>
                                            <th>Ticket</th>
                                            <th>Producto</th>
                                            <th class="right">Cantidad</th>
                                            <th class="right">Total Bs.</th>
                                            <th>Método</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(r, i) in salesResult.imported" :key="i">
                                            <td class="mono">{{ r.ticket }}</td>
                                            <td>{{ r.producto }}</td>
                                            <td class="right">{{ r.cantidad }} {{ r.input_type === 'weight' ? 'kg' : 'und' }}</td>
                                            <td class="right amount">{{ fmtBs(r.total_bs) }}</td>
                                            <td class="muted">{{ r.metodo_pago }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

        </div>
    </AppLayout>
</template>

<style scoped>
.cont-wrap {
    padding: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

/* ─── Header ───────────────────────────────────────────────────────────────── */
.page-header { display: flex; flex-direction: column; gap: 0.35rem; }
.page-title-row { display: flex; align-items: center; gap: 0.6rem; }
.icon-lightning { font-size: 1.6rem; }
.page-title { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; }
.page-subtitle { color: var(--text-muted); font-size: 0.92rem; margin: 0; }

/* ─── Secciones ─────────────────────────────────────────────────────────────── */
section { display: flex; flex-direction: column; gap: 1rem; }
.section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0;
}
.badge-step {
    background: var(--brand);
    color: #fff;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}

/* ─── Resource cards ────────────────────────────────────────────────────────── */
.cards-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}
.resource-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.resource-card--paper { border-left: 3px solid #6366f1; }
.resource-card--excel { border-left: 3px solid #16a34a; }
.resource-card--inv   { border-left: 3px solid #d97706; }
.resource-card__icon  { font-size: 2rem; }
.resource-card__content { flex: 1; }
.resource-card__content h3 { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.4rem; }
.resource-card__content p  { font-size: 0.84rem; color: var(--text-muted); margin: 0; line-height: 1.5; }
.instruction-tip {
    margin-top: 0.6rem;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.45rem 0.65rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    display: flex;
    align-items: flex-start;
    gap: 6px;
}
.tip-inline { flex-shrink: 0; margin-top: 1px; color: var(--brand); }
.btn-download {
    display: block;
    background: var(--bg-base);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 0.55rem 1rem;
    border-radius: 9px;
    text-align: center;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    transition: border-color 0.15s, color 0.15s;
}
.btn-download:hover { border-color: var(--brand); color: var(--brand); }

/* ─── Alert orden ───────────────────────────────────────────────────────────── */
.alert-order {
    background: rgba(245,158,11,0.1);
    border: 1px solid rgba(245,158,11,0.3);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 0.86rem;
    color: #d97706;
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

/* ─── Import grid ───────────────────────────────────────────────────────────── */
.import-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
    gap: 1.25rem;
}
.import-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.import-card__header { display: flex; align-items: center; gap: 0.65rem; }
.import-card__header h3 { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.import-step {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 99px;
    background: rgba(99,102,241,0.14);
    color: #6366f1;
    white-space: nowrap;
}
.import-step--b {
    background: rgba(22,163,74,0.12);
    color: #16a34a;
}
.import-desc { font-size: 0.84rem; color: var(--text-muted); margin: 0; line-height: 1.5; }

/* ─── Upload zone ───────────────────────────────────────────────────────────── */
.upload-zone { }
.file-input-hidden { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.file-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--bg-base);
    border: 1.5px dashed var(--border);
    border-radius: 10px;
    padding: 0.7rem 1rem;
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--text-muted);
    transition: border-color 0.15s;
}
.file-label:hover { border-color: var(--brand); color: var(--text-primary); }
.file-icon { font-size: 1.1rem; }

/* ─── Botones importar ──────────────────────────────────────────────────────── */
.btn-import {
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: opacity 0.15s;
    align-self: flex-start;
}
.btn-import:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-import--inv   { background: #d97706; color: #fff; }
.btn-import--sales { background: var(--brand); color: #fff; }

/* ─── Resultados ─────────────────────────────────────────────────────────────── */
.import-error {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 8px;
    padding: 0.55rem 0.85rem;
    font-size: 0.84rem;
    color: #ef4444;
}
.import-warnings {
    background: rgba(245,158,11,0.08);
    border: 1px solid rgba(245,158,11,0.25);
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    font-size: 0.8rem;
    color: #d97706;
}
.warn-title { font-weight: 700; margin: 0 0 0.35rem; }
.import-warnings ul { margin: 0; padding-left: 1.1rem; }
.import-warnings li { margin-bottom: 0.2rem; }
.import-result { }
.result-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #16a34a;
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 0 0 0.5rem;
}
.result-table-wrap { overflow-x: auto; }
.result-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.result-table th {
    padding: 0.35rem 0.6rem;
    text-align: left;
    font-size: 0.68rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.result-table td {
    padding: 0.4rem 0.6rem;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary);
}
.result-table tr:last-child td { border-bottom: none; }
.right  { text-align: right !important; }
.muted  { color: var(--text-muted) !important; }
.mono   { font-family: monospace; color: var(--brand); font-size: 0.8rem; }
.amount { font-weight: 700; }

/* ─── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .import-grid { grid-template-columns: 1fr; }
    .cards-row   { grid-template-columns: 1fr; }
}
</style>
