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

// Tiap kartu punya warna sendiri (garis kiri, ikon, angka) agar mudah dibedakan sekilas.
const DAFTAR: { kunci: KunciStatistikKampus; label: string; ikon: Component; warna: string; catatan?: string }[] = [
    { kunci: 'mahasiswa', label: 'Jumlah Mahasiswa', ikon: Users, warna: '#0075de', catatan: 'semua status' },
    { kunci: 'mahasiswa_aktif', label: 'Mahasiswa Aktif', ikon: UserCheck, warna: '#1aae39' },
    { kunci: 'dosen_aktif', label: 'Dosen Aktif', ikon: GraduationCap, warna: '#7c3aed' },
    { kunci: 'kelas_kuliah', label: 'Kelas Kuliah', ikon: School, warna: '#dd5b00', catatan: 'tahun akademik aktif' },
    { kunci: 'mata_kuliah', label: 'Mata Kuliah', ikon: BookText, warna: '#0d9488' },
    { kunci: 'program_studi', label: 'Program Studi', ikon: BookOpen, warna: '#db2777' },
    { kunci: 'mahasiswa_cuti', label: 'Mahasiswa Cuti', ikon: PauseCircle, warna: '#ca8a04' },
    { kunci: 'mahasiswa_lulus', label: 'Mahasiswa Lulus', ikon: CircleCheck, warna: '#4f46e5' },
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
            class="kartu flex flex-col gap-1 border-l-4 px-4 py-3"
            :class="k.href ? 'transition-shadow hover:shadow-md' : ''"
            :style="{ borderLeftColor: k.warna }"
        >
            <div class="flex items-center justify-between gap-2">
                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">{{ k.label }}</p>
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                    :style="{ backgroundColor: `${k.warna}1a`, color: k.warna }"
                >
                    <component :is="k.ikon" class="size-4" />
                </span>
            </div>
            <p class="text-2xl font-bold tabular-nums" :style="{ color: k.warna }">{{ angka.format(k.nilai) }}</p>
            <p v-if="k.catatan" class="teks-bantu -mt-1">{{ k.catatan }}</p>
        </component>
    </div>
</template>
