<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';

/**
 * Select untuk bilah filter. Panah bawaan browser diganti ikon sendiri karena panah bawaan menempel
 * ke tepi kanan; ruang di kanan disediakan lewat padding agar teks panjang tidak menabrak panah.
 */
defineProps<{ label: string }>();
const model = defineModel<number | string | null>({ required: true });
</script>

<template>
    <div class="relative w-full lg:w-[200px]">
        <select
            v-model="model"
            :aria-label="label"
            class="h-10 w-full appearance-none rounded-lg border border-[#d8d5d2] bg-white py-2 pl-3 pr-10 text-sm text-[#31302e] shadow-sm outline-none transition-colors hover:border-[#aaa5a0] focus:border-[#0075de] focus:ring-2 focus:ring-[#0075de]/15"
        >
            <slot />
        </select>
        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
    </div>
</template>
