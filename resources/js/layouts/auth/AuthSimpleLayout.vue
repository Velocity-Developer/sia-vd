<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage<SharedData>();
const institusi = page.props.institusi;
const tahun = new Date().getFullYear();
</script>

<template>
    <!-- Warna dan bentuk kartu disamakan dengan halaman dalam sistem (latar #f6f5f4, kartu putih). -->
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-[#f6f5f4] px-4 py-10 sm:px-6">
        <div class="w-full max-w-[420px]">
            <div class="flex flex-col gap-6">
                <Link :href="route('home')" class="flex flex-col items-center gap-3 text-center">
                    <div
                        class="flex size-12 items-center justify-center overflow-hidden rounded-xl"
                        :class="institusi?.logo_url ? 'border border-[#e6e6e6] bg-white' : 'bg-[#0075de]'"
                    >
                        <img v-if="institusi?.logo_url" :src="institusi.logo_url" :alt="institusi.nama_pt" class="size-full object-contain p-1.5" />
                        <AppLogoIcon v-else class="size-6 fill-current text-white" />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-[15px] font-semibold leading-5 text-black">{{ institusi?.nama_pt ?? page.props.name }}</p>
                        <p v-if="institusi?.singkatan" class="text-[11px] font-medium uppercase tracking-[0.08em] text-[#a39e98]">
                            {{ institusi.singkatan }}
                        </p>
                    </div>
                </Link>

                <div class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm sm:p-7">
                    <div class="mb-5 space-y-1">
                        <h1 class="text-[22px] font-bold leading-7 tracking-[-0.4px] text-black">{{ title }}</h1>
                        <p v-if="description" class="text-sm leading-5 text-[#615d59]">{{ description }}</p>
                    </div>
                    <slot />
                </div>

                <p class="text-center text-xs text-[#a39e98]">© {{ tahun }} {{ institusi?.nama_pt ?? page.props.name }}</p>
            </div>
        </div>
    </div>
</template>
