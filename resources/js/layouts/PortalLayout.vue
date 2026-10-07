<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import HakCipta from '@/components/HakCipta.vue';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Halaman depan publik (masuk, pengumuman, kalender akademik): identitas institusi + menu atas, tanpa sidebar.
const page = usePage<SharedData>();
const institusi = computed(() => page.props.institusi);

const menu = [
    { judul: 'SIAKAD', rute: 'login' },
    { judul: 'Pengumuman', rute: 'pengumuman' },
    { judul: 'Kalender Akademik', rute: 'kalender-akademik' },
];
const aktif = (rute: string) => page.url.split('?')[0] === route(rute, undefined, false);
</script>

<template>
    <div class="flex min-h-svh flex-col bg-[#f6f5f4] dark:bg-background">
        <header class="border-b border-[#e6e6e6] bg-white dark:border-border dark:bg-card">
            <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <Link :href="route('login')" class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl"
                        :class="institusi?.logo_url ? 'border border-[#e6e6e6] bg-white' : 'bg-[#0075de]'"
                    >
                        <img v-if="institusi?.logo_url" :src="institusi.logo_url" :alt="institusi.nama_pt" class="size-full object-contain p-1.5" />
                        <AppLogoIcon v-else class="size-6 fill-current text-white" />
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-base font-semibold text-black dark:text-foreground">{{ institusi?.nama_pt ?? page.props.name }}</p>
                        <p class="text-[11px] font-medium uppercase tracking-[0.1em] text-[#a39e98]">Sistem Informasi Akademik</p>
                    </div>
                </Link>

                <nav class="flex flex-wrap gap-1.5" aria-label="Menu utama">
                    <Link
                        v-for="item in menu"
                        :key="item.rute"
                        :href="route(item.rute)"
                        class="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-[0.06em] transition-colors sm:px-4"
                        :class="
                            aktif(item.rute)
                                ? 'border-[#0075de] bg-[#0075de] text-white'
                                : 'border-[#e6e6e6] text-[#31302e] hover:border-[#0075de] hover:text-[#0075de] dark:border-border dark:text-foreground'
                        "
                        :aria-current="aktif(item.rute) ? 'page' : undefined"
                        >{{ item.judul }}</Link
                    >
                </nav>
            </div>
        </header>

        <main class="flex flex-1 flex-col">
            <slot />
        </main>

        <footer class="border-t border-[#e6e6e6] bg-white px-4 py-5 dark:border-border dark:bg-card">
            <HakCipta class="text-center text-xs text-[#a39e98]" />
        </footer>
    </div>
</template>
