<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <!-- Setara judul bagian/kartu standar (docs/standar-ui.md). -->
    <header>
        <h2 class="judul-bagian">{{ title }}</h2>
        <p v-if="description" class="teks-bantu mt-1">
            {{ description }}
        </p>
    </header>
</template>
