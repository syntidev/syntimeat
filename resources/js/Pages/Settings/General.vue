<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import HelpModal      from '@/Components/HelpModal.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    business: Object,
})

const form = useForm({
    name:        props.business.name        ?? '',
    legal_name:  props.business.legal_name  ?? '',
    rif:         props.business.rif         ?? '',
    phone:       props.business.phone       ?? '',
    address:     props.business.address     ?? '',
    city:        props.business.city        ?? '',
    state:       props.business.state       ?? '',
    logo:        null,
    theme_color: props.business.theme_color ?? 'blue',
})

const palettes = [
    { key: 'blue',   label: 'Azul',    color: '#2563EB' },
    { key: 'green',  label: 'Verde',   color: '#059669' },
    { key: 'red',    label: 'Rojo',    color: '#DC2626' },
    { key: 'orange', label: 'Naranja', color: '#EA580C' },
    { key: 'purple', label: 'Violeta', color: '#7C3AED' },
    { key: 'teal',   label: 'Cian',    color: '#0891B2' },
]

const logoPreview = ref(
    props.business.logo_path
        ? `/storage/${props.business.logo_path}`
        : null
)

function onLogoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.logo = file
    logoPreview.value = URL.createObjectURL(file)
}

function submit() {
    form.post(route('settings.general.update'), {
        forceFormData: true,
    })
}

const inputClass = 'w-full bg-[var(--bg-input)] border border-[var(--border)] text-[var(--text-primary)] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[var(--text-muted)] focus:border-[var(--brand)] focus:ring-1 focus:ring-[var(--brand)] outline-none transition'
const labelClass = 'block text-sm font-medium text-[var(--text-secondary)] mb-1.5'
const errorClass = 'mt-1 text-xs text-red-400'

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Datos del negocio',
        body:  'Registra el nombre comercial, razón social, RIF y teléfono de tu establecimiento. Estos datos aparecen en los tickets y reportes del sistema.',
        tip:   'El campo "Nombre del negocio" es obligatorio y es lo que verán tus cajeros al iniciar sesión.',
    },
    {
        title: 'Logo y color de interfaz',
        body:  'Sube el logo de tu negocio (PNG o JPG, máx. 2 MB) y elige el color principal de la interfaz. El color se aplica a botones, íconos activos y acentos de toda la aplicación.',
        tip:   'El logo aparece en la pantalla de bienvenida. El cambio de color es instantáneo para todos los usuarios.',
    },
    {
        title: 'Ubicación',
        body:  'Ingresa la dirección, ciudad y estado del negocio. Estos datos pueden mostrarse en los tickets impresos si lo configuras en Configuración → Ticket.',
        tip:   'Ciudad y estado son campos obligatorios. La dirección completa es opcional pero aparece en el ticket si la activas.',
    },
]

const helpFaqs = [
    {
        q: '¿El RIF aparece en los tickets al cliente?',
        a: 'No automáticamente. Por ahora el ticket muestra el nombre del negocio. El RIF se usa internamente para reportes y documentos administrativos.',
    },
    {
        q: '¿Puedo cambiar el color de la interfaz después?',
        a: 'Sí, en cualquier momento. El cambio se aplica de inmediato en toda la aplicación para todos los usuarios sin necesidad de recargar.',
    },
    {
        q: '¿El logo se imprime en los tickets?',
        a: 'El logo se muestra en la interfaz del sistema. La impresión del logo en tickets depende de la impresora y la configuración en Configuración → Ticket.',
    },
]
</script>

<template>
    <SettingsLayout>
        <div class="settings-panel">

            <!-- Header -->
            <div class="settings-panel__header">
                <div class="header-row">
                    <div>
                        <h2 class="settings-panel__title">Mi Negocio</h2>
                        <p class="settings-panel__subtitle">Información general de tu establecimiento</p>
                    </div>
                    <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
                </div>
            </div>

            <form @submit.prevent="submit" class="settings-panel__body" enctype="multipart/form-data">

                <!-- Logo -->
                <div class="logo-section">
                    <div class="logo-preview">
                        <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="logo-preview__img" />
                        <div v-else class="logo-preview__placeholder">
                            <svg class="w-8 h-8 text-[var(--text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label class="btn-secondary cursor-pointer">
                            <input type="file" accept="image/*" class="sr-only" @change="onLogoChange" />
                            Cambiar logo
                        </label>
                        <p class="mt-1 text-[11px] text-[var(--text-muted)]">PNG, JPG hasta 2 MB</p>
                        <p v-if="form.errors.logo" :class="errorClass">{{ form.errors.logo }}</p>
                    </div>
                </div>

                <hr class="divider" />

                <!-- Nombre y datos fiscales -->
                <div class="form-grid">
                    <div class="col-span-2">
                        <label :class="labelClass">Nombre del negocio <span class="text-[var(--brand)]">*</span></label>
                        <input v-model="form.name" type="text" :class="inputClass" placeholder="Ej: Mi Negocio" />
                        <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label :class="labelClass">Razón social</label>
                        <input v-model="form.legal_name" type="text" :class="inputClass" placeholder="Opcional" />
                        <p v-if="form.errors.legal_name" :class="errorClass">{{ form.errors.legal_name }}</p>
                    </div>

                    <div>
                        <label :class="labelClass">RIF</label>
                        <input v-model="form.rif" type="text" :class="inputClass" placeholder="J-12345678-9" />
                        <p v-if="form.errors.rif" :class="errorClass">{{ form.errors.rif }}</p>
                    </div>

                    <div class="col-span-2">
                        <label :class="labelClass">Teléfono</label>
                        <input v-model="form.phone" type="tel" :class="inputClass" placeholder="+58 412-0000000" />
                        <p v-if="form.errors.phone" :class="errorClass">{{ form.errors.phone }}</p>
                    </div>
                </div>

                <hr class="divider" />

                <!-- Ubicación -->
                <h3 class="section-subtitle">Ubicación</h3>
                <div class="form-grid">
                    <div class="col-span-2">
                        <label :class="labelClass">Dirección</label>
                        <input v-model="form.address" type="text" :class="inputClass" placeholder="Av. Principal, Local 1" />
                        <p v-if="form.errors.address" :class="errorClass">{{ form.errors.address }}</p>
                    </div>

                    <div>
                        <label :class="labelClass">Ciudad <span class="text-[var(--brand)]">*</span></label>
                        <input v-model="form.city" type="text" :class="inputClass" placeholder="Caracas" />
                        <p v-if="form.errors.city" :class="errorClass">{{ form.errors.city }}</p>
                    </div>

                    <div>
                        <label :class="labelClass">Estado <span class="text-[var(--brand)]">*</span></label>
                        <input v-model="form.state" type="text" :class="inputClass" placeholder="Miranda" />
                        <p v-if="form.errors.state" :class="errorClass">{{ form.errors.state }}</p>
                    </div>
                </div>

                <!-- Guardar -->
                <hr style="border-color: var(--border); margin: 0;" />

                <!-- Paleta de color -->
                <h3 class="section-subtitle">Color de la interfaz</h3>
                <div class="palette-row">
                    <button
                        v-for="p in palettes"
                        :key="p.key"
                        type="button"
                        :title="p.label"
                        :class="['palette-swatch', { 'palette-swatch--active': form.theme_color === p.key }]"
                        :style="{ background: p.color }"
                        @click="form.theme_color = p.key"
                    >
                        <svg v-if="form.theme_color === p.key" class="w-4 h-4 text-white drop-shadow" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>

                <!-- Guardar -->
                <div class="form-footer">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Guardar cambios
                    </button>

                    <p v-if="$page.props.flash?.success" class="text-sm text-green-400">
                        {{ $page.props.flash.success }}
                    </p>
                </div>

            </form>
        </div>

        <!-- Panel de ayuda ───────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Mi Negocio — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </SettingsLayout>
</template>

<style scoped>
.settings-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.settings-panel__header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); }
.settings-panel__title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.settings-panel__subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }
.header-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.help-btn {
    border-radius: 50%;
    width: 44px; height: 44px;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700;
    border: 1.5px solid var(--border);
    background: none; color: var(--text-muted);
    cursor: pointer; flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.settings-panel__body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.logo-section { display: flex; align-items: center; gap: 1.25rem; }
.logo-preview { width: 72px; height: 72px; border-radius: 10px; border: 1px solid var(--border); overflow: hidden; flex-shrink: 0; background: var(--bg-input); display: flex; align-items: center; justify-content: center; }
.logo-preview__img { width: 100%; height: 100%; object-fit: cover; }
.logo-preview__placeholder { display: flex; align-items: center; justify-content: center; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.col-span-2 { grid-column: span 2; }
.divider { border-color: var(--border); margin: 0; }
.section-subtitle { font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); }
.form-footer { display: flex; align-items: center; gap: 1rem; padding-top: 0.25rem; }
.palette-row { display: flex; gap: 0.75rem; flex-wrap: wrap; padding-bottom: 0.5rem; }
.palette-swatch { width: 44px; height: 44px; border-radius: 50%; border: 3px solid transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.15s, border-color 0.15s; }
.palette-swatch:hover { transform: scale(1.1); }
.palette-swatch--active { border-color: var(--text-primary); }
.btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; background: var(--brand); color: #fff; font-size: 0.875rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: opacity 0.15s; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-secondary { display: inline-flex; align-items: center; min-height: 44px; padding: 0.4rem 0.875rem; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-secondary); font-size: 0.8125rem; font-weight: 500; border-radius: 7px; cursor: pointer; transition: border-color 0.15s; }
.btn-secondary:hover { border-color: var(--brand); }

/* ─── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
    .settings-panel__body { padding: 1.25rem 1rem; }
    /* Form grid: 1 col en tablet */
    .form-grid { grid-template-columns: 1fr; }
    .col-span-2 { grid-column: span 1; }
    /* Logo section: apila */
    .logo-section { flex-direction: column; align-items: flex-start; }
    /* Palette: scroll horizontal si hay muchos swatches */
    .palette-row { overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
    /* Form footer: apila */
    .form-footer { flex-direction: column; align-items: flex-start; }
}

@media (max-width: 640px) {
    .settings-panel__body { padding: 1rem 0.75rem; }
    /* Header row apila */
    .header-row { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    /* Touch targets */
    .btn-primary, .btn-secondary { min-height: 44px; }
}
</style>
