<script setup>
defineProps({
    dark: { type: Boolean, default: true },
    size: { type: Number, default: 32 },
})
</script>

<template>
    <img
        :src="dark ? '/syntimeat-logo-transparent.svg' : '/syntimeat-logo-positive.svg'"
        alt="SYNTImeat"
        :width="size"
        :height="size"
        class="app-logo"
        draggable="false"
    />
</template>

<style scoped>
.app-logo { display: block; object-fit: contain; flex-shrink: 0; }
</style>
