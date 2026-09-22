<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage<SharedData>();
const institusi = page.props.institusi;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-[8px]"
        :class="institusi?.logo_url ? 'bg-white' : 'bg-[#0075de] text-white'"
    >
        <img v-if="institusi?.logo_url" :src="institusi.logo_url" :alt="institusi.nama_pt" class="size-full object-contain" />
        <AppLogoIcon v-else class="size-5 fill-current text-white" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate text-[14px] font-semibold leading-none tracking-[-0.01em] text-black">{{ institusi?.nama_pt ?? page.props.name }}</span>
        <span class="truncate text-[11px] font-medium uppercase tracking-[0.06em] text-[#a39e98]">{{ institusi?.singkatan }}</span>
    </div>
</template>
