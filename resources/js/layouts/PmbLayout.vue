<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import HakCipta from '@/components/HakCipta.vue';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Halaman publik PMB: lebar (formulirnya panjang), identitas institusi di atas, tanpa sidebar.
const page = usePage<SharedData>();
const institusi = computed(() => page.props.institusi);
</script>

<template>
    <div class="min-h-svh bg-[#f6f5f4] px-4 py-8 dark:bg-background sm:py-12">
        <div class="mx-auto w-full max-w-[880px]">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl"
                        :class="institusi?.logo_url ? 'border border-[#e6e6e6] bg-white' : 'bg-[#0075de]'"
                    >
                        <img v-if="institusi?.logo_url" :src="institusi.logo_url" :alt="institusi.nama_pt" class="size-full object-contain p-1.5" />
                        <AppLogoIcon v-else class="size-6 fill-current text-white" />
                    </div>
                    <div class="leading-tight">
                        <p class="text-base font-semibold text-black dark:text-foreground">{{ institusi?.nama_pt ?? page.props.name }}</p>
                        <p class="text-sm text-[#615d59]">Penerimaan Mahasiswa Baru</p>
                    </div>
                </div>
                <Link :href="route('login')" class="shrink-0 text-sm font-medium text-[#0075de] hover:underline">Masuk</Link>
            </div>

            <slot />

            <HakCipta class="mt-8 text-center text-xs text-[#a39e98]" />
        </div>
    </div>
</template>
