<script setup>
defineProps({
    show:    { type: Boolean, default: false },
    mensaje: { type: String,  default: 'No tienes permiso para realizar esta acción.' },
})

defineEmits(['close'])
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="ar-overlay">
            <div class="ar-modal" role="dialog" aria-modal="true">
                <div class="ar-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                </div>
                <h2 class="ar-title">Acceso restringido</h2>
                <p class="ar-msg">{{ mensaje }}</p>
                <p class="ar-hint">Comunícate con el administrador de tu sucursal si necesitas acceso.</p>
                <button class="ar-btn" @click="$emit('close')">Entendido</button>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.ar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.ar-modal {
    background: var(--bg-card, #1a1d24);
    border: 1px solid var(--border, rgba(255,255,255,0.1));
    border-radius: 1rem;
    padding: 2rem 1.75rem;
    width: min(380px, 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

.ar-icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: rgba(185,28,28,0.12);
    border: 1px solid rgba(185,28,28,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #B91C1C;
    margin-bottom: 0.25rem;
}

.ar-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary, rgba(255,255,255,0.92));
    margin: 0;
}

.ar-msg {
    font-size: 0.9rem;
    color: var(--text-secondary, rgba(255,255,255,0.65));
    margin: 0;
    line-height: 1.5;
}

.ar-hint {
    font-size: 0.78rem;
    color: var(--text-muted, rgba(255,255,255,0.35));
    margin: 0;
    line-height: 1.5;
}

.ar-btn {
    margin-top: 0.5rem;
    padding: 0.6rem 2rem;
    border-radius: 0.5rem;
    background: #B91C1C;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s;
}
.ar-btn:hover { background: #991b1b; }
</style>
