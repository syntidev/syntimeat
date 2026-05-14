<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
    canResetPassword: { type: Boolean },
    status:           { type: String },
})

const showPassword = ref(false)

const form = useForm({
    email:    '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Ingresar · SYNTImeat" />

        <!-- Mensaje de estado (reset password, etc.) -->
        <div v-if="status" class="login-status">{{ status }}</div>

        <form @submit.prevent="submit" class="login-form" novalidate>
            <h1 class="login-title">Ingresa a tu cuenta</h1>

            <!-- Email -->
            <div class="login-field">
                <label class="login-label" for="email">Correo electrónico</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="login-input"
                    :class="{ 'login-input--error': form.errors.email }"
                    placeholder="tu@correo.com"
                    autocomplete="username"
                    autofocus
                    required
                />
                <p v-if="form.errors.email" class="login-error">{{ form.errors.email }}</p>
            </div>

            <!-- Contraseña -->
            <div class="login-field">
                <label class="login-label" for="password">Contraseña</label>
                <div class="login-input-wrap">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="login-input login-input--pw"
                        :class="{ 'login-input--error': form.errors.password }"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                    />
                    <button
                        type="button"
                        class="login-eye"
                        @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    >
                        <!-- Ojo abierto -->
                        <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <!-- Ojo cerrado -->
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
                <p v-if="form.errors.password" class="login-error">{{ form.errors.password }}</p>
            </div>

            <!-- Recordarme + Olvidé clave -->
            <div class="login-row">
                <label class="login-remember">
                    <input type="checkbox" v-model="form.remember" class="login-chk" />
                    <span>Recordarme</span>
                </label>
                <a v-if="canResetPassword" :href="route('password.request')" class="login-forgot">
                    ¿Olvidé mi contraseña?
                </a>
            </div>

            <!-- Botón -->
            <button
                type="submit"
                class="login-btn"
                :class="{ 'login-btn--loading': form.processing }"
                :disabled="form.processing"
            >
                <svg v-if="form.processing" class="login-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="12" stroke-linecap="round"/>
                </svg>
                {{ form.processing ? 'Ingresando…' : 'Ingresar' }}
            </button>
        </form>
    </GuestLayout>
</template>

<style scoped>
.login-status {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.3);
    color: #34d399;
    border-radius: 0.5rem;
    padding: 0.625rem 0.875rem;
    font-size: 0.8rem;
    text-align: center;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.login-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: rgba(255,255,255,0.92);
    margin: 0 0 0.25rem;
    text-align: center;
}

.login-field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.login-label {
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255,255,255,0.55);
}

.login-input-wrap { position: relative; }

.login-input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem;
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    padding: 0.7rem 0.9rem;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
    font-family: inherit;
}
.login-input--pw { padding-right: 2.75rem; }
.login-input::placeholder { color: rgba(255,255,255,0.22); }
.login-input:focus {
    border-color: #B91C1C;
    box-shadow: 0 0 0 3px rgba(185,28,28,0.2);
}
.login-input--error {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239,68,68,0.15);
}

.login-eye {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.35);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    transition: color 0.15s;
}
.login-eye:hover { color: rgba(255,255,255,0.7); }

.login-error {
    font-size: 0.75rem;
    color: #f87171;
    margin: 0;
}

.login-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}

.login-remember {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    cursor: pointer;
}
.login-chk {
    width: 15px;
    height: 15px;
    accent-color: #B91C1C;
    cursor: pointer;
}

.login-forgot {
    font-size: 0.8rem;
    color: rgba(185,28,28,0.85);
    text-decoration: none;
    transition: color 0.15s;
}
.login-forgot:hover { color: #ef4444; }

.login-btn {
    width: 100%;
    padding: 0.8rem;
    background: #B91C1C;
    color: #fff;
    font-size: 0.9rem;
    font-weight: 700;
    border: none;
    border-radius: 0.625rem;
    cursor: pointer;
    transition: background 0.15s, opacity 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-family: inherit;
    margin-top: 0.25rem;
}
.login-btn:hover:not(:disabled) { background: #991b1b; }
.login-btn:disabled { opacity: 0.65; cursor: not-allowed; }

@keyframes spin { to { transform: rotate(360deg); } }
.login-spinner {
    width: 16px; height: 16px;
    animation: spin 0.8s linear infinite;
}
</style>
