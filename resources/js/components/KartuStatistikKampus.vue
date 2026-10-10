<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, BookText, CircleCheck, GraduationCap, PauseCircle, School, UserCheck, Users } from 'lucide-vue-next';
import { computed, type Component } from 'vue';

export type KunciStatistikKampus =
    | 'mahasiswa'
    | 'mahasiswa_aktif'
    | 'mahasiswa_cuti'
    | 'mahasiswa_lulus'
    | 'dosen_aktif'
    | 'kelas_kuliah'
    | 'mata_kuliah'
    | 'program_studi';

const props = defineProps<{
    /** Hanya kunci yang dikirim server yang tampil (dashboard Admin, Prodi, Dosen). */
    statistik: Partial<Record<KunciStatistikKampus, number>>;
    /** Tautan per kartu; kartu tanpa tautan tampil sebagai teks biasa. */
    tautan?: Partial<Record<KunciStatistikKampus, string | null>>;
}>();

const DAFTAR: { kunci: KunciStatistikKampus; label: string; ikon: Component; catatan?: string }[] = [
    { kunci: 'mahasiswa', label: 'Jumlah Mahasiswa', ikon: Users, catatan: 'semua status' },
    { kunci: 'mahasiswa_aktif', label: 'Mahasiswa Aktif', ikon: UserCheck },
    { kunci: 'dosen_aktif', label: 'Dosen Aktif', ikon: GraduationCap },
    { kunci: 'kelas_kuliah', label: 'Kelas Kuliah', ikon: School, catatan: 'tahun akademik aktif' },
    { kunci: 'mata_kuliah', label: 'Mata Kuliah', ikon: BookText },
    { kunci: 'program_studi', label: 'Program Studi', ikon: BookOpen },
    { kunci: 'mahasiswa_cuti', label: 'Mahasiswa Cuti', ikon: PauseCircle },
    { kunci: 'mahasiswa_lulus', label: 'Mahasiswa Lulus', ikon: CircleCheck },
];

const angka = new Intl.NumberFormat('id-ID');

const kartu = computed(() =>
    DAFTAR.flatMap((k) => {
        const nilai = props.statistik[k.kunci];
        return nilai === undefined ? [] : [{ ...k, nilai, href: props.tautan?.[k.kunci] ?? null }];
    }),
);
</script>

<template>
    <div v-if="kartu.length" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <component
            :is="k.href ? Link : 'div'"
            v-for="k in kartu"
            :key="k.kunci"
            :href="k.href ?? undefined"
            class="kartu flex flex-col gap-1 px-4 py-3"
            :class="k.href ? 'transition-colors hover:border-[#0075de]' : ''"
        >
            <div class="flex items-center justify-between gap-2">
                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">{{ k.label }}</p>
                <component :is="k.ikon" class="size-4 shrink-0 text-[#a39e98]" />
            </div>
            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ angka.format(k.nilai) }}</p>
            <p v-if="k.catatan" class="teks-bantu -mt-1">{{ k.catatan }}</p>
        </component>
    </div>
</template>
