<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    registers: Array,
    branches:  Array,
})

const showModal    = ref(false)
const editRegister = ref(null)

const form = useForm({ name: '', branch_id: null })

function openNew() {
    editRegister.value = null
    form.reset()
    showModal.value = true
}

function openEdit(reg) {
    editRegister.value = reg
    form.name      = reg.name
    form.branch_id = reg.branch_id
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submit() {
    if (editRegister.value) {
        form.put(route('settings.cash-registers.update', editRegister.value.id), { onSuccess: closeModal })
    } else {
        form.post(route('settings.cash-registers.store'), { onSuccess: closeModal })
    }
}

function destroy(reg) {
    if (!confirm(`¿Eliminar la caja "${reg.name}"?`)) return
    useForm({}).delete(route('settings.cash-registers.destroy', reg.id))
}

function branchName(id) {
    return props.branches?.find(b => b.id === id)?.name ?? '—'
}
</script>

<template>
    <SettingsLayout>
        <div class="page">

            <div class="page-head">
                <div>
                    <h2 class="page-title">Cajas Registradoras</h2>
                    <p class="page-sub">Una caja por sucursal. Solo una puede estar abierta a la vez por sede.</p>
                </div>
                <button class="btn-primary" @click="openNew">+ Agregar caja</button>
            </div>

            <div class="table-wrap">
                <table class="tbl" v-if="registers.length">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Sucursal</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="reg in registers" :key="reg.id">
                            <td class="reg-name">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                {{ reg.name }}
                            </td>
                            <td class="muted">{{ branchName(reg.branch_id) }}</td>
                            <td>
                                <span :class="['pill', reg.opened_at && !reg.closed_at ? 'pill--open' : 'pill--closed']">
                                    {{ reg.opened_at && !reg.closed_at ? 'Abierta' : 'Cerrada' }}
                                </span>
                            </td>
                            <td class="actions">
                                <button class="btn-act" @click="openEdit(reg)">Editar</button>
                                <button class="btn-act act--danger" @click="destroy(reg)">Eliminar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="empty-state">No hay cajas. Agrega la primera.</div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="overlay">
                <div class="modal">
                    <div class="modal-head">
                        <h3>{{ editRegister ? 'Editar caja' : 'Nueva caja registradora' }}</h3>
                        <button @click="closeModal">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal-body">
                        <div class="field">
                            <label>Nombre *</label>
                            <input v-model="form.name" type="text" class="input" placeholder="Ej: Caja 1" required autofocus />
                            <span v-if="form.errors.name" class="err">{{ form.errors.name }}</span>
                        </div>
                        <div class="field">
                            <label>Sucursal</label>
                            <select v-model="form.branch_id" class="input">
                                <option :value="null">Sin asignar</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-ghost" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing">
                                {{ editRegister ? 'Guardar cambios' : 'Crear caja' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
.page      { display: flex; flex-direction: column; gap: 1rem; }
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.page-sub   { font-size: .8rem; color: var(--text-muted); margin-top: 2px; }

.table-wrap { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; }
.tbl        { width: 100%; min-width: 480px; border-collapse: collapse; font-size: .875rem; }
.tbl th     { padding: .6rem 1rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); border-bottom: 1px solid var(--border); text-align: left; }
.tbl td     { padding: .75rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.tbl tr:last-child td { border-bottom: none; }
.tbl tr:hover td { background: var(--hover); }
.reg-name   { display: flex; align-items: center; gap: .5rem; font-weight: 600; color: var(--text-primary); }
.reg-name svg { color: var(--brand); flex-shrink: 0; }
.muted      { color: var(--text-muted); font-size: .85rem; }
.pill       { font-size: .7rem; font-weight: 700; padding: 2px 9px; border-radius: 99px; }
.pill--open   { background: #14532d; color: #4ade80; }
.pill--closed { background: var(--hover); color: var(--text-muted); border: 1px solid var(--border); }
.actions    { display: flex; gap: .35rem; }
.btn-act    { font-size: .72rem; font-weight: 500; padding: .25rem .6rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; }
.btn-act:hover { background: var(--hover); }
.act--danger   { color: #ef4444; border-color: color-mix(in srgb,#ef4444 30%,transparent); }
.act--danger:hover { background: color-mix(in srgb,#ef4444 10%,transparent); }
.empty-state { padding: 2.5rem; text-align: center; color: var(--text-muted); font-size: .875rem; }

.overlay    { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 60; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal      { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 380px; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal-head h3 { font-size: .9375rem; font-weight: 700; color: var(--text-primary); }
.modal-head button { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; }
.modal-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-foot { display: flex; justify-content: flex-end; gap: .6rem; padding-top: .25rem; }
.field      { display: flex; flex-direction: column; gap: .3rem; }
.field label { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.input      { background: var(--hover); border: 1px solid var(--border); color: var(--text-primary); border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; outline: none; font-family: inherit; width: 100%; }
.input:focus { border-color: var(--brand); }
.err        { font-size: .73rem; color: #ef4444; }
.btn-primary { font-size: .875rem; font-weight: 600; color: #fff; background: var(--brand); border: none; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-family: inherit; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.btn-ghost  { font-size: .8125rem; color: var(--text-secondary); background: transparent; border: 1px solid var(--border); border-radius: 8px; padding: .5rem 1rem; cursor: pointer; font-family: inherit; }

/* ─── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
    /* Page header stacks */
    .page-head { flex-direction: column; align-items: flex-start; }
    .page-head .btn-primary { width: 100%; justify-content: center; min-height: 44px; }

    .tbl { font-size: 0.8rem; }

    /* Action buttons: 44px touch target */
    .btn-act { min-height: 44px; padding: .35rem .75rem; }

    /* Modal → bottom sheet */
    .overlay { align-items: flex-end; padding: 0; }
    .modal { border-radius: 16px 16px 0 0; max-height: 90dvh; overflow-y: auto; max-width: 100%; }

    /* Input: prevent iOS zoom */
    .input { font-size: 1rem; }

    /* Modal footer buttons */
    .modal-foot { flex-direction: column-reverse; gap: 0.5rem; }
    .modal-foot .btn-primary, .modal-foot .btn-ghost { width: 100%; justify-content: center; min-height: 44px; }
}
</style>
