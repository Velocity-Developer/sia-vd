<script setup lang="ts">
/**
 * Select untuk bilah filter, atau isian form bila `penuh` diisi (lebar mengikuti kolomnya).
 * Gaya dari kelas `isian isian-pilih` (app.css) yang juga dipakai select di form,
 * sehingga filter dan form tampil sama.
 */
const props = withDefaults(defineProps<{ label: string; penuh?: boolean }>(), { penuh: false });
const model = defineModel<number | string | null>({ required: true });
</script>

<template>
    <div class="relative w-full" :class="props.penuh ? '' : 'lg:min-w-[170px] lg:max-w-[220px] lg:flex-1'">
        <select v-model="model" :aria-label="label" class="isian isian-pilih">
            <slot />
        </select>
    </div>
</template>
