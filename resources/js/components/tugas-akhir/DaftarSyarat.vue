<script setup lang="ts">
import type { Syarat } from '@/lib/tugasAkhir';
import { CheckCircle2, CircleX } from 'lucide-vue-next';

defineProps<{ syarat: Syarat[] }>();
</script>

<template>
    <ul class="grid gap-2">
        <li v-for="s in syarat" :key="s.label" class="flex items-start gap-2 text-sm">
            <CheckCircle2 v-if="s.terpenuhi" class="mt-0.5 size-4 shrink-0 text-[#1aae39]" />
            <CircleX v-else class="mt-0.5 size-4 shrink-0 text-[#dd5b00]" />
            <span>
                <span class="text-black">{{ s.label }}</span>
                <span v-if="s.keterangan" class="block text-xs text-[#615d59]">{{ s.keterangan }}</span>
            </span>
        </li>
    </ul>
</template>
