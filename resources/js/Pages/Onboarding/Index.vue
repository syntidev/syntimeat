<script setup>
import { ref, computed } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'

const props = defineProps({
    currentStep: {
        type: Number,
        default: 1,
    },
})

const step = ref(props.currentStep)

// ─── Forms por paso ───────────────────────────────────────────────────────────

const form1 = useForm({
    name: '',
    legal_name: '',
    rif: '',
    city: '',
    state: '',
    phone: '',
})

const form2 = useForm({
    currency_default: 'USD',
    rate_source: 'bcv',
    rate_margin: 0,
    weight_unit: 'kg',
})

const form3 = useForm({
    ticket_prefix: 'VEN',
    ticket_footer: '',
    payment_methods: [],
})

const form4 = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const forms = { 1: form1, 2: form2, 3: form3, 4: form4 }
const currentForm = computed(() => forms[step.value])
const isLastStep  = computed(() => step.value === 4)

// ─── Constantes UI ────────────────────────────────────────────────────────────

const stepLabels = ['Datos del Negocio', 'Moneda y Tasa', 'Tickets y Cobros', 'Administrador']

const currencyOptions = [
    { value: 'USD',   label: 'USD — Dólares' },
    { value: 'VES',   label: 'VES — Bolívares' },
    { value: 'mixed', label: 'Mixto (USD + VES)' },
]

const rateSourceOptions = [
    { value: 'bcv',        label: 'Tasa BCV (Banco Central)' },
    { value: 'parallel',   label: 'Tasa Paralela' },
    { value: 'negotiated', label: 'Negociada' },
    { value: 'manual',     label: 'Manual' },
]

const weightOptions = [
    { value: 'kg', label: 'kg' },
    { value: 'lb', label: 'lb' },
    { value: 'g',  label: 'g'  },
]

const paymentOptions = [
    { value: 'cash',           label: 'Efectivo (Bs.)' },
    { value: 'usd_cash',       label: 'Efectivo (USD)' },
    { value: 'mobile_payment', label: 'Pago Móvil' },
    { value: 'transfer',       label: 'Transferencia Bancaria' },
    { value: 'pos_debit',      label: 'Débito/Crédito (POS)' },
    { value: 'zelle',          label: 'Zelle' },
    { value: 'biopago',        label: 'BioPago' },
    { value: 'paypal',         label: 'PayPal' },
    { value: 'binance',        label: 'Binance (Cripto)' },
    { value: 'cashea',         label: 'Cashea' },
]

// ─── Acciones ─────────────────────────────────────────────────────────────────

const submit = () => {
    currentForm.value.post(route('onboarding.step', step.value), {
        onSuccess: () => {
            if (step.value < 4) step.value++
        },
    })
}

const prev = () => {
    if (step.value > 1) step.value--
}

// ─── Helpers de estilo ────────────────────────────────────────────────────────

const inputClass = 'w-full bg-[#0b1829] border border-[#243650] text-[#EEF2FF] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[#3a5272] focus:border-[#2563EB] focus:ring-1 focus:ring-[#2563EB] outline-none transition'

const selectClass = 'w-full bg-[#0b1829] border border-[#243650] text-[#EEF2FF] rounded-lg px-3.5 py-2.5 text-sm focus:border-[#2563EB] focus:ring-1 focus:ring-[#2563EB] outline-none transition'

const labelClass = 'block text-xs font-medium text-[#93aed4] mb-1.5'

const errorClass = 'mt-1 text-xs text-red-400'
</script>

<template>
    <Head title="Configuración Inicial — SYNTImeat">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="min-h-screen bg-[#0F1E35] flex flex-col items-center justify-center px-4 py-12"
        style="font-family: 'Plus Jakarta Sans', sans-serif"
    >
        <!-- Logo -->
        <div class="mb-8 text-center select-none">
            <div class="text-3xl font-bold tracking-tight text-[#EEF2FF]">
                SYNTI<span class="text-[#2563EB]">meat</span>
            </div>
            <p class="mt-1 text-sm text-[#4a6585]">Configuración inicial del sistema</p>
        </div>

        <!-- Card -->
        <div class="w-full max-w-lg bg-[#132035] rounded-2xl shadow-2xl shadow-black/40 overflow-hidden">

            <!-- Stepper -->
            <div class="px-8 pt-7 pb-6 border-b border-[#1e3250]">
                <div class="flex items-start">
                    <template v-for="i in 4" :key="i">
                        <!-- Círculo + label -->
                        <div class="flex flex-col items-center" style="min-width: 56px">
                            <div
                                :class="[
                                    'w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300',
                                    step > i
                                        ? 'bg-[#2563EB] text-white'
                                        : step === i
                                            ? 'border-2 border-[#2563EB] text-[#2563EB] bg-transparent'
                                            : 'bg-[#1a2d4a] text-[#4a6585]',
                                ]"
                            >
                                <svg
                                    v-if="step > i"
                                    class="w-4 h-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span v-else>{{ i }}</span>
                            </div>
                            <span
                                :class="[
                                    'mt-1.5 text-[10px] font-medium text-center leading-tight',
                                    step === i
                                        ? 'text-[#2563EB]'
                                        : step > i
                                            ? 'text-[#6b8db3]'
                                            : 'text-[#3a5272]',
                                ]"
                                style="max-width: 56px"
                            >
                                {{ stepLabels[i - 1] }}
                            </span>
                        </div>
                        <!-- Conector -->
                        <div
                            v-if="i < 4"
                            :class="[
                                'flex-1 h-px mt-4 mx-1 transition-all duration-500',
                                step > i ? 'bg-[#2563EB]' : 'bg-[#1e3250]',
                            ]"
                        />
                    </template>
                </div>
            </div>

            <!-- Contenido -->
            <form @submit.prevent="submit" novalidate>
                <div class="px-8 py-7">

                    <!-- ── Paso 1: Datos del Negocio ──────────────────────────── -->
                    <template v-if="step === 1">
                        <h2 class="text-base font-semibold text-[#EEF2FF] mb-0.5">Datos del Negocio</h2>
                        <p class="text-xs text-[#4a6585] mb-6">Información básica de tu establecimiento</p>

                        <div class="space-y-4">
                            <div>
                                <label :class="labelClass">Nombre del negocio <span class="text-[#2563EB]">*</span></label>
                                <input
                                    v-model="form1.name"
                                    type="text"
                                    :class="inputClass"
                                    placeholder="Ej: Carnicería El Buen Corte"
                                    autofocus
                                />
                                <p v-if="form1.errors.name" :class="errorClass">{{ form1.errors.name }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label :class="labelClass">Razón social</label>
                                    <input v-model="form1.legal_name" type="text" :class="inputClass" placeholder="Opcional" />
                                    <p v-if="form1.errors.legal_name" :class="errorClass">{{ form1.errors.legal_name }}</p>
                                </div>
                                <div>
                                    <label :class="labelClass">RIF</label>
                                    <input v-model="form1.rif" type="text" :class="inputClass" placeholder="J-12345678-9" />
                                    <p v-if="form1.errors.rif" :class="errorClass">{{ form1.errors.rif }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label :class="labelClass">Ciudad <span class="text-[#2563EB]">*</span></label>
                                    <input v-model="form1.city" type="text" :class="inputClass" placeholder="Ej: Caracas" />
                                    <p v-if="form1.errors.city" :class="errorClass">{{ form1.errors.city }}</p>
                                </div>
                                <div>
                                    <label :class="labelClass">Estado <span class="text-[#2563EB]">*</span></label>
                                    <input v-model="form1.state" type="text" :class="inputClass" placeholder="Ej: Miranda" />
                                    <p v-if="form1.errors.state" :class="errorClass">{{ form1.errors.state }}</p>
                                </div>
                            </div>

                            <div>
                                <label :class="labelClass">Teléfono</label>
                                <input v-model="form1.phone" type="tel" :class="inputClass" placeholder="+58 412-0000000" />
                                <p v-if="form1.errors.phone" :class="errorClass">{{ form1.errors.phone }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- ── Paso 2: Moneda y Tasa ──────────────────────────────── -->
                    <template v-if="step === 2">
                        <h2 class="text-base font-semibold text-[#EEF2FF] mb-0.5">Moneda y Tasa de Cambio</h2>
                        <p class="text-xs text-[#4a6585] mb-6">Define cómo se manejan precios y conversiones</p>

                        <div class="space-y-4">
                            <div>
                                <label :class="labelClass">Moneda predeterminada <span class="text-[#2563EB]">*</span></label>
                                <select v-model="form2.currency_default" :class="selectClass">
                                    <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p v-if="form2.errors.currency_default" :class="errorClass">{{ form2.errors.currency_default }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">Fuente de tasa de cambio <span class="text-[#2563EB]">*</span></label>
                                <select v-model="form2.rate_source" :class="selectClass">
                                    <option v-for="opt in rateSourceOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p v-if="form2.errors.rate_source" :class="errorClass">{{ form2.errors.rate_source }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">
                                    Margen sobre tasa (%)
                                    <span class="text-[#3a5272] font-normal ml-1">— aplicado sobre la tasa base</span>
                                </label>
                                <input
                                    v-model="form2.rate_margin"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="99.99"
                                    :class="inputClass"
                                    placeholder="0"
                                />
                                <p v-if="form2.errors.rate_margin" :class="errorClass">{{ form2.errors.rate_margin }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">Unidad de peso predeterminada <span class="text-[#2563EB]">*</span></label>
                                <div class="flex gap-2">
                                    <label
                                        v-for="opt in weightOptions"
                                        :key="opt.value"
                                        :class="[
                                            'flex-1 border rounded-lg py-2.5 text-center text-sm font-semibold cursor-pointer transition',
                                            form2.weight_unit === opt.value
                                                ? 'border-[#2563EB] bg-[#2563EB]/10 text-[#2563EB]'
                                                : 'border-[#243650] bg-[#0b1829] text-[#6b8db3] hover:border-[#3a5272]',
                                        ]"
                                    >
                                        <input type="radio" v-model="form2.weight_unit" :value="opt.value" class="sr-only" />
                                        {{ opt.label }}
                                    </label>
                                </div>
                                <p v-if="form2.errors.weight_unit" :class="errorClass">{{ form2.errors.weight_unit }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- ── Paso 3: Tickets y Cobros ───────────────────────────── -->
                    <template v-if="step === 3">
                        <h2 class="text-base font-semibold text-[#EEF2FF] mb-0.5">Tickets y Métodos de Cobro</h2>
                        <p class="text-xs text-[#4a6585] mb-6">Personaliza tus tickets e indica cómo recibes pagos</p>

                        <div class="space-y-5">
                            <div>
                                <label :class="labelClass">
                                    Prefijo de ticket <span class="text-[#2563EB]">*</span>
                                    <span class="text-[#3a5272] font-normal ml-1">— máx. 10 caracteres</span>
                                </label>
                                <input
                                    v-model="form3.ticket_prefix"
                                    type="text"
                                    maxlength="10"
                                    :class="inputClass"
                                    placeholder="VEN"
                                    style="text-transform: uppercase"
                                />
                                <p class="mt-1 text-[10px] text-[#3a5272]">
                                    Vista previa: <span class="text-[#93aed4] font-medium">{{ form3.ticket_prefix || 'VEN' }}-0001</span>
                                </p>
                                <p v-if="form3.errors.ticket_prefix" :class="errorClass">{{ form3.errors.ticket_prefix }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">Pie de ticket</label>
                                <textarea
                                    v-model="form3.ticket_footer"
                                    rows="2"
                                    :class="inputClass + ' resize-none'"
                                    placeholder="Ej: Gracias por su compra. ¡Vuelva pronto!"
                                />
                                <p v-if="form3.errors.ticket_footer" :class="errorClass">{{ form3.errors.ticket_footer }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">
                                    Métodos de pago aceptados <span class="text-[#2563EB]">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label
                                        v-for="method in paymentOptions"
                                        :key="method.value"
                                        :class="[
                                            'flex items-center gap-2.5 border rounded-lg px-3.5 py-2.5 cursor-pointer transition select-none',
                                            form3.payment_methods.includes(method.value)
                                                ? 'border-[#2563EB] bg-[#2563EB]/10'
                                                : 'border-[#243650] bg-[#0b1829] hover:border-[#3a5272]',
                                        ]"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="form3.payment_methods"
                                            :value="method.value"
                                            class="sr-only"
                                        />
                                        <!-- Custom checkbox -->
                                        <div
                                            :class="[
                                                'w-4 h-4 rounded border-2 flex items-center justify-center flex-shrink-0 transition',
                                                form3.payment_methods.includes(method.value)
                                                    ? 'border-[#2563EB] bg-[#2563EB]'
                                                    : 'border-[#4a6585]',
                                            ]"
                                        >
                                            <svg
                                                v-if="form3.payment_methods.includes(method.value)"
                                                class="w-2.5 h-2.5 text-white"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <span
                                            :class="[
                                                'text-xs font-medium',
                                                form3.payment_methods.includes(method.value)
                                                    ? 'text-[#60a5fa]'
                                                    : 'text-[#93aed4]',
                                            ]"
                                        >
                                            {{ method.label }}
                                        </span>
                                    </label>
                                </div>
                                <p v-if="form3.errors.payment_methods" :class="errorClass">
                                    {{ form3.errors.payment_methods }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- ── Paso 4: Administrador ──────────────────────────────── -->
                    <template v-if="step === 4">
                        <h2 class="text-base font-semibold text-[#EEF2FF] mb-0.5">Crear Administrador</h2>
                        <p class="text-xs text-[#4a6585] mb-6">Este usuario tendrá acceso completo al sistema</p>

                        <div class="space-y-4">
                            <div>
                                <label :class="labelClass">Nombre completo <span class="text-[#2563EB]">*</span></label>
                                <input
                                    v-model="form4.name"
                                    type="text"
                                    :class="inputClass"
                                    placeholder="Ej: Juan Pérez"
                                    autofocus
                                />
                                <p v-if="form4.errors.name" :class="errorClass">{{ form4.errors.name }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">Correo electrónico <span class="text-[#2563EB]">*</span></label>
                                <input
                                    v-model="form4.email"
                                    type="email"
                                    :class="inputClass"
                                    placeholder="admin@tunegocio.com"
                                    autocomplete="username"
                                />
                                <p class="mt-1 text-[11px] text-[#4a6585]">
                                    Si ya tienes una cuenta registrada, usa ese mismo correo y tu contraseña actual.
                                </p>
                                <p v-if="form4.errors.email" :class="errorClass">{{ form4.errors.email }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">Contraseña <span class="text-[#2563EB]">*</span></label>
                                <input
                                    v-model="form4.password"
                                    type="password"
                                    :class="inputClass"
                                    placeholder="Mínimo 8 caracteres"
                                    autocomplete="new-password"
                                />
                                <p v-if="form4.errors.password" :class="errorClass">{{ form4.errors.password }}</p>
                            </div>

                            <div>
                                <label :class="labelClass">Confirmar contraseña <span class="text-[#4a6585] font-normal">(solo si es cuenta nueva)</span></label>
                                <input
                                    v-model="form4.password_confirmation"
                                    type="password"
                                    :class="inputClass"
                                    placeholder="Repite la contraseña"
                                    autocomplete="new-password"
                                />
                            </div>

                            <!-- Info box -->
                            <div class="flex items-start gap-2.5 bg-[#2563EB]/10 border border-[#2563EB]/25 rounded-lg px-4 py-3">
                                <svg class="w-4 h-4 text-[#60a5fa] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-xs text-[#93aed4] leading-relaxed">
                                    Al finalizar, serás redirigido al panel de control e ingresarás automáticamente. Si ya tenías una cuenta, quedará vinculada como administrador de este negocio.
                                </p>
                            </div>
                        </div>
                    </template>

                </div>

                <!-- Navegación -->
                <div class="px-8 pb-8 flex items-center justify-between">
                    <!-- Anterior -->
                    <button
                        v-if="step > 1"
                        type="button"
                        @click="prev"
                        class="flex items-center gap-1.5 text-sm text-[#6b8db3] hover:text-[#EEF2FF] transition font-medium"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Anterior
                    </button>
                    <div v-else />

                    <!-- Siguiente / Finalizar -->
                    <button
                        type="submit"
                        :disabled="currentForm.processing"
                        class="flex items-center gap-2 px-6 py-2.5 bg-[#2563EB] hover:bg-[#1d4ed8] active:bg-[#1e40af] disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition"
                    >
                        <!-- Spinner -->
                        <svg
                            v-if="currentForm.processing"
                            class="w-4 h-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>

                        <span>{{ isLastStep ? 'Finalizar' : 'Siguiente' }}</span>

                        <!-- Icono de flecha o check -->
                        <svg
                            v-if="!currentForm.processing && !isLastStep"
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <svg
                            v-if="!currentForm.processing && isLastStep"
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <p class="mt-6 text-[10px] text-[#2a3f5f]">SYNTImeat &copy; {{ new Date().getFullYear() }}</p>
    </div>
</template>

<style>
/* Dark styling para options de select en navegadores que lo soporten */
select option {
    background-color: #0b1829;
    color: #EEF2FF;
}
</style>
