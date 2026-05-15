<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Users, Monitor } from 'lucide-vue-next'

const props = defineProps({
    branches: { type: Array, default: () => [] },
    users:    { type: Array, default: () => [] },
})

const saving = ref(false)

// ─── Modal nueva sucursal ─────────────────────────────────────────────────
const showNew   = ref(false)
const newForm   = ref({ name: '', address: '', city: '', phone: '' })
const newErrors = ref({})

function openNew() {
    newForm.value = { name: '', address: '', city: '', phone: '' }
    newErrors.value = {}
    showNew.value = true
}

function submitNew() {
    saving.value = true
    router.post(route('settings.branches.store'), newForm.value, {
        onSuccess: () => { showNew.value = false; saving.value = false },
        onError:   (e) => { newErrors.value = e; saving.value = false },
        preserveScroll: true,
    })
}

// ─── Modal editar sucursal ────────────────────────────────────────────────
const editTarget = ref(null)
const editForm   = ref({})
const editErrors = ref({})

function openEdit(branch) {
    editTarget.value = branch
    editForm.value   = {
        name:      branch.name,
        address:   branch.address ?? '',
        city:      branch.city    ?? '',
        phone:     branch.phone   ?? '',
        is_active: branch.is_active,
    }
    editErrors.value = {}
}

function submitEdit() {
    saving.value = true
    router.put(route('settings.branches.update', editTarget.value.id), editForm.value, {
        onSuccess: () => { editTarget.value = null; saving.value = false },
        onError:   (e) => { editErrors.value = e; saving.value = false },
        preserveScroll: true,
    })
}
</script>

<template>
    <SettingsLayout>
        <div class="page">

            <div class="page-head">
                <div>
                    <h2 class="page-title">Sucursales</h2>
                    <p class="page-sub">Sedes del negocio. Asigna usuarios en <strong>Equipo</strong> y cajas en <strong>Cajas</strong>.</p>
                </div>
                <button class="btn-brand" @click="openNew">+ Nueva sucursal</button>
            </div>

            <div v-if="!branches.length" class="empty-state">No hay sucursales. Crea la primera.</div>

            <div v-else class="branch-list">
                <div
                    v-for="branch in branches" :key="branch.id"
                    class="branch-row"
                    :class="{ 'branch-row--inactive': !branch.is_active }"
                >
                    <div class="branch-dot" :class="branch.is_active ? 'dot--on' : 'dot--off'" />

                    <div class="branch-body">
                        <div class="branch-name">{{ branch.name }}
                            <span v-if="!branch.is_active" class="badge-off">Inactiva</span>
                        </div>
                        <div class="branch-meta">
                            <span v-if="branch.city">📍 {{ branch.city }}</span>
                            <span v-if="branch.address">{{ branch.address }}</span>
                            <span v-if="branch.phone">📞 {{ branch.phone }}</span>
                        </div>
                    </div>

                    <div class="branch-stats">
                        <span class="stat"><Users :size="13" class="stat-icon" /> {{ branch.users_count }} usuario{{ branch.users_count !== 1 ? 's' : '' }}</span>
                        <span class="stat"><Monitor :size="13" class="stat-icon" /> {{ branch.cash_registers?.length ?? 0 }} caja{{ (branch.cash_registers?.length ?? 0) !== 1 ? 's' : '' }}</span>
                    </div>

                    <button class="btn-ghost btn-sm" @click="openEdit(branch)">Editar</button>
                </div>
            </div>
        </div>

        <!-- Modal nueva -->
        <Teleport to="body">
            <div v-if="showNew" class="overlay">
                <div class="modal">
                    <div class="modal-head"><h3>Nueva Sucursal</h3><button @click="showNew = false">✕</button></div>
                    <div class="modal-body">
                        <div class="field"><label>Nombre *</label>
                            <input v-model="newForm.name" class="input" placeholder="Ej: Sucursal Norte" />
                            <span v-if="newErrors.name" class="err">{{ newErrors.name }}</span>
                        </div>
                        <div class="field"><label>Dirección</label><input v-model="newForm.address" class="input" /></div>
                        <div class="field-row">
                            <div class="field"><label>Ciudad</label><input v-model="newForm.city" class="input" /></div>
                            <div class="field"><label>Teléfono</label><input v-model="newForm.phone" class="input" /></div>
                        </div>
                    </div>
                    <div class="modal-foot">
                        <button class="btn-ghost" @click="showNew = false">Cancelar</button>
                        <button class="btn-brand" :disabled="!newForm.name || saving" @click="submitNew">
                            {{ saving ? 'Guardando…' : 'Crear' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal editar -->
        <Teleport to="body">
            <div v-if="editTarget" class="overlay">
                <div class="modal">
                    <div class="modal-head"><h3>Editar — {{ editTarget.name }}</h3><button @click="editTarget = null">✕</button></div>
                    <div class="modal-body">
                        <div class="field"><label>Nombre *</label>
                            <input v-model="editForm.name" class="input" />
                            <span v-if="editErrors.name" class="err">{{ editErrors.name }}</span>
                        </div>
                        <div class="field"><label>Dirección</label><input v-model="editForm.address" class="input" /></div>
                        <div class="field-row">
                            <div class="field"><label>Ciudad</label><input v-model="editForm.city" class="input" /></div>
                            <div class="field"><label>Teléfono</label><input v-model="editForm.phone" class="input" /></div>
                        </div>
                        <label class="check-row">
                            <input type="checkbox" v-model="editForm.is_active" />
                            Sucursal activa
                        </label>
                    </div>
                    <div class="modal-foot">
                        <button class="btn-ghost" @click="editTarget = null">Cancelar</button>
                        <button class="btn-brand" :disabled="!editForm.name || saving" @click="submitEdit">
                            {{ saving ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
.page      { display: flex; flex-direction: column; gap: 1.25rem; }
.page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.page-sub   { font-size: .8rem; color: var(--text-muted); margin-top: 2px; }

.branch-list { display: flex; flex-direction: column; gap: .5rem; }
.branch-row  { display: flex; align-items: center; gap: 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: .85rem 1.1rem; flex-wrap: wrap; }
.branch-row--inactive { opacity: .6; }
.branch-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot--on     { background: #22c55e; }
.dot--off    { background: #6b7280; }
.branch-body { flex: 1; min-width: 0; }
.branch-name { font-size: .9375rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: .5rem; }
.badge-off   { font-size: .65rem; font-weight: 700; padding: 1px 6px; border-radius: 20px; background: #ef444422; color: #ef4444; }
.branch-meta { display: flex; gap: .85rem; font-size: .78rem; color: var(--text-muted); margin-top: 2px; flex-wrap: wrap; }
.branch-stats { display: flex; gap: .5rem; flex-shrink: 0; }
.stat        { font-size: .75rem; color: var(--text-muted); background: var(--hover); border-radius: 20px; padding: .2rem .65rem; display: inline-flex; align-items: center; gap: 4px; }
.stat-icon   { flex-shrink: 0; }

.empty-state { text-align: center; padding: 3rem; color: var(--text-muted); font-size: .875rem; }

/* Modal */
.overlay  { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 60; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal    { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 440px; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal-head h3 { font-size: .9375rem; font-weight: 700; color: var(--text-primary); }
.modal-head button { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; }
.modal-body { padding: 1.25rem; display: flex; flex-direction: column; gap: .9rem; }
.modal-foot { display: flex; justify-content: flex-end; gap: .6rem; padding: 1rem 1.25rem; border-top: 1px solid var(--border); }
.field      { display: flex; flex-direction: column; gap: .3rem; }
.field-row  { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.field label { font-size: .75rem; font-weight: 600; color: var(--text-secondary); }
.input      { background: var(--hover); border: 1px solid var(--border); color: var(--text-primary); border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; outline: none; font-family: inherit; width: 100%; }
.input:focus { border-color: var(--brand); }
.err        { font-size: .72rem; color: #ef4444; }
.check-row  { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: var(--text-secondary); cursor: pointer; }
.btn-brand  { font-size: .875rem; font-weight: 600; color: #fff; background: var(--brand); border: none; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-family: inherit; }
.btn-brand:disabled { opacity: .45; cursor: not-allowed; }
.btn-ghost  { font-size: .8125rem; font-weight: 500; color: var(--text-secondary); background: transparent; border: 1px solid var(--border); border-radius: 8px; padding: .45rem .875rem; cursor: pointer; font-family: inherit; }
.btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
.btn-sm     { font-size: .75rem; padding: .3rem .75rem; border-radius: 6px; flex-shrink: 0; }

/* ─── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
    /* Page header stacks */
    .page-head { flex-direction: column; align-items: flex-start; }
    .page-head .btn-brand { width: 100%; justify-content: center; min-height: 44px; }

    /* Branch row: let it reflow naturally */
    .branch-row { flex-wrap: wrap; gap: 0.75rem; }
    .branch-stats { width: 100%; }
    .btn-ghost.btn-sm { min-height: 44px; width: 100%; }

    /* Modal → bottom sheet */
    .overlay { align-items: flex-end; padding: 0; }
    .modal { border-radius: 16px 16px 0 0; max-height: 90dvh; overflow-y: auto; max-width: 100%; }

    /* Field row → 1 col */
    .field-row { grid-template-columns: 1fr; }

    /* Input: prevent iOS zoom */
    .input { font-size: 1rem; }

    /* Modal footer buttons */
    .modal-foot { flex-direction: column-reverse; gap: 0.5rem; }
    .modal-foot .btn-brand, .modal-foot .btn-ghost { width: 100%; justify-content: center; min-height: 44px; }
}
</style>
