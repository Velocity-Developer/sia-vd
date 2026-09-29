<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <!-- Setara judul halaman standar (docs/standar-ui.md). -->
    <div>
        <h1 class="judul-halaman">{{ title }}</h1>
        <p v-if="description" class="deskripsi-halaman">
            {{ description }}
        </p>
    </div>
</template>
