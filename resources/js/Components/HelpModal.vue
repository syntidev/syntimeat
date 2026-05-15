<script setup>
import { ref, watch } from 'vue'
import { X, Lightbulb } from 'lucide-vue-next'

const props = defineProps({
    show:  { type: Boolean, default: false },
    title: { type: String, default: 'Ayuda' },
    steps: { type: Array, default: () => [] }, // [{icon, title, body, tip?}]
    faqs:  { type: Array, default: () => [] }, // [{q, a}]
})

const emit = defineEmits(['close'])

const activeTab = ref('steps')
const openFaq   = ref(null)

watch(() => props.show, (val) => {
    if (val) {
        activeTab.value = 'steps'
        openFaq.value   = null
    }
})

function onKeydown(e) {
    if (e.key === 'Escape') emit('close')
}
</script>

<template>
    <Teleport to="body">
        <Transition name="help-fade">
            <div
                v-if="show"
                class="help-overlay"
               
                @keydown="onKeydown"
                tabindex="-1"
            >
                <Transition name="help-slide" appear>
                    <div class="help-panel" role="dialog" aria-modal="true" :aria-label="`Ayuda: ${title}`">

                        <!-- Header -->
                        <div class="help-header">
                            <div class="help-title-row">
                                <div class="help-badge">?</div>
                                <h2 class="help-title">{{ title }}</h2>
                            </div>
                            <button class="help-close" @click="emit('close')" aria-label="Cerrar ayuda"><X :size="16" /></button>
                        </div>

                        <!-- Tabs (solo si hay ambas secciones) -->
                        <div v-if="steps.length && faqs.length" class="help-tabs">
                            <button
                                :class="['help-tab', { active: activeTab === 'steps' }]"
                                @click="activeTab = 'steps'"
                            >Cómo funciona</button>
                            <button
                                :class="['help-tab', { active: activeTab === 'faqs' }]"
                                @click="activeTab = 'faqs'"
                            >Preguntas frecuentes</button>
                        </div>

                        <!-- Body -->
                        <div class="help-body">

                            <!-- Pasos -->
                            <div v-show="activeTab === 'steps' && steps.length" class="steps-list">
                                <div v-for="(step, i) in steps" :key="i" class="step-item">
                                    <div class="step-num">{{ i + 1 }}</div>
                                    <div class="step-content">
                                        <div class="step-head">
                                            <span class="step-title">{{ step.title }}</span>
                                        </div>
                                        <p class="step-body">{{ step.body }}</p>
                                        <p v-if="step.tip" class="step-tip"><Lightbulb :size="13" class="tip-icon" />{{ step.tip }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- FAQs acordeón -->
                            <div v-show="activeTab === 'faqs' && faqs.length" class="faqs-list">
                                <div v-for="(faq, i) in faqs" :key="i" class="faq-item">
                                    <button
                                        class="faq-q"
                                        :aria-expanded="openFaq === i"
                                        @click="openFaq = openFaq === i ? null : i"
                                    >
                                        <span class="faq-text">{{ faq.q }}</span>
                                        <span class="faq-caret" aria-hidden="true">{{ openFaq === i ? '▲' : '▼' }}</span>
                                    </button>
                                    <Transition name="faq-expand">
                                        <p v-if="openFaq === i" class="faq-a">{{ faq.a }}</p>
                                    </Transition>
                                </div>
                            </div>

                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Overlay ─────────────────────────────────────────────── */
.help-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1100;
    display: flex;
    justify-content: flex-end;
    align-items: stretch;
}

/* ── Panel ───────────────────────────────────────────────── */
.help-panel {
    width: min(460px, 100vw);
    background: var(--bg-card);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: -6px 0 32px rgba(0, 0, 0, 0.35);
}

/* ── Header ──────────────────────────────────────────────── */
.help-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 22px 16px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
    flex-shrink: 0;
}
.help-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.help-badge {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--brand);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.help-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.help-close {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
    flex-shrink: 0;
}
.help-close:hover { background: var(--bg-row-hover, rgba(255,255,255,0.06)); }

/* ── Tabs ────────────────────────────────────────────────── */
.help-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
    padding: 0 22px;
    flex-shrink: 0;
}
.help-tab {
    padding: 10px 14px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.83rem;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
}
.help-tab.active {
    color: var(--brand);
    border-bottom-color: var(--brand);
}

/* ── Body ────────────────────────────────────────────────── */
.help-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 22px 24px;
}

/* ── Pasos ───────────────────────────────────────────────── */
.steps-list { display: flex; flex-direction: column; }

.step-item {
    display: flex;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid var(--border);
}
.step-item:last-child { border-bottom: none; }

.step-num {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--brand);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.step-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.step-head {
    display: flex;
    align-items: center;
    gap: 6px;
}
.step-title {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text-primary);
}
.step-body {
    font-size: 0.83rem;
    color: var(--text-secondary);
    line-height: 1.55;
    margin: 0;
}
.tip-icon {
    display: inline-block;
    vertical-align: middle;
    margin-right: 5px;
    flex-shrink: 0;
    color: var(--brand);
}
.step-tip {
    font-size: 0.8rem;
    display: flex;
    align-items: flex-start;
    color: var(--text-muted);
    background: var(--bg-row-hover, rgba(255,255,255,0.04));
    border-left: 3px solid var(--brand);
    border-radius: 0 6px 6px 0;
    padding: 6px 10px;
    margin: 2px 0 0;
    line-height: 1.45;
}

/* ── FAQs ────────────────────────────────────────────────── */
.faqs-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.faq-item {
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}
.faq-q {
    width: 100%;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition: background 0.12s;
}
.faq-q:hover { background: var(--bg-row-hover, rgba(255,255,255,0.04)); }
.faq-text {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-primary);
    line-height: 1.4;
}
.faq-caret {
    font-size: 9px;
    color: var(--text-muted);
    flex-shrink: 0;
    margin-top: 3px;
}
.faq-a {
    font-size: 0.82rem;
    color: var(--text-secondary);
    line-height: 1.55;
    padding: 8px 14px 14px;
    margin: 0;
    border-top: 1px solid var(--border);
}

/* ── Transiciones ────────────────────────────────────────── */
.help-fade-enter-active,
.help-fade-leave-active { transition: opacity 0.2s ease; }
.help-fade-enter-from,
.help-fade-leave-to     { opacity: 0; }

.help-slide-enter-active,
.help-slide-leave-active { transition: transform 0.25s ease; }
.help-slide-enter-from,
.help-slide-leave-to     { transform: translateX(100%); }

.faq-expand-enter-active,
.faq-expand-leave-active { transition: opacity 0.15s ease; }
.faq-expand-enter-from,
.faq-expand-leave-to     { opacity: 0; }

@media (max-width: 600px) {
    .help-panel { width: 100vw; }
}
</style>
