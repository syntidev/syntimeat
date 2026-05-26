<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
    DollarSign, Package, ShoppingCart, BarChart2,
    RefreshCw, Moon, Truck, ChevronLeft, ChevronRight,
    CheckCircle, Menu, X,
} from '@lucide/vue'

// ─── Flujos ───────────────────────────────────────────────────────────────────
const flujos = [
    {
        id: 'flujo-1',
        titulo: 'Inicio del día',
        subtitulo: 'Abre tu caja antes de vender',
        icon: DollarSign,
        pasos: [
            {
                titulo: 'Abre tu caja',
                cuerpo: [
                    'Ve a <strong>CAJA</strong> en el menú lateral.',
                    'Presiona el botón <strong>ABRIR CAJA</strong>.',
                    'Escribe el dinero que tienes en el cajón en este momento (ejemplo: Bs. 5.000).',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'Ya puedes vender. El sistema lleva la cuenta desde ese momento.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-2',
        titulo: 'Entrada de mercancía a Bóveda',
        subtitulo: 'Cuando llega una canal (res, cerdo, pollo)',
        icon: Truck,
        pasos: [
            {
                titulo: 'Registra la canal en Bóveda',
                cuerpo: [
                    'Ve a <strong>BÓVEDA</strong> en el menú.',
                    'Presiona <strong>+ NUEVA ENTRADA</strong>.',
                    'Selecciona el tipo de canal (Res - Medio Canal, Cerdo - Canal, Pollo - Entero Congelado, etc.).',
                    'Escribe los kilos que llegaron y el costo en dólares.',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'La canal queda registrada con su costo. El sistema calculará tu utilidad cuando empieces a vender.',
                nota: null,
            },
            {
                titulo: 'Súrtela a la tienda',
                cuerpo: [
                    'En la lista de entradas de Bóveda, busca la canal que acabas de registrar.',
                    'Presiona el botón <strong>SURTIR</strong>.',
                    'Escribe cuántos kilos vas a pasar a la tienda hoy.',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'Solo lo que surtes llega al inventario de vitrina para venderse en el POS.',
                nota: null,
            },
            {
                titulo: 'Regístrala en Fábrica (si es Res o Cerdo)',
                cuerpo: [
                    'Ve a <strong>FÁBRICA</strong> en el menú.',
                    'Verás la canal pendiente de despiece.',
                    'Escribe cuántos kilos salieron de cada corte (lomito, paleta, costilla, etc.).',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'Fábrica registra el despiece para que el sistema sepa qué productos salieron de esa canal.',
                nota: 'El pollo no pasa por Fábrica — se surte directamente a la vitrina.',
            },
        ],
    },
    {
        id: 'flujo-3',
        titulo: 'Entrada directa a Inventario',
        subtitulo: 'Para productos que llegan listos (jamón, chorizos, víveres)',
        icon: Package,
        pasos: [
            {
                titulo: 'Agrega el producto al inventario',
                cuerpo: [
                    'Ve a <strong>INVENTARIO</strong> en el menú.',
                    'Presiona <strong>+ NUEVA ENTRADA</strong>.',
                    'Selecciona el producto de la lista.',
                    'Escribe los kilos o unidades que llegaron.',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'Este producto ya estará disponible para vender en el POS de inmediato.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-4',
        titulo: 'Venta en el Punto de Venta',
        subtitulo: 'Para cobrarle a un cliente',
        icon: ShoppingCart,
        pasos: [
            {
                titulo: 'Arma el pedido del cliente',
                cuerpo: [
                    'Ve a <strong>PUNTO DE VENTA</strong> en el menú.',
                    'Toca el producto que quiere el cliente.',
                    'Escribe los kilos (o unidades si aplica).',
                    'Repite para cada producto que lleve.',
                ],
                tip: 'El sistema calcula el precio automáticamente usando la tasa del día. Tú solo escribes los kilos.',
                nota: null,
            },
            {
                titulo: 'Cobra y cierra la venta',
                cuerpo: [
                    'Presiona el botón <strong>COBRAR</strong>.',
                    'Selecciona cómo pagó el cliente (Efectivo, Pago Móvil, Transferencia, etc.).',
                    'Escribe el monto que recibiste.',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'El ticket aparece en pantalla listo para imprimir o mostrarle al cliente.',
                nota: 'El stock se descuenta solo cuando la venta queda como "pagada". Un ticket abierto no toca el inventario.',
            },
        ],
    },
    {
        id: 'flujo-5',
        titulo: 'Corte de turno',
        subtitulo: 'Para sacar efectivo sin cerrar la caja',
        icon: RefreshCw,
        pasos: [
            {
                titulo: 'Registra un retiro de caja',
                cuerpo: [
                    'Ve a <strong>CAJA</strong> en el menú.',
                    'Presiona <strong>+ MOVIMIENTO</strong>.',
                    'Selecciona <strong>RETIRO</strong>.',
                    'Escribe el monto que estás sacando del cajón.',
                    'Escribe el motivo (ejemplo: "Depósito banco", "Pago proveedor").',
                    'Presiona <strong>Confirmar</strong>.',
                ],
                tip: 'La caja sigue abierta. Puedes seguir vendiendo normalmente.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-6',
        titulo: 'Cierre del día',
        subtitulo: 'Al final del día, cuando terminas de vender',
        icon: Moon,
        pasos: [
            {
                titulo: 'Cierra la caja del día',
                cuerpo: [
                    'Ve a <strong>CIERRE DEL DÍA</strong> en el menú.',
                    'El sistema te muestra las ventas del día desglosadas por método de pago.',
                    'Cuenta el dinero en efectivo que tienes físicamente en el cajón.',
                    'Escribe esa cantidad en el campo <strong>EFECTIVO CONTADO</strong>.',
                    'Presiona <strong>CONFIRMAR CIERRE</strong>.',
                ],
                tip: 'El día queda guardado. Mañana abres caja nueva desde cero.',
                nota: 'El sistema solo cuenta como efectivo en caja lo cobrado en efectivo. El dinero por Pago Móvil está en el banco, no en el cajón — por eso no lo verás como "efectivo esperado".',
            },
        ],
    },
    {
        id: 'flujo-7',
        titulo: 'Lectura de resultados',
        subtitulo: 'Para ver cómo te fue hoy o en el período',
        icon: BarChart2,
        pasos: [
            {
                titulo: '¿Dónde ver los números?',
                cuerpo: [
                    '<strong>Dashboard</strong>: ventas del día, tickets emitidos, rendimiento por canal.',
                    '<strong>Reportes → Resumen</strong>: filtra por hoy, semana, mes o año.',
                    '<strong>Panel Empresarial</strong>: compara sucursales (solo para dueños).',
                ],
                tip: 'El Dashboard se actualiza cada 30 segundos solo — no necesitas recargar la página.',
                nota: null,
            },
            {
                titulo: 'Entendiendo el Rendimiento por Canal',
                cuerpo: [
                    'Cada tarjeta muestra una canal comprada en Bóveda.',
                    '<strong>Costo</strong>: lo que pagaste por esa canal.',
                    '<strong>Vendido</strong>: lo que llevas recuperado con las ventas de esa canal.',
                    'La <strong>barra</strong> se llena a medida que recuperas el costo — llena y verde = cubriste el costo.',
                    '<strong>Ámbar = kilos disponibles mañana</strong> (pasaron a mañana sin venderse).',
                    '<strong>101% recuperado</strong> = cubriste el costo y además ganaste algo.',
                ],
                tip: 'Si la barra está roja, todavía no has vendido suficiente para cubrir lo que pagaste por esa canal.',
                nota: null,
            },
        ],
    },
]

// ─── Estado de navegación ──────────────────────────────────────────────────────
const flujoActual   = ref(0)
const pasoActual    = ref(0)
const menuAbierto   = ref(false)
const terminado     = ref(false)

const flujo  = computed(() => flujos[flujoActual.value])
const paso   = computed(() => flujo.value.pasos[pasoActual.value])
const totalPasos = computed(() => flujo.value.pasos.length)

function irA(fi, pi = 0) {
    flujoActual.value = fi
    pasoActual.value  = pi
    terminado.value   = false
    menuAbierto.value = false
    window.location.hash = flujos[fi].id
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

function siguiente() {
    if (pasoActual.value < totalPasos.value - 1) {
        pasoActual.value++
    } else if (flujoActual.value < flujos.length - 1) {
        irA(flujoActual.value + 1)
    } else {
        terminado.value = true
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

function anterior() {
    if (terminado.value) { terminado.value = false; return }
    if (pasoActual.value > 0) {
        pasoActual.value--
    } else if (flujoActual.value > 0) {
        const fi = flujoActual.value - 1
        irA(fi, flujos[fi].pasos.length - 1)
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

const esPrimero = computed(() => flujoActual.value === 0 && pasoActual.value === 0 && !terminado.value)
const esUltimo  = computed(() => flujoActual.value === flujos.length - 1 && pasoActual.value === totalPasos.value - 1)

// Progreso global (de 0 a 1)
const progresoGlobal = computed(() => {
    if (terminado.value) return 1
    let totalAntes = 0
    for (let i = 0; i < flujoActual.value; i++) totalAntes += flujos[i].pasos.length
    const totalTodos = flujos.reduce((s, f) => s + f.pasos.length, 0)
    return (totalAntes + pasoActual.value) / totalTodos
})

// Leer hash inicial
onMounted(() => {
    const hash = window.location.hash.replace('#', '')
    if (hash) {
        const idx = flujos.findIndex(f => f.id === hash)
        if (idx !== -1) { flujoActual.value = idx; pasoActual.value = 0 }
    }
})
</script>

<template>
    <div class="ayuda-root">

        <!-- ─── Header ──────────────────────────────────────────────────────────── -->
        <header class="ayuda-header">
            <div class="ayuda-header-inner">
                <span class="ayuda-logo">SYNTImeat</span>
                <span class="ayuda-header-tag">Guía de uso</span>
                <button class="burger" @click="menuAbierto = !menuAbierto" aria-label="Menú">
                    <X v-if="menuAbierto" :size="22" />
                    <Menu v-else :size="22" />
                </button>
            </div>

            <!-- Progreso global -->
            <div class="progreso-global">
                <div class="progreso-fill" :style="{ width: (progresoGlobal * 100) + '%' }" />
            </div>
        </header>

        <div class="ayuda-body">

            <!-- ─── Sidebar / menú de flujos ────────────────────────────────────── -->
            <nav class="flujos-nav" :class="{ 'flujos-nav--open': menuAbierto }">
                <p class="nav-label">Flujos</p>
                <button
                    v-for="(f, fi) in flujos"
                    :key="f.id"
                    class="nav-item"
                    :class="{ 'nav-item--active': fi === flujoActual }"
                    @click="irA(fi)"
                >
                    <component :is="f.icon" :size="16" />
                    <span>{{ f.titulo }}</span>
                </button>
            </nav>

            <!-- ─── Contenido principal ─────────────────────────────────────────── -->
            <main class="ayuda-main">

                <!-- Pantalla final -->
                <div v-if="terminado" class="fin-card">
                    <CheckCircle :size="64" class="fin-icon" />
                    <h1 class="fin-titulo">¡Listo! Ya sabes usar SYNTImeat</h1>
                    <p class="fin-sub">
                        Pasaste por los 7 flujos principales del sistema.<br>
                        Si tienes dudas, cada módulo tiene un botón <strong>?</strong> con ayuda específica.
                    </p>
                    <a href="/dashboard" class="btn-ir">Ir al sistema →</a>
                    <button class="btn-reiniciar" @click="irA(0)">Volver al inicio de la guía</button>
                </div>

                <!-- Paso actual -->
                <template v-else>
                    <!-- Encabezado del flujo -->
                    <div class="flujo-header">
                        <div class="flujo-num-wrap">
                            <component :is="flujo.icon" :size="28" class="flujo-icon" />
                            <span class="flujo-num">Flujo {{ flujoActual + 1 }} de {{ flujos.length }}</span>
                        </div>
                        <h2 class="flujo-titulo">{{ flujo.titulo }}</h2>
                        <p class="flujo-sub">{{ flujo.subtitulo }}</p>

                        <!-- Mini progreso dentro del flujo -->
                        <div v-if="totalPasos > 1" class="flujo-pasos-dots">
                            <span
                                v-for="(_, pi) in flujo.pasos"
                                :key="pi"
                                class="dot"
                                :class="{ 'dot--on': pi <= pasoActual }"
                            />
                        </div>
                    </div>

                    <!-- Card del paso -->
                    <div class="paso-card">
                        <div class="paso-num">{{ pasoActual + 1 }}</div>
                        <h3 class="paso-titulo">{{ paso.titulo }}</h3>

                        <ol class="paso-lista">
                            <li
                                v-for="(linea, li) in paso.cuerpo"
                                :key="li"
                                v-html="linea"
                            />
                        </ol>

                        <div v-if="paso.tip" class="paso-tip">
                            <CheckCircle :size="16" />
                            <span>{{ paso.tip }}</span>
                        </div>

                        <div v-if="paso.nota" class="paso-nota">
                            <span class="nota-label">Nota:</span> {{ paso.nota }}
                        </div>
                    </div>

                    <!-- Navegación anterior / siguiente -->
                    <div class="nav-btns">
                        <button
                            class="btn-nav btn-nav--prev"
                            :disabled="esPrimero"
                            @click="anterior"
                        >
                            <ChevronLeft :size="20" /> Anterior
                        </button>

                        <span class="nav-paginacion">
                            Paso {{ pasoActual + 1 }}/{{ totalPasos }}
                        </span>

                        <button class="btn-nav btn-nav--next" @click="siguiente">
                            {{ esUltimo ? 'Finalizar' : 'Siguiente' }}
                            <ChevronRight :size="20" />
                        </button>
                    </div>

                    <!-- Saltar a flujo (links rápidos) -->
                    <div class="saltar-flujos">
                        <p class="saltar-label">Ir directamente a:</p>
                        <div class="saltar-chips">
                            <button
                                v-for="(f, fi) in flujos"
                                :key="f.id"
                                class="chip"
                                :class="{ 'chip--on': fi === flujoActual }"
                                @click="irA(fi)"
                            >
                                {{ fi + 1 }}. {{ f.titulo }}
                            </button>
                        </div>
                    </div>
                </template>

            </main>
        </div>

    </div>
</template>

<style scoped>
/* ─── Reset + fuente ──────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.ayuda-root {
    min-height: 100vh;
    background: var(--bg-base, #0f0f0f);
    color: var(--text-primary, #f1f1f1);
    font-family: 'Plus Jakarta Sans', sans-serif;
}

/* ─── Header ─────────────────────────────────────────────────────────────────── */
.ayuda-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: var(--bg-card, #1a1a1a);
    border-bottom: 1px solid var(--border, #2a2a2a);
}
.ayuda-header-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
}
.ayuda-logo {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--brand, #e53e3e);
    letter-spacing: -0.5px;
}
.ayuda-header-tag {
    font-size: 0.78rem;
    color: var(--text-muted, #888);
    background: var(--bg-elevated, #222);
    padding: 2px 8px;
    border-radius: 20px;
    flex: 1;
}
.burger {
    background: none;
    border: none;
    color: var(--text-primary, #f1f1f1);
    cursor: pointer;
    padding: 6px;
    display: flex;
    align-items: center;
}
@media (min-width: 768px) { .burger { display: none; } }

/* Progreso global */
.progreso-global {
    height: 3px;
    background: var(--bg-elevated, #222);
}
.progreso-fill {
    height: 100%;
    background: var(--brand, #e53e3e);
    transition: width 0.4s ease;
}

/* ─── Body layout ────────────────────────────────────────────────────────────── */
.ayuda-body {
    display: flex;
    min-height: calc(100vh - 57px);
}

/* ─── Sidebar nav ────────────────────────────────────────────────────────────── */
.flujos-nav {
    display: none;
    flex-direction: column;
    gap: 4px;
    width: 240px;
    flex-shrink: 0;
    padding: 24px 12px;
    border-right: 1px solid var(--border, #2a2a2a);
    background: var(--bg-card, #1a1a1a);
}
@media (min-width: 768px) {
    .flujos-nav { display: flex; }
}
/* Mobile: overlay cuando está abierto */
.flujos-nav--open {
    display: flex;
    position: fixed;
    top: 57px;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    z-index: 40;
    overflow-y: auto;
    padding: 20px 16px;
}
.nav-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted, #888);
    padding: 0 8px;
    margin-bottom: 8px;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    border: none;
    background: none;
    color: var(--text-muted, #888);
    font-family: inherit;
    font-size: 0.88rem;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s, color 0.15s;
    min-height: 44px;
}
.nav-item:hover { background: var(--bg-elevated, #222); color: var(--text-primary, #f1f1f1); }
.nav-item--active {
    background: color-mix(in srgb, var(--brand, #e53e3e) 15%, transparent);
    color: var(--brand, #e53e3e);
    font-weight: 600;
}

/* ─── Main content ───────────────────────────────────────────────────────────── */
.ayuda-main {
    flex: 1;
    padding: 24px 16px 48px;
    max-width: 680px;
    margin: 0 auto;
    width: 100%;
}
@media (min-width: 768px) {
    .ayuda-main { padding: 40px 48px 64px; }
}

/* ─── Flujo header ───────────────────────────────────────────────────────────── */
.flujo-header {
    margin-bottom: 24px;
}
.flujo-num-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.flujo-icon { color: var(--brand, #e53e3e); }
.flujo-num {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-muted, #888);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.flujo-titulo {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-primary, #f1f1f1);
    line-height: 1.2;
    margin-bottom: 6px;
}
.flujo-sub {
    font-size: 0.95rem;
    color: var(--text-muted, #888);
    margin-bottom: 16px;
}
.flujo-pasos-dots {
    display: flex;
    gap: 6px;
}
.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border, #2a2a2a);
    transition: background 0.2s;
}
.dot--on { background: var(--brand, #e53e3e); }

/* ─── Paso card ──────────────────────────────────────────────────────────────── */
.paso-card {
    background: var(--bg-card, #1a1a1a);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 16px;
    padding: 28px 24px;
    margin-bottom: 24px;
    position: relative;
}
.paso-num {
    font-size: 3rem;
    font-weight: 900;
    color: var(--brand, #e53e3e);
    line-height: 1;
    margin-bottom: 8px;
    font-variant-numeric: tabular-nums;
}
.paso-titulo {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-primary, #f1f1f1);
    margin-bottom: 20px;
    line-height: 1.3;
}
.paso-lista {
    padding-left: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}
.paso-lista li {
    font-size: 1rem;
    line-height: 1.8;
    color: var(--text-primary, #f1f1f1);
}
.paso-lista li :deep(strong) {
    color: var(--brand, #e53e3e);
    font-weight: 700;
}
.paso-tip {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: color-mix(in srgb, var(--brand, #e53e3e) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand, #e53e3e) 25%, transparent);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 0.9rem;
    line-height: 1.5;
    color: var(--text-primary, #f1f1f1);
    margin-top: 4px;
}
.paso-tip svg { color: var(--brand, #e53e3e); flex-shrink: 0; margin-top: 2px; }
.paso-nota {
    margin-top: 14px;
    font-size: 0.85rem;
    color: var(--text-muted, #888);
    line-height: 1.6;
    border-left: 3px solid var(--border, #2a2a2a);
    padding-left: 12px;
}
.nota-label {
    font-weight: 700;
    color: var(--text-primary, #f1f1f1);
}

/* ─── Botones navegación ─────────────────────────────────────────────────────── */
.nav-btns {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 32px;
}
.btn-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 14px 22px;
    border-radius: 12px;
    border: none;
    font-family: inherit;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    min-height: 48px;
    transition: opacity 0.15s, background 0.15s;
}
.btn-nav--prev {
    background: var(--bg-elevated, #222);
    color: var(--text-muted, #888);
}
.btn-nav--prev:hover:not(:disabled) { color: var(--text-primary, #f1f1f1); }
.btn-nav--prev:disabled { opacity: 0.35; cursor: default; }
.btn-nav--next {
    background: var(--brand, #e53e3e);
    color: #fff;
    margin-left: auto;
}
.btn-nav--next:hover { opacity: 0.88; }
.nav-paginacion {
    font-size: 0.8rem;
    color: var(--text-muted, #888);
    white-space: nowrap;
}

/* ─── Saltar a flujo ─────────────────────────────────────────────────────────── */
.saltar-flujos { margin-top: 8px; }
.saltar-label {
    font-size: 0.75rem;
    color: var(--text-muted, #888);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 10px;
    font-weight: 600;
}
.saltar-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.chip {
    padding: 6px 12px;
    border-radius: 20px;
    border: 1px solid var(--border, #2a2a2a);
    background: none;
    color: var(--text-muted, #888);
    font-family: inherit;
    font-size: 0.8rem;
    cursor: pointer;
    min-height: 36px;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.chip:hover { background: var(--bg-elevated, #222); color: var(--text-primary, #f1f1f1); }
.chip--on {
    border-color: var(--brand, #e53e3e);
    color: var(--brand, #e53e3e);
    font-weight: 600;
}

/* ─── Pantalla final ─────────────────────────────────────────────────────────── */
.fin-card {
    text-align: center;
    padding: 48px 24px;
    background: var(--bg-card, #1a1a1a);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.fin-icon { color: #22c55e; }
.fin-titulo {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-primary, #f1f1f1);
    line-height: 1.3;
}
.fin-sub {
    font-size: 1rem;
    color: var(--text-muted, #888);
    line-height: 1.7;
    max-width: 460px;
}
.btn-ir {
    display: inline-block;
    padding: 16px 32px;
    background: var(--brand, #e53e3e);
    color: #fff;
    border-radius: 12px;
    font-family: inherit;
    font-size: 1.05rem;
    font-weight: 700;
    text-decoration: none;
    min-height: 52px;
    line-height: 1;
    display: flex;
    align-items: center;
}
.btn-ir:hover { opacity: 0.88; }
.btn-reiniciar {
    background: none;
    border: none;
    color: var(--text-muted, #888);
    font-family: inherit;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: underline;
    min-height: 44px;
}
.btn-reiniciar:hover { color: var(--text-primary, #f1f1f1); }
</style>
