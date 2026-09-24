<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

// Pilihan pengguna di browsernya menang; bila belum pernah memilih, pakai bawaan dari Pengaturan Sistem → Tampilan.
const bawaanLebar = usePage<SharedData>().props.tampilan?.sidebar_bawaan !== 'ringkas';
const isOpen = ref(bawaanLebar);

onMounted(() => {
    const tersimpan = localStorage.getItem('sidebar');
    isOpen.value = tersimpan === null ? bawaanLebar : tersimpan !== 'false';
});

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <slot />
    </SidebarProvider>
</template>
