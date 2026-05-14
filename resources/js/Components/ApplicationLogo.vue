<script setup>
import AppLogo from '@/Components/AppLogo.vue'
import { inject } from 'vue'

defineProps({
    dark: { type: Boolean, default: true },
    size: { type: Number, default: 40 },
})
</script>

<template>
    <AppLogo :dark="dark" :size="size" />
</template>
