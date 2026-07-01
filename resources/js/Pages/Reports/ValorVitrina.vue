<script setup>
import { ref, onMounted, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const data     = ref(null)
const loading  = ref(true)
const error    = ref('')
const expanded = ref({})

async function cargar() {
    loading.value = true
    error.value = ''
    try {
        const r = await axios.get('/reportes/valor-vitrina/data', {
            withCredentials: true,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        data.value = r.data
    } catch (e) {
        error.value = (e.response?.data?.message ?? e.response?.statusText ?? e.message ?? 'Error cargando datos.')
    } finally {
        loading.value = false
    }
}

function toggle(cat) {
    expanded.value[cat] = !expanded.value[cat]
}

function fmtUsd(v) {
    return '$' + Number(v || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtKg(v) {
    return Number(v || 0).toLocaleString('es-VE', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + ' kg'
}

function utilColor(v) {
    return Number(v) >= 0 ? 'val-green' : 'val-red'
}

onMounted(cargar)
</script>

<template>
    <AppLayout title="Valor en Vitrina">
        <div class="vv-wrap">
            <div class="vv-header">
                <h1 class="vv-title">Valor del Inventario en Vitrina</h1>
                <button class="vv-refresh" @click="cargar">↻ Actualizar</button>
            </div>

            <div v-if="loading" class="vv-loading">Cargando...</div>
            <div v-else-if="error" class="vv-error">{{ error }}</div>

            <template v-else-if="data">
                <!-- Totales macro -->
                <div class="vv-macro">
                    <div class="vv-macro-card">
                        <span class="vv-macro-lbl">Total Invertido</span>
                        <span class="vv-macro-val">{{ fmtUsd(data.total_invertido) }}</span>
                    </div>
                    <div class="vv-macro-card">
                        <span class="vv-macro-lbl">Venta Potencial</span>
                        <span class="vv-macro-val">{{ fmtUsd(data.total_venta) }}</span>
                    </div>
                    <div class="vv-macro-card" :class="data.utilidad_potencial >= 0 ? 'vv-macro-card--pos' : 'vv-macro-card--neg'">
                        <span class="vv-macro-lbl">Utilidad Potencial</span>
                        <span class="vv-macro-val">{{ fmtUsd(data.utilidad_potencial) }}</span>
                    </div>
                </div>

                <!-- Por categoría -->
                <div class="vv-cats">
                    <div v-for="cat in data.categorias" :key="cat.categoria" class="vv-cat">
                        <!-- Header categoría -->
                        <div class="vv-cat-header" @click="toggle(cat.categoria)">
                            <div class="vv-cat-info">
                                <span class="vv-cat-name">{{ cat.categoria }}</span>
                                <span class="vv-cat-kg">{{ fmtKg(cat.kg_total) }}</span>
                            </div>
                            <div class="vv-cat-nums">
                                <div class="vv-cat-num">
                                    <span class="vv-cat-num-lbl">Invertido</span>
                                    <span class="vv-cat-num-val">{{ fmtUsd(cat.total_invertido) }}</span>
                                </div>
                                <div class="vv-cat-num">
                                    <span class="vv-cat-num-lbl">Venta potencial</span>
                                    <span class="vv-cat-num-val">{{ fmtUsd(cat.total_venta) }}</span>
                                </div>
                                <div class="vv-cat-num">
                                    <span class="vv-cat-num-lbl">Utilidad</span>
                                    <span class="vv-cat-num-val" :class="utilColor(cat.utilidad_potencial)">{{ fmtUsd(cat.utilidad_potencial) }}</span>
                                </div>
                                <span class="vv-cat-arrow">{{ expanded[cat.categoria] ? '▲' : '▼' }}</span>
                            </div>
                        </div>

                        <!-- Detalle productos -->
                        <div v-if="expanded[cat.categoria]" class="vv-table-wrap">
                            <table class="vv-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Kg disponible</th>
                                        <th>Costo/kg</th>
                                        <th>Precio venta/kg</th>
                                        <th>Total invertido</th>
                                        <th>Venta potencial</th>
                                        <th>Utilidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in cat.productos" :key="p.product_id">
                                        <td>{{ p.producto }}</td>
                                        <td>{{ fmtKg(p.kg_disponible) }}</td>
                                        <td>{{ p.costo_kg ? fmtUsd(p.costo_kg) : '—' }}</td>
                                        <td>{{ p.precio_venta_kg ? fmtUsd(p.precio_venta_kg) : '—' }}</td>
                                        <td>{{ fmtUsd(p.total_invertido) }}</td>
                                        <td>{{ fmtUsd(p.total_venta_potencial) }}</td>
                                        <td :class="utilColor(p.utilidad_potencial)">{{ fmtUsd(p.utilidad_potencial) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.vv-wrap { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
.vv-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.vv-title { font-size: 1.5rem; font-weight: 600; color: var(--color-text-primary); }
.vv-refresh { padding: 0.5rem 1rem; background: var(--brand); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; }
.vv-loading { text-align: center; padding: 3rem; color: var(--color-text-secondary); font-size: 1.1rem; }
.vv-error { color: var(--color-text-error); padding: 1rem; }

.vv-macro { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
.vv-macro-card { background: var(--bg-base); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
.vv-macro-card--pos { border-color: var(--color-border-success); }
.vv-macro-card--neg { border-color: #ef4444; }
.vv-macro-lbl { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-secondary); }
.vv-macro-val { font-size: 1.8rem; font-weight: 600; color: var(--color-text-primary); }

.vv-cats { display: flex; flex-direction: column; gap: 0.75rem; }
.vv-cat { background: var(--bg-base); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.vv-cat-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; cursor: pointer; gap: 1rem; }
.vv-cat-header:hover { background: rgba(255,255,255,0.04); }
.vv-cat-info { display: flex; align-items: center; gap: 1rem; min-width: 160px; }
.vv-cat-name { font-size: 1.1rem; font-weight: 600; color: var(--color-text-primary); }
.vv-cat-kg { font-size: 0.85rem; color: var(--color-text-secondary); }
.vv-cat-nums { display: flex; align-items: center; gap: 2rem; }
.vv-cat-num { display: flex; flex-direction: column; align-items: flex-end; }
.vv-cat-num-lbl { font-size: 0.72rem; color: var(--color-text-secondary); text-transform: uppercase; }
.vv-cat-num-val { font-size: 1rem; font-weight: 600; color: var(--color-text-primary); }
.vv-cat-arrow { font-size: 0.8rem; color: var(--color-text-secondary); }

.vv-table-wrap { overflow-x: auto; border-top: 1px solid var(--border); }
.vv-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.vv-table th { padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--color-text-secondary); background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border); }
.vv-table th:first-child { text-align: left; }
.vv-table td { padding: 0.75rem 1rem; text-align: right; color: var(--color-text-primary); border-bottom: 1px solid rgba(255,255,255,0.05); }
.vv-table td:first-child { text-align: left; font-weight: 500; }
.vv-table tr:last-child td { border-bottom: none; }
.val-green { color: #4ade80; }
.val-red { color: #f87171; }

@media (max-width: 768px) {
    .vv-macro { grid-template-columns: 1fr; }
    .vv-cat-nums { gap: 1rem; }
    .vv-cat-num-val { font-size: 0.9rem; }
}
</style>
