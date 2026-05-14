<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    status: { type: Number, required: true },
})

const info = computed(() => {
    const map = {
        403: {
            icon: '🔒',
            titulo: 'Acceso restringido',
            mensaje: 'No tienes permiso para ver esta página. Si crees que es un error, comunícate con el administrador de tu sucursal.',
            cta: null,
        },
        404: {
            icon: '🔍',
            titulo: 'Página no encontrada',
            mensaje: 'La dirección que buscas no existe o fue movida.',
            cta: null,
        },
        500: {
            icon: '⚠️',
            titulo: 'Error del servidor',
            mensaje: 'Algo salió mal en el servidor. Intenta de nuevo en unos momentos.',
            cta: null,
        },
        503: {
            icon: '🔧',
            titulo: 'En mantenimiento',
            mensaje: 'El sistema está en mantenimiento. Vuelve pronto.',
            cta: null,
        },
    }
    return map[props.status] ?? {
        icon: '❗',
        titulo: `Error ${props.status}`,
        mensaje: 'Ocurrió un error inesperado.',
        cta: null,
    }
})
</script>

<template>
    <div class="error-page">
        <div class="error-card">
            <span class="error-icon">{{ info.icon }}</span>
            <span class="error-code">{{ status }}</span>
            <h1 class="error-title">{{ info.titulo }}</h1>
            <p class="error-msg">{{ info.mensaje }}</p>

            <div class="error-actions">
                <Link href="/" class="error-btn">
                    Volver al inicio
                </Link>
                <button class="error-btn error-btn--ghost" @click="() => history.back()">
                    Regresar
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.error-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-base, #0d0f14);
    padding: 2rem;
}

.error-card {
    text-align: center;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.error-icon {
    font-size: 3rem;
    line-height: 1;
}

.error-code {
    font-size: 5rem;
    font-weight: 800;
    color: #B91C1C;
    line-height: 1;
    letter-spacing: -0.04em;
}

.error-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary, rgba(255,255,255,0.92));
    margin: 0;
}

.error-msg {
    font-size: 0.9rem;
    color: var(--text-muted, rgba(255,255,255,0.45));
    line-height: 1.6;
    margin: 0;
}

.error-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.error-btn {
    padding: 0.6rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    background: #B91C1C;
    color: #fff;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
    font-family: inherit;
}
.error-btn:hover { background: #991b1b; }

.error-btn--ghost {
    background: transparent;
    border: 1px solid var(--border, rgba(255,255,255,0.1));
    color: var(--text-secondary, rgba(255,255,255,0.6));
}
.error-btn--ghost:hover {
    background: var(--bg-elevated, rgba(255,255,255,0.05));
    color: var(--text-primary, rgba(255,255,255,0.92));
}
</style>
