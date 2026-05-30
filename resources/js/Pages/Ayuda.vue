<script setup>
import { ref, computed, onMounted } from 'vue'
import {
    DollarSign, Package, ShoppingCart, BarChart2,
    RefreshCw, Moon, Truck, ChevronLeft, ChevronRight,
    CheckCircle, Menu, X, AlertTriangle, Clock, Info, Scale,
} from '@lucide/vue'

// ─── Flujos ───────────────────────────────────────────────────────────────────
const flujos = [
    {
        id: 'flujo-1',
        titulo: 'Inicio del día',
        subtitulo: 'Lo primero que haces antes de vender',
        icon: DollarSign,
        pasos: [
            {
                titulo: 'Abre tu caja antes de vender',
                alerta: 'Sin caja abierta no puedes vender. El Punto de Venta estará bloqueado hasta que abras tu caja.',
                cuerpo: [
                    'Ve a <strong>CAJA</strong> en el menú lateral.',
                    'Presiona el botón <strong>ABRIR CAJA</strong>.',
                    'Escribe el efectivo que tienes en el cajón ahorita (ejemplo: Bs. 5.000).',
                    'Confirma.',
                ],
                tip: 'Ya puedes vender. El sistema lleva la cuenta desde ese momento.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-2',
        titulo: 'Entrada de mercancía a Bóveda',
        subtitulo: 'Cuando llega una canal — res, cerdo o pollo',
        icon: Truck,
        pasos: [
            {
                titulo: '¿Tienes pollos de diferentes marcas o tamaños?',
                alerta: null,
                cuerpo: [
                    'Antes de registrar en Bóveda, crea en <strong>CATÁLOGO</strong> el producto de vitrina con su nombre y precio.',
                    'Por ejemplo: "Pollo Campesino $4/kg", "Pollo Importado $6/kg".',
                    'Luego ve a <strong>Configuración → Productos Bóveda</strong> y vincula tu producto bóveda con el de vitrina.',
                    'Al surtir, los kilos irán directo a ese producto de vitrina con tu precio.',
                    'Puedes crear tantas variedades como necesites.',
                ],
                tip: 'Si solo manejas un tipo de pollo no necesitas hacer esto — sigue al paso siguiente.',
                nota: null,
            },
            {
                titulo: 'Registra la canal en Bóveda',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>BÓVEDA</strong> en el menú.',
                    'Presiona <strong>+ NUEVA ENTRADA</strong>.',
                    'Selecciona el tipo de canal (Res - Medio Canal, Cerdo - Canal, Pollo - Entero Congelado…).',
                    'Escribe los kilos y el costo en dólares.',
                    'Confirma.',
                ],
                tip: 'El sistema calcula tu utilidad automáticamente cuando empieces a vender de esa canal.',
                nota: null,
            },
            {
                titulo: 'Súrtela a la tienda',
                alerta: null,
                cuerpo: [
                    'En Bóveda, busca la canal que acabas de registrar.',
                    'Presiona <strong>SURTIR</strong>.',
                    'Escribe cuántos kilos van a la tienda hoy.',
                    'Confirma.',
                ],
                tip: 'Solo lo que surtes llega al inventario de vitrina para venderse en el POS.',
                nota: null,
            },
            {
                titulo: 'Llévala a Fábrica si es Res o Cerdo',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>FÁBRICA</strong> en el menú.',
                    'Verás la canal pendiente de despiece.',
                    'Escribe cuántos kilos salieron de cada corte (lomito, paleta, costilla…).',
                    'Confirma.',
                ],
                tip: 'Fábrica registra el despiece para que el sistema sepa qué productos salieron de esa canal.',
                nota: 'El pollo no pasa por Fábrica — se surte directamente a vitrina.',
            },
        ],
    },
    {
        id: 'flujo-3',
        titulo: 'Entrada directa a Inventario',
        subtitulo: 'Para jamón, chorizos, víveres y todo lo que llega listo',
        icon: Package,
        pasos: [
            {
                titulo: 'Agrega el producto directo al inventario',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>INVENTARIO</strong> en el menú.',
                    'Presiona <strong>+ NUEVA ENTRADA</strong>.',
                    'Selecciona el producto.',
                    'Escribe los kilos o unidades que llegaron.',
                    'Confirma.',
                ],
                tip: 'Este producto queda disponible de inmediato para vender en el POS.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-4',
        titulo: 'Venta en el Punto de Venta',
        subtitulo: 'Así cobras a cada cliente',
        icon: ShoppingCart,
        pasos: [
            {
                titulo: 'Arma el pedido del cliente',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>PUNTO DE VENTA</strong> en el menú.',
                    'Toca el producto que quiere el cliente.',
                    'Escribe los kilos (o unidades si aplica).',
                    'Repite para cada producto que lleve.',
                ],
                tip: 'El precio se calcula solo usando la tasa del día. Tú solo escribes los kilos.',
                nota: null,
            },
            {
                titulo: 'Cobra y cierra la venta',
                alerta: null,
                cuerpo: [
                    'Presiona <strong>COBRAR</strong>.',
                    'Selecciona cómo pagó el cliente (Efectivo, Pago Móvil, Transferencia…).',
                    'Escribe el monto recibido.',
                    'Confirma.',
                ],
                tip: 'El ticket aparece en pantalla listo para imprimir o mostrarle al cliente.',
                nota: 'El stock se descuenta solo cuando la venta queda pagada. Un ticket abierto sin cobrar no toca el inventario.',
            },
            {
                titulo: 'Corte bancario — ventas después de las 7 PM',
                alerta: 'Todos los días a las 7:00 PM el sistema cambia de día contable. Las ventas después de esa hora se registran como ventas del día SIGUIENTE.',
                cuerpo: [
                    'Esto es normal — así funciona el sistema bancario venezolano.',
                    'Ejemplo: si vendes a las 8:00 PM del lunes, esa venta aparecerá en el reporte del martes.',
                    'El Punto de Venta sigue funcionando con normalidad — solo cambia en qué "día" queda registrada.',
                ],
                tip: 'No necesitas hacer nada especial. El sistema lo maneja solo.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-5',
        titulo: 'Corte de turno',
        subtitulo: 'Saca efectivo sin cerrar la caja',
        icon: RefreshCw,
        pasos: [
            {
                titulo: 'Registra un retiro de efectivo',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>CAJA</strong> en el menú.',
                    'Presiona <strong>+ MOVIMIENTO</strong>.',
                    'Selecciona <strong>RETIRO</strong>.',
                    'Escribe el monto que estás sacando del cajón.',
                    'Escribe el motivo (ejemplo: "Depósito banco", "Pago proveedor").',
                    'Confirma.',
                ],
                tip: 'La caja sigue abierta. Puedes seguir vendiendo sin problema.',
                nota: null,
            },
            {
                titulo: 'Corte de turno vs. Cierre del día',
                alerta: null,
                cuerpo: [
                    'El <strong>CORTE DE TURNO</strong> no cierra la caja — solo retira efectivo. Puedes hacer varios cortes en el mismo día.',
                    'El <strong>CIERRE DEL DÍA</strong> sí cierra la caja definitivamente. No puedes vender más hasta que abras una caja nueva.',
                    'Usa el corte cuando necesitas sacar plata del cajón sin terminar el día.',
                    'Usa el cierre cuando terminaste de vender por hoy.',
                ],
                tip: 'Todos los retiros quedan registrados con hora y motivo para que los puedas revisar.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-6',
        titulo: 'Cierre del día',
        subtitulo: 'Cuando terminas de vender y cierras la caja',
        icon: Moon,
        pasos: [
            {
                titulo: 'Cierra la caja del día',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>CIERRE DEL DÍA</strong> en el menú.',
                    'El sistema te muestra las ventas del día desglosadas por método de pago.',
                    'Cuenta el efectivo físico que tienes en el cajón.',
                    'Escríbelo en el campo <strong>EFECTIVO CONTADO</strong>.',
                    'Presiona <strong>CONFIRMAR CIERRE</strong>.',
                ],
                tip: 'El día queda guardado y cerrado. No puedes agregar más ventas a ese día.',
                nota: null,
            },
            {
                titulo: 'Efectivo esperado vs. lo que tienes en el cajón',
                alerta: null,
                cuerpo: [
                    'Solo cuenta como efectivo en caja lo que cobraste en <strong>efectivo físico</strong>.',
                    'El dinero por Pago Móvil, transferencia o punto de venta está en el banco — no en tu cajón.',
                    'Por eso esos montos no suman al efectivo esperado. Es normal que sean diferentes.',
                ],
                tip: 'Si el efectivo contado no cuadra con el esperado, el sistema registra la diferencia para que puedas revisarla.',
                nota: null,
            },
            {
                titulo: 'Mañana abre caja nueva',
                alerta: null,
                cuerpo: [
                    'Al día siguiente el sistema no abre caja solo — tú debes abrirla.',
                    'Ve a <strong>CAJA → ABRIR CAJA</strong> antes de empezar a vender.',
                    'Si intentas vender sin abrir caja, el Punto de Venta estará bloqueado.',
                ],
                tip: 'Convierte esto en hábito: abrir caja es lo primero al llegar, cerrar caja es lo último antes de irte.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-7',
        titulo: 'Lectura de resultados',
        subtitulo: 'Para ver cómo te fue hoy y en el período',
        icon: BarChart2,
        pasos: [
            {
                titulo: '¿Dónde ver los números?',
                alerta: null,
                cuerpo: [
                    '<strong>Dashboard</strong>: ventas del día, tickets emitidos, rendimiento por canal.',
                    '<strong>Reportes → Resumen</strong>: filtra por hoy, semana, mes o año.',
                    '<strong>Panel Empresarial</strong>: compara sucursales (solo dueños).',
                ],
                tip: 'El Dashboard se actualiza cada 30 segundos solo — no necesitas recargar la página.',
                nota: null,
            },
            {
                titulo: 'Rendimiento por Canal — cómo leerlo',
                alerta: null,
                cuerpo: [
                    'Cada tarjeta muestra una canal comprada en Bóveda.',
                    'La <strong>barra de cada producto</strong> muestra cuánto vendiste del total disponible.',
                    '<strong>Verde</strong> = vendiste todo el lote. <strong>Azul</strong> = vendiste parte. <strong>Vacía</strong> = no vendiste nada.',
                    'En <strong>ámbar</strong> verás los kilos que quedaron para el día siguiente.',
                ],
                tip: 'La barra llena y verde significa que vendiste toda la mercancía de ese corte.',
                nota: null,
            },
            {
                titulo: '¿Qué significa el % recuperado?',
                alerta: null,
                cuerpo: [
                    'Muestra qué porcentaje del costo de la canal ya recuperaste con las ventas.',
                    '<strong>50% recuperado</strong> = recuperaste la mitad de lo que pagaste.',
                    '<strong>100% recuperado</strong> = cubriste exactamente lo que pagaste.',
                    '<strong>101% o más</strong> = cubriste el costo y ganaste algo encima.',
                ],
                tip: 'La barra se pone roja si todavía no cubriste el costo, verde cuando lo superaste.',
                nota: null,
            },
        ],
    },
    {
        id: 'flujo-8',
        titulo: 'Ajuste de Stock',
        subtitulo: 'Para cuando el conteo físico no coincide con lo que muestra el sistema',
        icon: Scale,
        pasos: [
            {
                titulo: 'Cómo corregir el stock de un producto',
                alerta: null,
                cuerpo: [
                    'Ve a <strong>INVENTARIO</strong> y toca el producto que necesitas corregir.',
                    'En el panel lateral aparece el botón <strong>Ajustar stock</strong> (el cajero no lo ve).',
                    'El sistema muestra el stock actual registrado.',
                    'Escribe el número real que hay físicamente en ese momento.',
                    'Toca <strong>Confirmar ajuste</strong>.',
                    'El sistema calcula la diferencia y corrige el stock automáticamente.',
                ],
                tip: '¿Quién puede usarlo? Dueño, Administrador y Contable. El cajero no tiene acceso.',
                nota: 'El ajuste queda registrado en el historial del producto con la descripción "Ajuste de inventario". No borra entradas anteriores — crea un movimiento de corrección.',
            },
        ],
    },
]

// ─── Estado de navegación ──────────────────────────────────────────────────────
const flujoActual = ref(0)
const pasoActual  = ref(0)
const menuAbierto = ref(false)
const terminado   = ref(false)

const flujo      = computed(() => flujos[flujoActual.value])
const paso       = computed(() => flujo.value.pasos[pasoActual.value])
const totalPasos = computed(() => flujo.value.pasos.length)

const esPrimero = computed(() => flujoActual.value === 0 && pasoActual.value === 0 && !terminado.value)
const esUltimo  = computed(() => flujoActual.value === flujos.length - 1 && pasoActual.value === totalPasos.value - 1)

const progresoGlobal = computed(() => {
    if (terminado.value) return 1
    let antes = 0
    for (let i = 0; i < flujoActual.value; i++) antes += flujos[i].pasos.length
    const total = flujos.reduce((s, f) => s + f.pasos.length, 0)
    return (antes + pasoActual.value) / total
})

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
            <div class="progreso-global">
                <div class="progreso-fill" :style="{ width: (progresoGlobal * 100) + '%' }" />
            </div>
        </header>

        <div class="ayuda-body">

            <!-- ─── Sidebar ──────────────────────────────────────────────────────── -->
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
                    <span>{{ fi + 1 }}. {{ f.titulo }}</span>
                </button>
            </nav>

            <!-- ─── Contenido ────────────────────────────────────────────────────── -->
            <main class="ayuda-main">

                <!-- Pantalla final -->
                <div v-if="terminado" class="fin-card">
                    <CheckCircle :size="64" class="fin-icon" />
                    <h1 class="fin-titulo">¡Listo! Ya sabes usar SYNTImeat</h1>
                    <p class="fin-sub">
                        Pasaste por los {{ flujos.length }} flujos principales del sistema.<br>
                        Si tienes dudas, cada módulo tiene un botón <strong>?</strong> con ayuda específica.
                    </p>
                    <a href="/dashboard" class="btn-ir">Ir al sistema →</a>
                    <button class="btn-reiniciar" @click="irA(0)">Volver al inicio de la guía</button>
                </div>

                <!-- Paso actual -->
                <template v-else>

                    <!-- Encabezado del flujo -->
                    <div class="flujo-header">
                        <div class="flujo-meta">
                            <component :is="flujo.icon" :size="24" class="flujo-icon" />
                            <span class="flujo-num-txt">Flujo {{ flujoActual + 1 }} de {{ flujos.length }}</span>
                        </div>
                        <h2 class="flujo-titulo">{{ flujo.titulo }}</h2>
                        <p class="flujo-sub">{{ flujo.subtitulo }}</p>

                        <!-- Dots de progreso dentro del flujo -->
                        <div v-if="totalPasos > 1" class="flujo-dots">
                            <span
                                v-for="(_, pi) in flujo.pasos"
                                :key="pi"
                                class="dot"
                                :class="{ 'dot--on': pi <= pasoActual }"
                            />
                        </div>
                    </div>

                    <!-- Alerta del paso (si tiene) -->
                    <div v-if="paso.alerta" class="paso-alerta">
                        <AlertTriangle :size="18" class="alerta-icon" />
                        <span>{{ paso.alerta }}</span>
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
                            <Info :size="14" class="nota-icon" />
                            <span><strong>Nota:</strong> {{ paso.nota }}</span>
                        </div>
                    </div>

                    <!-- Navegación -->
                    <div class="nav-btns">
                        <button
                            class="btn-nav btn-nav--prev"
                            :disabled="esPrimero"
                            @click="anterior"
                        >
                            <ChevronLeft :size="20" /> Anterior
                        </button>
                        <span class="nav-paginacion">{{ pasoActual + 1 }} / {{ totalPasos }}</span>
                        <button class="btn-nav btn-nav--next" @click="siguiente">
                            {{ esUltimo ? 'Finalizar' : 'Siguiente' }}
                            <ChevronRight :size="20" />
                        </button>
                    </div>

                    <!-- Saltar a flujo -->
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
    flex: 1;
    font-size: 0.78rem;
    color: var(--text-muted, #888);
    background: var(--bg-elevated, #222);
    padding: 3px 10px;
    border-radius: 20px;
}
.burger {
    background: none;
    border: none;
    color: var(--text-primary, #f1f1f1);
    cursor: pointer;
    padding: 6px;
    display: flex;
    align-items: center;
    min-height: 44px;
}
@media (min-width: 768px) { .burger { display: none; } }

.progreso-global { height: 3px; background: var(--bg-elevated, #222); }
.progreso-fill {
    height: 100%;
    background: var(--brand, #e53e3e);
    transition: width 0.4s ease;
}

/* ─── Layout ─────────────────────────────────────────────────────────────────── */
.ayuda-body { display: flex; min-height: calc(100vh - 57px); }

/* ─── Sidebar ────────────────────────────────────────────────────────────────── */
.flujos-nav {
    display: none;
    flex-direction: column;
    gap: 2px;
    width: 250px;
    flex-shrink: 0;
    padding: 24px 12px;
    border-right: 1px solid var(--border, #2a2a2a);
    background: var(--bg-card, #1a1a1a);
}
@media (min-width: 768px) { .flujos-nav { display: flex; } }
.flujos-nav--open {
    display: flex;
    position: fixed;
    top: 57px; left: 0; right: 0; bottom: 0;
    width: 100%;
    z-index: 40;
    overflow-y: auto;
    padding: 20px 16px;
}
.nav-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: var(--text-muted, #888);
    padding: 0 10px;
    margin-bottom: 10px;
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
    font-size: 0.85rem;
    cursor: pointer;
    text-align: left;
    min-height: 44px;
    transition: background 0.15s, color 0.15s;
}
.nav-item:hover { background: var(--bg-elevated, #222); color: var(--text-primary, #f1f1f1); }
.nav-item--active {
    background: color-mix(in srgb, var(--brand, #e53e3e) 14%, transparent);
    color: var(--brand, #e53e3e);
    font-weight: 600;
}

/* ─── Main ───────────────────────────────────────────────────────────────────── */
.ayuda-main {
    flex: 1;
    padding: 24px 16px 56px;
    max-width: 680px;
    margin: 0 auto;
    width: 100%;
}
@media (min-width: 768px) { .ayuda-main { padding: 40px 48px 72px; } }

/* ─── Flujo header ───────────────────────────────────────────────────────────── */
.flujo-header { margin-bottom: 20px; }
.flujo-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.flujo-icon { color: var(--brand, #e53e3e); }
.flujo-num-txt {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted, #888);
    text-transform: uppercase;
    letter-spacing: 0.07em;
}
.flujo-titulo {
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.2;
    color: var(--text-primary, #f1f1f1);
    margin-bottom: 6px;
}
.flujo-sub {
    font-size: 0.95rem;
    color: var(--text-muted, #888);
    margin-bottom: 14px;
}
.flujo-dots { display: flex; gap: 6px; }
.dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--border, #2a2a2a);
    transition: background 0.2s;
}
.dot--on { background: var(--brand, #e53e3e); }

/* ─── Alerta del paso ────────────────────────────────────────────────────────── */
.paso-alerta {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: color-mix(in srgb, #f59e0b 12%, transparent);
    border: 1px solid color-mix(in srgb, #f59e0b 30%, transparent);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 16px;
    font-size: 0.92rem;
    line-height: 1.6;
    color: var(--text-primary, #f1f1f1);
}
.alerta-icon { color: #f59e0b; flex-shrink: 0; margin-top: 2px; }

/* ─── Paso card ──────────────────────────────────────────────────────────────── */
.paso-card {
    background: var(--bg-card, #1a1a1a);
    border: 1px solid var(--border, #2a2a2a);
    border-radius: 16px;
    padding: 28px 24px;
    margin-bottom: 24px;
}
.paso-num {
    font-size: 3rem;
    font-weight: 900;
    color: var(--brand, #e53e3e);
    line-height: 1;
    margin-bottom: 8px;
}
.paso-titulo {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-primary, #f1f1f1);
    line-height: 1.3;
    margin-bottom: 20px;
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
.paso-lista li :deep(strong) { color: var(--brand, #e53e3e); font-weight: 700; }
.paso-tip {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: color-mix(in srgb, var(--brand, #e53e3e) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand, #e53e3e) 22%, transparent);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 0.9rem;
    line-height: 1.55;
    color: var(--text-primary, #f1f1f1);
    margin-bottom: 12px;
}
.paso-tip svg { color: var(--brand, #e53e3e); flex-shrink: 0; margin-top: 2px; }
.paso-nota {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.84rem;
    color: var(--text-muted, #888);
    line-height: 1.6;
    border-left: 3px solid var(--border, #2a2a2a);
    padding-left: 12px;
    margin-top: 4px;
}
.nota-icon { flex-shrink: 0; margin-top: 2px; color: var(--text-muted, #888); }
.paso-nota :deep(strong) { color: var(--text-primary, #f1f1f1); }

/* ─── Botones nav ────────────────────────────────────────────────────────────── */
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
.btn-nav--prev:disabled { opacity: 0.3; cursor: default; }
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

/* ─── Chips de flujo ─────────────────────────────────────────────────────────── */
.saltar-flujos { margin-top: 4px; }
.saltar-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted, #888);
    margin-bottom: 10px;
}
.saltar-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.chip {
    padding: 6px 14px;
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
.chip--on { border-color: var(--brand, #e53e3e); color: var(--brand, #e53e3e); font-weight: 600; }

/* ─── Pantalla final ─────────────────────────────────────────────────────────── */
.fin-card {
    text-align: center;
    padding: 52px 24px;
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
    font-size: 1.65rem;
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
.fin-sub :deep(strong) { color: var(--text-primary, #f1f1f1); }
.btn-ir {
    display: flex;
    align-items: center;
    padding: 16px 32px;
    background: var(--brand, #e53e3e);
    color: #fff;
    border-radius: 12px;
    font-family: inherit;
    font-size: 1.05rem;
    font-weight: 700;
    text-decoration: none;
    min-height: 52px;
    transition: opacity 0.15s;
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

@media (max-width: 640px) {
    .ayuda-header { padding: 0.625rem 0.875rem; }
    .ayuda-main   { padding: 1.25rem 1rem 3rem; }
    .flujos-nav   { gap: 0.375rem; padding: 0.375rem; }
    .flujo-btn    { font-size: 0.8rem; padding: 0.5rem 0.75rem; min-height: 44px; }
    .ayuda-section-title { font-size: 1.1rem; }
    .paso-title   { font-size: 0.9rem; }
}

@media (max-width: 1023px) {
    .ayuda-main   { padding: 1.5rem 1.25rem 3rem; }
    .flujos-nav   { flex-wrap: wrap; }
}
</style>
