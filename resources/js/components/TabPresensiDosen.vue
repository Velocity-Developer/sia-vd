<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/** Tab menu Presensi Dosen: daftar pertemuan, rekap per dosen, dan laporan per kelas. */
defineProps<{ aktif: 'pertemuan' | 'rekap' | 'kelas' }>();

const tab = [
    { id: 'pertemuan', label: 'Daftar Pertemuan', rute: 'admin.presensi-dosen.index' },
    { id: 'rekap', label: 'Rekap per Dosen', rute: 'admin.presensi-dosen.rekap' },
    { id: 'kelas', label: 'Per Kelas', rute: 'admin.presensi.laporan-dosen' },
] as const;
</script>

<template>
    <nav class="flex flex-wrap gap-1 border-b border-[#e6e6e6] dark:border-border" aria-label="Tab presensi dosen">
        <Link
            v-for="t in tab"
            :key="t.id"
            :href="route(t.rute)"
            class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
            :class="
                aktif === t.id
                    ? 'border-[#0075de] text-black dark:text-foreground'
                    : 'border-transparent text-[#615d59] hover:text-black dark:text-muted-foreground dark:hover:text-foreground'
            "
            :aria-current="aktif === t.id ? 'page' : undefined"
        >
            {{ t.label }}
        </Link>
    </nav>
</template>
