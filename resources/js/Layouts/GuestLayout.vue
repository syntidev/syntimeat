<script setup>
import AppLogo from '@/Components/AppLogo.vue'
import { onMounted, onUnmounted, ref } from 'vue'

const canvasRef = ref(null)
let animId = null

onMounted(() => {
    const canvas = canvasRef.value
    if (!canvas) return
    const ctx = canvas.getContext('2d')

    const resize = () => {
        canvas.width  = window.innerWidth
        canvas.height = window.innerHeight
    }
    resize()
    window.addEventListener('resize', resize)

    // Paleta: rojos oscuros (carne), cremas (grasa), borgoñas
    const palette = [
        'rgba(185,28,28,',   // rojo brand
        'rgba(153,27,27,',   // rojo oscuro
        'rgba(220,80,60,',   // rojo vivo
        'rgba(240,200,160,', // crema/grasa
        'rgba(200,160,120,', // tostado
        'rgba(120,20,20,',   // borgoña
    ]

    const COUNT = 55

    const particles = Array.from({ length: COUNT }, () => {
        const color = palette[Math.floor(Math.random() * palette.length)]
        const r = 3 + Math.random() * 28          // radio 3–31px
        const alpha = 0.06 + Math.random() * 0.18 // semitransparentes
        return {
            x:  Math.random() * window.innerWidth,
            y:  Math.random() * window.innerHeight,
            r,
            color,
            alpha,
            alphaDir: Math.random() < 0.5 ? 1 : -1,
            alphaSpeed: 0.0003 + Math.random() * 0.0007,
            vx: (Math.random() - 0.5) * 0.35,
            vy: (Math.random() - 0.5) * 0.35,
            // forma elíptica para evocar veteado
            rx: r,
            ry: r * (0.4 + Math.random() * 0.8),
            angle: Math.random() * Math.PI,
            angleSpeed: (Math.random() - 0.5) * 0.004,
        }
    })

    const draw = () => {
        const W = canvas.width
        const H = canvas.height
        ctx.clearRect(0, 0, W, H)

        for (const p of particles) {
            // pulso de opacidad
            p.alpha += p.alphaSpeed * p.alphaDir
            if (p.alpha > 0.22 || p.alpha < 0.04) p.alphaDir *= -1

            // movimiento
            p.x += p.vx
            p.y += p.vy
            p.angle += p.angleSpeed

            // wrap suave
            if (p.x < -p.r * 2) p.x = W + p.r
            if (p.x > W + p.r * 2) p.x = -p.r
            if (p.y < -p.r * 2) p.y = H + p.r
            if (p.y > H + p.r * 2) p.y = -p.r

            // dibujar elipse rotada
            ctx.save()
            ctx.translate(p.x, p.y)
            ctx.rotate(p.angle)

            const grad = ctx.createRadialGradient(0, 0, 0, 0, 0, p.rx)
            grad.addColorStop(0, `${p.color}${p.alpha.toFixed(3)})`)
            grad.addColorStop(1, `${p.color}0)`)

            ctx.beginPath()
            ctx.ellipse(0, 0, p.rx, p.ry, 0, 0, Math.PI * 2)
            ctx.fillStyle = grad
            ctx.fill()
            ctx.restore()
        }

        animId = requestAnimationFrame(draw)
    }

    draw()

    onUnmounted(() => {
        cancelAnimationFrame(animId)
        window.removeEventListener('resize', resize)
    })
})
</script>

<template>
    <div class="guest-root">
        <!-- Canvas animado: partículas marmoladas -->
        <canvas ref="canvasRef" class="guest-canvas" aria-hidden="true" />

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

/* Canvas de partículas */
.guest-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
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

@media (max-width: 640px) {
    .guest-root { padding: 1rem; }
    .guest-card { padding: 1.5rem 1.25rem 1.25rem; border-radius: 1rem; }
}

@media (max-width: 1023px) {
    .guest-card { max-width: 100%; }
}
</style>
