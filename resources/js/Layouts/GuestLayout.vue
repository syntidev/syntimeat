<script setup>
import AppLogo from '@/Components/AppLogo.vue'
</script>

<template>
    <div class="guest-root">
        <div class="guest-card">
            <!-- Logo SYNTImeat -->
            <div class="guest-brand">
                <AppLogo :dark="true" :size="52" />
                <div class="guest-brand-text">
                    <span class="guest-brand-synti">SYNTI</span><span class="guest-brand-meat">meat</span>
                </div>
                <p class="guest-brand-by">by SYNTIDEV</p>
            </div>

            <!-- Contenido de la página (form) -->
            <slot />

            <!-- Footer -->
            <p class="guest-footer">
                © {{ new Date().getFullYear() }} SYNTIDEV · SYNTImeat
            </p>
        </div>

        <!-- Decoración fondo -->
        <div class="guest-bg-orb guest-bg-orb--1" aria-hidden="true" />
        <div class="guest-bg-orb guest-bg-orb--2" aria-hidden="true" />
    </div>
</template>

<style scoped>
.guest-root {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0d0f14;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

/* Orbes decorativos */
.guest-bg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
}
.guest-bg-orb--1 {
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(185,28,28,0.18) 0%, transparent 70%);
    top: -80px; left: -80px;
}
.guest-bg-orb--2 {
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(37,99,235,0.12) 0%, transparent 70%);
    bottom: -60px; right: -60px;
}

/* Card */
.guest-card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 400px;
    background: #161920;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1.25rem;
    padding: 2rem 2rem 1.5rem;
    box-shadow: 0 24px 80px rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Marca */
.guest-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}
.guest-brand-text {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1;
}
.guest-brand-synti { color: #ffffff; }
.guest-brand-meat  { color: #B91C1C; }
.guest-brand-by {
    font-size: 0.7rem;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.3);
    margin: 0;
}

/* Footer */
.guest-footer {
    text-align: center;
    font-size: 0.68rem;
    color: rgba(255,255,255,0.2);
    margin: 0;
}
</style>
