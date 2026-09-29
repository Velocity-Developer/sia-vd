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
    <nav v-if="props.total > 0" class="flex flex-wrap items-center gap-1.5" aria-label="Navigasi halaman">
        <Link
            v-for="link in tautan"
            :key="link.label"
            :href="link.url ?? '#'"
            preserve-scroll
            preserve-state
            class="inline-flex h-9 min-w-9 items-center justify-center whitespace-nowrap rounded-lg border px-3 text-sm font-medium shadow-sm transition-colors"
            :class="
                link.active
                    ? 'border-[#0075de] bg-[#0075de] text-white hover:bg-[#005bab]'
                    : link.url
                      ? 'border-[#d8d5d2] bg-white text-[#31302e] hover:bg-[#f6f5f4] dark:border-border dark:bg-background dark:text-foreground dark:hover:bg-accent'
                      : 'pointer-events-none border-[#d8d5d2] bg-white text-[#31302e] opacity-50 dark:border-border dark:bg-background dark:text-foreground'
            "
            :aria-current="link.active ? 'page' : undefined"
            >{{ link.teks }}</Link
        >
    </nav>
</template>
