<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import HelpModal      from '@/Components/HelpModal.vue'
import { useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    business: { type: Object, required: true },
    prefs:    { type: Object, required: true },
})

const form = useForm({
    pos_show_kg_visual: props.prefs.pos_show_kg_visual ?? false,
    show_kg:            props.prefs.show_kg            ?? true,
    show_kg_unit_price: props.prefs.show_kg_unit_price ?? false,
    kg_decimals:        props.prefs.kg_decimals        ?? 3,
    show_bs:            props.prefs.show_bs            ?? true,
    show_usd:           props.prefs.show_usd           ?? false,
    usd_format:         props.prefs.usd_format         ?? 'usd',
    show_description:   props.prefs.show_description   ?? true,
    show_address:       props.prefs.show_address       ?? true,
    show_phone:         props.prefs.show_phone         ?? false,
    show_client:        props.prefs.show_client        ?? true,
    show_rif:           props.prefs.show_rif           ?? false,
    show_cajero:        props.prefs.show_cajero        ?? false,
    show_tasa:          props.prefs.show_tasa          ?? false,
    show_metodo_pago:   props.prefs.show_metodo_pago   ?? false,
    footer_text:        props.prefs.footer_text        ?? '',
    ticket_prefix:      props.prefs.ticket_prefix      ?? 'VEN',
})

function submit() {
    form.post(route('settings.ticket.update'))
}

// Vista previa del ticket — misma estructura que el modal de éxito del POS
const DEMO_ITEMS = [
    { name: 'Lomo de res',   description: 'Premium',  kg: 1.250, price_kg: 8.00,  total_bs: 520.00,  total_usd: 10.00 },
    { name: 'Chuleta cerdo', description: 'Fresca',   kg: 0.800, price_kg: 4.50,  total_bs: 187.50,  total_usd: 3.60  },
]

const previewItems = computed(() => {
    const dec = form.kg_decimals ?? 3
    return DEMO_ITEMS.map(item => {
        let kgLine = null
        if (form.show_kg) {
            kgLine = item.kg.toFixed(dec) + ' kg'
            if (form.show_kg_unit_price) {
                kgLine += ' × ' + item.price_kg.toFixed(2) + ' $/kg'
            }
        }
        return {
            name:        item.name,
            description: form.show_description ? item.description : null,
            kg:          kgLine,
            bs:          form.show_bs  ? item.total_bs.toLocaleString('de-DE', { minimumFractionDigits: 2 }) + ' Bs.' : null,
            usd:         form.show_usd ? (form.usd_format === 'ref' ? 'REF ' : '$') + item.total_usd.toFixed(2) : null,
        }
    })
})

const previewTotal = computed(() =>
    DEMO_ITEMS.reduce((s, i) => s + i.total_bs, 0).toLocaleString('de-DE', { minimumFractionDigits: 2 }) + ' Bs.'
)

const inputClass = 'w-full bg-[var(--bg-input)] border border-[var(--border)] text-[var(--text-primary)] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[var(--text-muted)] focus:border-[var(--brand)] focus:ring-1 focus:ring-[var(--brand)] outline-none transition'
const labelClass = 'block text-xs font-medium text-[var(--text-secondary)] mb-1.5'

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Prefijo y numeración',
        body:  'El prefijo identifica los tickets de este negocio (ej: "VEN" genera tickets VEN-0001, VEN-0002…). Úsalo para diferenciar tickets si tienes varias sucursales o sistemas.',
        tip:   'Usa mayúsculas y sin espacios. Máximo 10 caracteres. Ej: VEN, CAR-01, CHAG.',
    },
    {
        title: 'Qué muestra el ticket',
        body:  'Controla qué información aparece en el ticket al cliente: cantidad en kg, precio por kg, monto en Bs., referencia en dólares, datos del cliente y datos del negocio (dirección, teléfono).',
        tip:   'Activa solo lo que sea útil para tu cliente. Un ticket más corto se imprime más rápido y es más fácil de leer.',
    },
    {
        title: 'Campos adicionales de identificación',
        body:  'Puedes activar cuatro campos extra que aparecen en el encabezado o pie del ticket: RIF del negocio (número fiscal del establecimiento), Nombre del cajero (quién procesó la venta), Tasa BCV del día (referencia cambiaria al momento del cobro) y Método de pago (cómo pagó el cliente: Efectivo, Pago Móvil, etc.). Todos están desactivados por defecto — actívalos con el toggle si los necesitas.',
        tip:   'El nombre del cajero y la tasa BCV son útiles para auditoría y control interno. El cliente generalmente no necesita verlos.',
    },
    {
        title: 'Vista previa en tiempo real',
        body:  'La columna derecha muestra exactamente cómo se verá el ticket al confirmar una venta. Cada cambio que hagas en el formulario se refleja de inmediato en la vista previa.',
        tip:   'Guarda los cambios con el botón inferior. Los cambios aplican a todos los tickets nuevos — los tickets ya emitidos no se modifican.',
    },
]

const helpFaqs = [
    {
        q: '¿Los cambios afectan tickets ya impresos?',
        a: 'No. Los cambios solo aplican a las ventas nuevas. Los tickets anteriores mantienen el formato con el que fueron generados.',
    },
    {
        q: '¿Qué es la opción "REF" en divisas?',
        a: 'Muestra el monto en divisas como "REF 8.00" en lugar de "$8.00". Algunos negocios prefieren no mostrar el símbolo de dólar en el ticket para evitar confusiones con la moneda de cobro.',
    },
    {
        q: '¿El pie de página aparece siempre?',
        a: 'Sí, si lo configuras. Es un texto fijo que aparece al final de cada ticket. Ideal para mensajes de agradecimiento, número de WhatsApp o políticas de devolución.',
    },
]
</script>

<template>
    <SettingsLayout>
        <div class="settings-panel">

            <div class="settings-panel__header">
                <div class="header-row">
                    <div>
                        <h1 class="settings-panel__title">Ticket</h1>
                        <p class="settings-panel__desc">Configura qué muestra el ticket al cliente y el prefijo de numeración.</p>
                    </div>
                    <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
                </div>
            </div>

            <div class="tkt-layout">

                <!-- ── Formulario ──────────────────────────────────────────── -->
                <form @submit.prevent="submit" class="tkt-form">

                    <!-- Pantalla del cajero -->
                    <section class="tkt-section">
                        <h2 class="tkt-section-title">Pantalla del cajero (POS)</h2>
                        <div class="tkt-toggle-row" style="border-bottom: none; padding-bottom: 0;">
                            <label class="tkt-toggle">
                                <input type="checkbox" v-model="form.pos_show_kg_visual" class="tkt-chk" />
                                <div>
                                    <span class="tkt-toggle-label">Mostrar kilogramos en el carrito</span>
                                    <p class="tkt-toggle-hint">
                                        El cajero verá los kg calculados junto al monto Bs. en cada ítem del carrito.<br>
                                        Ejemplo: <code>Lomo de res — 1.250 kg — 650,00 Bs.</code><br>
                                        No afecta el ticket impreso ni el flujo de cobro.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </section>

                    <!-- Prefijo -->
                    <section class="tkt-section">
                        <h2 class="tkt-section-title">Numeración</h2>
                        <div>
                            <label :class="labelClass">Prefijo del ticket</label>
                            <input v-model="form.ticket_prefix" type="text" :class="inputClass" maxlength="10" placeholder="VEN" />
                            <p v-if="form.errors.ticket_prefix" class="mt-1 text-xs text-red-400">{{ form.errors.ticket_prefix }}</p>
                            <p class="mt-1 text-xs text-[var(--text-muted)]">Solo mayúsculas, números y guión. Ej: VEN, CAR-01</p>
                        </div>
                    </section>

                    <!-- Columnas visibles -->
                    <section class="tkt-section">
                        <h2 class="tkt-section-title">Columnas visibles</h2>

                        <div class="tkt-toggles">
                            <!-- Descripción -->
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_description" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Descripción del producto</span>
                                        <p class="tkt-toggle-hint">Muestra el texto descriptivo debajo del nombre del ítem.</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Bs. -->
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_bs" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Monto en Bolívares (Bs.)</span>
                                        <p class="tkt-toggle-hint">Imprime el subtotal de cada ítem y el total en Bolívares. Moneda principal de cobro al cliente.</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Divisas -->
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_usd" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Monto en divisas</span>
                                        <p class="tkt-toggle-hint">Añade referencia en USD o "REF" junto al monto en Bs. Útil para clientes que comparan precios en dólares.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Formato USD -->
                        <div v-if="form.show_usd" class="tkt-usd-format">
                            <label :class="labelClass">Formato divisas</label>
                            <div class="tkt-format-options">
                                <label class="tkt-format-opt" :class="{ 'tkt-format-opt--active': form.usd_format === 'usd' }">
                                    <input type="radio" v-model="form.usd_format" value="usd" class="sr-only" />
                                    <span class="tkt-format-symbol">$</span>
                                    <span class="tkt-format-name">Dólar ($ 8.00)</span>
                                </label>
                                <label class="tkt-format-opt" :class="{ 'tkt-format-opt--active': form.usd_format === 'ref' }">
                                    <input type="radio" v-model="form.usd_format" value="ref" class="sr-only" />
                                    <span class="tkt-format-symbol">REF</span>
                                    <span class="tkt-format-name">Referencia (REF 8.00)</span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Datos del negocio en ticket -->
                    <section class="tkt-section">
                        <h2 class="tkt-section-title">Datos del negocio en ticket</h2>
                        <div class="tkt-toggles">
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_address" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Dirección del negocio</span>
                                        <p class="tkt-toggle-hint">Imprime la dirección registrada en Configuración → General.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_phone" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Teléfono del negocio</span>
                                        <p class="tkt-toggle-hint">Imprime el teléfono registrado en Configuración → General.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_client" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Datos del cliente</span>
                                        <p class="tkt-toggle-hint">Muestra el nombre y teléfono del cliente si se ingresaron al cobrar.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_rif" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">RIF del negocio</span>
                                        <p class="tkt-toggle-hint">Imprime el RIF registrado en Configuración → General.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_cajero" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Nombre del cajero</span>
                                        <p class="tkt-toggle-hint">Muestra el nombre del usuario que procesó la venta.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_tasa" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Tasa BCV del día</span>
                                        <p class="tkt-toggle-hint">Imprime la tasa de cambio usada al momento de la venta.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="tkt-toggle-row" style="border-bottom: none; padding-bottom: 0;">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_metodo_pago" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Método de pago</span>
                                        <p class="tkt-toggle-hint">Imprime el método de pago utilizado (Efectivo, Transferencia, etc.).</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Visualización de kilogramos -->
                    <section class="tkt-section">
                        <h2 class="tkt-section-title">Kilogramos y cantidad</h2>

                        <div class="tkt-toggles">
                            <!-- Mostrar kg -->
                            <div class="tkt-toggle-row">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_kg" class="tkt-chk" />
                                    <div>
                                        <span class="tkt-toggle-label">Mostrar cantidad en kg por ítem</span>
                                        <p class="tkt-toggle-hint">Imprime el peso vendido junto a cada producto.<br>Ejemplo: <code>Lomo de res — 1.250 kg</code></p>
                                    </div>
                                </label>
                            </div>

                            <!-- Precio por kg -->
                            <div class="tkt-toggle-row" :class="{ 'tkt-toggle-row--disabled': !form.show_kg }">
                                <label class="tkt-toggle">
                                    <input type="checkbox" v-model="form.show_kg_unit_price" class="tkt-chk" :disabled="!form.show_kg" />
                                    <div>
                                        <span class="tkt-toggle-label">Mostrar precio por kg</span>
                                        <p class="tkt-toggle-hint">Agrega la cantidad × precio unitario en la línea del ítem.<br>Ejemplo: <code>1.250 kg × 520,00 Bs/kg</code><br>Solo disponible si "Mostrar kg" está activo.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Decimales -->
                        <div v-if="form.show_kg" class="tkt-kg-decimals">
                            <label :class="labelClass">Precisión del peso impreso</label>
                            <p class="tkt-toggle-hint" style="margin-bottom: 0.5rem">Define cuántos decimales aparecen en el peso. 3 decimales = precisión de gramo.</p>
                            <div class="tkt-format-options">
                                <label class="tkt-format-opt" :class="{ 'tkt-format-opt--active': form.kg_decimals === 3 }">
                                    <input type="radio" v-model.number="form.kg_decimals" :value="3" class="sr-only" />
                                    <span class="tkt-format-symbol">3</span>
                                    <span class="tkt-format-name">1.250 kg<br><small>Hasta gramo</small></span>
                                </label>
                                <label class="tkt-format-opt" :class="{ 'tkt-format-opt--active': form.kg_decimals === 2 }">
                                    <input type="radio" v-model.number="form.kg_decimals" :value="2" class="sr-only" />
                                    <span class="tkt-format-symbol">2</span>
                                    <span class="tkt-format-name">1.25 kg<br><small>Hasta 10 g</small></span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <!-- Pie de ticket -->
                    <section class="tkt-section">
                        <h2 class="tkt-section-title">Pie de página</h2>
                        <div>
                            <label :class="labelClass">Texto del pie (opcional)</label>
                            <textarea v-model="form.footer_text" :class="inputClass" rows="3" maxlength="200" placeholder="Gracias por su preferencia…" />
                            <p class="mt-1 text-xs text-[var(--text-muted)]">{{ form.footer_text.length }}/200 caracteres</p>
                        </div>
                    </section>

                    <div class="tkt-actions">
                        <button type="submit" class="btn btn-brand" :disabled="form.processing">
                            {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                        </button>
                    </div>

                </form>

                <!-- ── Vista previa ─────────────────────────────────────────── -->
                <div class="tkt-preview-wrap">
                    <h2 class="tkt-section-title">Vista previa del ticket</h2>
                    <p class="tkt-hint" style="margin-bottom:0.5rem">Así se verá al cajero al confirmar la venta.</p>

                    <div class="tkt-preview">
                        <!-- Cabecera negocio -->
                        <div class="tkt-prev-header">{{ business.name ?? 'Mi Negocio' }}</div>
                        <div v-if="business.city || business.state" class="tkt-prev-sub">
                            {{ [business.city, business.state].filter(Boolean).join(', ') }}
                        </div>
                        <div v-if="form.show_address && business.address" class="tkt-prev-sub">{{ business.address }}</div>
                        <div v-if="form.show_phone && business.phone" class="tkt-prev-sub">Tel: {{ business.phone }}</div>
                        <div v-if="form.show_rif && business.rif" class="tkt-prev-sub">RIF: {{ business.rif }}</div>
                        <div class="tkt-prev-sub">Ticket: {{ form.ticket_prefix }}-0001</div>
                        <div class="tkt-prev-sub">07/05/2026 18:48 · Caja Principal</div>
                        <div v-if="form.show_cajero" class="tkt-prev-sub">Cajero: Demo Cajera</div>
                        <div v-if="form.show_tasa" class="tkt-prev-sub">Tasa: Bs. 530,50/USD</div>
                        <div v-if="form.show_client" class="tkt-prev-sub">Cliente: Juan Pérez · 0424-0000000</div>

                        <hr class="tkt-prev-sep" />

                        <!-- Ítems -->
                        <div v-for="(item, i) in previewItems" :key="i" class="tkt-prev-item">
                            <div class="tkt-prev-row">
                                <div class="tkt-prev-item-left">
                                    <span class="tkt-prev-name">{{ item.name }}</span>
                                    <span v-if="item.description" class="tkt-prev-desc">{{ item.description }}</span>
                                    <span v-if="item.kg" class="tkt-prev-kg">{{ item.kg }}</span>
                                </div>
                                <div class="tkt-prev-item-right">
                                    <span v-if="item.bs"  class="tkt-prev-bs">{{ item.bs }}</span>
                                    <span v-if="item.usd" class="tkt-prev-usd">{{ item.usd }}</span>
                                </div>
                            </div>
                        </div>

                        <hr class="tkt-prev-sep" />

                        <!-- Total -->
                        <div class="tkt-prev-total">
                            <span>TOTAL:</span>
                            <span>{{ previewTotal }}</span>
                        </div>
                        <div v-if="form.show_metodo_pago" class="tkt-prev-sub" style="margin-top:0.2rem">Pago: Efectivo</div>

                        <div v-if="form.footer_text" class="tkt-prev-footer">{{ form.footer_text }}</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Panel de ayuda ───────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Ticket — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </SettingsLayout>
</template>

<style scoped>
.settings-panel__header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); }
.header-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.help-btn {
    border-radius: 50%;
    width: 28px; height: 28px;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700;
    border: 1.5px solid var(--border);
    background: none; color: var(--text-muted);
    cursor: pointer; flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.tkt-layout {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 2rem;
    align-items: start;
}
@media (max-width: 768px) {
    .tkt-layout { grid-template-columns: 1fr; }
}

.tkt-form { display: flex; flex-direction: column; gap: 1.5rem; }

.tkt-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.tkt-section-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0;
}
.tkt-hint { font-size: 0.8rem; color: var(--text-muted); margin: 0; }

.tkt-toggles { display: flex; flex-direction: column; gap: 0; }
.tkt-toggle-row {
    padding: 0.75rem 0;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 60%, transparent);
}
.tkt-toggle-row:last-child { border-bottom: none; }
.tkt-toggle-row--disabled { opacity: 0.45; pointer-events: none; }
.tkt-toggle {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
}
.tkt-chk {
    width: 1rem;
    height: 1rem;
    margin-top: 0.15rem;
    flex-shrink: 0;
    accent-color: var(--brand);
    cursor: pointer;
}
.tkt-toggle-label { font-size: 0.875rem; font-weight: 500; color: var(--text-primary); display: block; }
.tkt-toggle-hint {
    font-size: 0.775rem;
    color: var(--text-muted);
    margin: 0.2rem 0 0;
    line-height: 1.5;
}
.tkt-toggle-hint code {
    font-family: 'Courier New', monospace;
    font-size: 0.72rem;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    color: var(--brand);
    padding: 0.05rem 0.3rem;
    border-radius: 3px;
}
.tkt-kg-decimals { padding-top: 0.75rem; border-top: 1px solid var(--border); }

.tkt-usd-format { padding-top: 0.5rem; border-top: 1px solid var(--border); }
.tkt-format-options { display: flex; gap: 0.75rem; margin-top: 0.5rem; }
.tkt-format-opt {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.tkt-format-opt--active {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 10%, transparent);
}
.tkt-format-symbol { font-size: 1.125rem; font-weight: 700; color: var(--text-primary); }
.tkt-format-name   { font-size: 0.75rem; color: var(--text-secondary); text-align: center; }

.tkt-actions { display: flex; justify-content: flex-end; }

/* ── Vista previa ─────────────────────────────────────────────── */
.tkt-preview-wrap {
    position: sticky;
    top: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.tkt-preview {
    background: #fff;
    color: #111;
    border-radius: 0.5rem;
    padding: 1rem 0.875rem;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    line-height: 1.6;
    min-height: 200px;
    border: 1px solid var(--border);
}
.tkt-prev-header { font-weight: 700; font-size: 0.8rem; text-align: center; text-transform: uppercase; }
.tkt-prev-sub    { text-align: center; color: #666; font-size: 0.67rem; margin-top: 0.05rem; }
.tkt-prev-sep    { border: none; border-top: 1px dashed #bbb; margin: 0.45rem 0; }

.tkt-prev-item   { padding: 0.2rem 0; }
.tkt-prev-row    { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
.tkt-prev-item-left  { display: flex; flex-direction: column; gap: 0.05rem; flex: 1; }
.tkt-prev-item-right { display: flex; flex-direction: column; align-items: flex-end; gap: 0.05rem; }
.tkt-prev-name   { font-weight: 600; font-size: 0.72rem; color: #111; }
.tkt-prev-desc   { font-size: 0.67rem; color: #666; }
.tkt-prev-kg     { font-size: 0.67rem; color: #444; }
.tkt-prev-usd    { font-size: 0.67rem; color: #888; white-space: nowrap; }
.tkt-prev-bs     { font-weight: 600; font-size: 0.72rem; color: #111; white-space: nowrap; }

.tkt-prev-total {
    display: flex;
    justify-content: space-between;
    font-weight: 700;
    font-size: 0.78rem;
    color: #111;
    padding-top: 0.1rem;
}
.tkt-prev-footer { text-align: center; color: #777; font-size: 0.67rem; margin-top: 0.5rem; }
</style>
