<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{ links: PaginationLink[]; total: number }>();

// Label dari Laravel berisi entitas HTML («, ») dan diterjemahkan ke teks biasa,
// sehingga tidak perlu v-html yang membuka celah injeksi.
const teks = (label: string): string =>
    label
        .replace(/&laquo;\s*/g, '‹ ')
        .replace(/\s*&raquo;/g, ' ›')
        .replace(/&hellip;/g, '…')
        .replace(/&[a-z]+;/g, ' ')
        .trim();

const tautan = computed(() => props.links.map((link) => ({ ...link, teks: teks(link.label) })));
</script>

<template>
    <nav v-if="props.total > 0" class="flex flex-wrap items-center gap-2" aria-label="Navigasi halaman">
        <Link
            v-for="link in tautan"
            :key="link.label"
            :href="link.url ?? '#'"
            preserve-scroll
            preserve-state
            class="rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
            :class="
                link.active
                    ? 'border-[#0075de] bg-[#0075de] text-white'
                    : link.url
                      ? 'border-[#e6e6e6] bg-white text-black hover:bg-[#f6f5f4]'
                      : 'pointer-events-none border-[#e6e6e6] bg-white opacity-40'
            "
            :aria-current="link.active ? 'page' : undefined"
            >{{ link.teks }}</Link
        >
    </nav>
</template>
