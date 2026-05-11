<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const theme = computed(() => page.props.auth?.user?.theme ?? 'dark')
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard" :theme="theme">
        <p style="color: var(--text-secondary);">SYNTImeat funcionando</p>
    </AppLayout>
</template>
