<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    branches: { type: Array, default: () => [] },
})

const showModal  = ref(false)
const editBranch = ref(null)

const form = useForm({
    name:    '',
    address: '',
    city:    '',
    phone:   '',
})

function openNew() {
    editBranch.value = null
    form.reset()
    showModal.value = true
}

function openEdit(branch) {
    editBranch.value = branch
    form.name    = branch.name
    form.address = branch.address ?? ''
    form.city    = branch.city    ?? ''
    form.phone   = branch.phone   ?? ''
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submit() {
    if (editBranch.value) {
        form.put(route('settings.branches.update', editBranch.value.id), {
            onSuccess: closeModal,
        })
    } else {
        form.post(route('settings.branches.store'), {
            onSuccess: closeModal,
        })
    }
}

function toggleActive(branch) {
    useForm({ is_active: !branch.is_active, name: branch.name }).put(
        route('settings.branches.update', branch.id),
        { preserveScroll: true }
    )
}

const inputClass = 'w-full bg-[var(--bg-input)] border border-[var(--border)] text-[var(--text-primary)] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[var(--text-muted)] focus:border-[var(--brand)] focus:ring-1 focus:ring-[var(--brand)] outline-none transition'
const labelClass = 'block text-xs font-medium text-[var(--text-secondary)] mb-1.5'
const errorClass = 'mt-1 text-xs text-red-400'
</script>

<template>
    <SettingsLayout>
        <div class="settings-panel">

            <!-- Header -->
            <div class="settings-panel__header">
                <div class="header-row">
                    <div>
                        <h2 class="settings-panel__title">Sucursales</h2>
                        <p class="settings-panel__subtitle">Administra las sucursales de tu negocio</p>
                    </div>
                    <button class="btn-primary" @click="openNew">+ Nueva sucursal</button>
                </div>
            </div>

            <!-- Lista -->
            <div class="panel-body">
                <div v-if="branches.length === 0" class="empty-state">
                    <p>No hay sucursales registradas. Agrega la primera sucursal.</p>
                </div>

                <div v-else class="branch-list">
                    <div v-for="b in branches" :key="b.id" class="branch-card">
                        <div class="branch-card__icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                            </svg>
                        </div>
                        <div class="branch-card__info">
                            <span class="branch-card__name">{{ b.name }}</span>
                            <span class="branch-card__detail" v-if="b.city || b.address">
                                {{ [b.city, b.address].filter(Boolean).join(' — ') }}
                            </span>
                            <span class="branch-card__detail" v-if="b.phone">Tel: {{ b.phone }}</span>
                        </div>
                        <div class="branch-card__actions">
                            <span :class="['status-badge', b.is_active ? 'status-active' : 'status-inactive']">
                                {{ b.is_active ? 'Activa' : 'Inactiva' }}
                            </span>
                            <button class="btn-icon" title="Editar" @click="openEdit(b)">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            </button>
                            <button class="btn-icon" :title="b.is_active ? 'Desactivar' : 'Activar'" @click="toggleActive(b)">
                                <svg v-if="b.is_active" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                <svg v-else width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m-4-4h8"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3 class="modal-title">{{ editBranch ? 'Editar Sucursal' : 'Nueva Sucursal' }}</h3>
                        <button class="btn-close" @click="closeModal">✕</button>
                    </div>

                    <form class="modal-form" @submit.prevent="submit">
                        <div>
                            <label :class="labelClass">Nombre <span class="text-red-400">*</span></label>
                            <input v-model="form.name" :class="inputClass" type="text" placeholder="Ej: Sucursal Centro" required />
                            <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Ciudad</label>
                            <input v-model="form.city" :class="inputClass" type="text" placeholder="Ej: Valle de la Pascua" />
                        </div>
                        <div>
                            <label :class="labelClass">Dirección</label>
                            <input v-model="form.address" :class="inputClass" type="text" placeholder="Av. Principal…" />
                        </div>
                        <div>
                            <label :class="labelClass">Teléfono</label>
                            <input v-model="form.phone" :class="inputClass" type="tel" placeholder="0424-0000000" />
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing">
                                {{ editBranch ? 'Guardar cambios' : 'Crear sucursal' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
.settings-panel {
    display: flex;
    flex-direction: column;
}

.settings-panel__header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.settings-panel__title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.settings-panel__subtitle {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
}

.panel-body {
    padding: 1.5rem;
}

.empty-state {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem;
    font-size: 0.875rem;
}

.branch-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.branch-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
}

.branch-card__icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.branch-card__icon svg {
    width: 1.1rem;
    height: 1.1rem;
}

.branch-card__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.branch-card__name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}

.branch-card__detail {
    font-size: 0.78rem;
    color: var(--text-muted);
}

.branch-card__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.status-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
}

.status-active {
    background: color-mix(in srgb, #10B981 15%, transparent);
    color: #10B981;
}

.status-inactive {
    background: color-mix(in srgb, #6B7280 15%, transparent);
    color: #6B7280;
}

.btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: 1px solid var(--border);
    border-radius: 0.4rem;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}

.btn-icon:hover {
    background: var(--bg-card);
    color: var(--text-primary);
}

.btn-primary {
    background: var(--brand);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.5rem 1.1rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: opacity 0.15s;
    min-height: 44px;
}

.btn-primary:hover   { opacity: 0.85; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary {
    background: transparent;
    color: var(--text-muted);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 1.1rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
}

.btn-secondary:hover { background: var(--bg-base); color: var(--text-primary); }

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
}

.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    width: 100%;
    max-width: 440px;
    overflow: hidden;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border);
}

.modal-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.btn-close {
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1rem;
    cursor: pointer;
}

.modal-form {
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
    margin-top: 0.25rem;
}
</style>
