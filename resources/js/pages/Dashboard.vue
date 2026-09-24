<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, STATUS_PERTEMUAN, jam, type JenisPertemuan, type StatusPertemuan } from '@/lib/presensi';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type PertemuanHariIni = {
    id: number;
    pertemuan_ke: number;
    jam_mulai: string;
    jam_akhir: string;
    jenis: JenisPertemuan;
    status: StatusPertemuan;
    ruang?: { kode_ruang: string } | null;
    kelas_kuliah?: { kode_kelas: string; mata_kuliah?: { nama_matkul: string } | null } | null;
};

const props = defineProps<{
    name?: string;
    /** Beranda mahasiswa: mata kuliah dengan kehadiran di bawah/mendekati batas. */
    peringatanPresensi?: { kelas_id: number; nama_matkul: string; kode_kelas: string; persen: number; sisa_absen: number }[] | null;
    /** Beranda dosen: ringkasan presensi hari ini. */
    presensiDosen?: { hariIni: PertemuanHariIni[]; izinMenunggu: number; mahasiswaBerisiko: number; minKehadiran: number } | null;
}>();

const kartu = 'rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <section v-if="props.peringatanPresensi?.length" :class="kartu">
                <h2 class="flex items-center gap-2 font-semibold text-[#b42318]"><TriangleAlert class="size-5" /> Perhatikan kehadiran Anda</h2>
                <ul class="mt-3 divide-y divide-[#e6e6e6]">
                    <li
                        v-for="k in props.peringatanPresensi"
                        :key="k.kelas_id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                    >
                        <span class="font-medium text-black"
                            >{{ k.nama_matkul }} <span class="font-normal text-[#a39e98]">· {{ k.kode_kelas }}</span></span
                        >
                        <span :class="k.sisa_absen < 0 ? 'text-[#b42318]' : 'text-[#dd5b00]'">
                            Kehadiran {{ k.persen }}% ·
                            {{ k.sisa_absen < 0 ? 'sudah melewati batas tidak hadir' : `sisa boleh tidak hadir ${k.sisa_absen}` }}
                        </span>
                    </li>
                </ul>
                <Link :href="route('mahasiswa.presensi')" class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                    >Lihat riwayat presensi</Link
                >
            </section>

            <section v-if="props.presensiDosen" :class="kartu">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="font-semibold text-black">Presensi hari ini</h2>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <Link
                            v-if="props.presensiDosen.izinMenunggu"
                            :href="route('dosen.presensi.izin.index')"
                            class="rounded-full bg-[#fff6e0] px-3 py-1 font-medium text-[#8a5a00] hover:underline"
                            >{{ props.presensiDosen.izinMenunggu }} pengajuan izin menunggu</Link
                        >
                        <span v-if="props.presensiDosen.mahasiswaBerisiko" class="rounded-full bg-[#fdecea] px-3 py-1 font-medium text-[#b42318]">
                            {{ props.presensiDosen.mahasiswaBerisiko }} mahasiswa di bawah {{ props.presensiDosen.minKehadiran }}%
                        </span>
                    </div>
                </div>
                <div v-if="props.presensiDosen.hariIni.length" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="p in props.presensiDosen.hariIni"
                        :key="p.id"
                        :href="route('dosen.presensi.pertemuan.show', p.id)"
                        class="rounded-lg border border-[#e6e6e6] p-4 hover:border-[#0075de]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-medium text-black">{{ p.kelas_kuliah?.mata_kuliah?.nama_matkul }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="STATUS_PERTEMUAN[p.status].kelas">{{
                                STATUS_PERTEMUAN[p.status].label
                            }}</span>
                        </div>
                        <p class="text-xs text-[#a39e98]">
                            {{ p.kelas_kuliah?.kode_kelas }} · Pertemuan {{ p.pertemuan_ke
                            }}<template v-if="p.jenis !== 'kuliah'"> · {{ JENIS_PERTEMUAN[p.jenis] }}</template>
                        </p>
                        <p class="mt-1 text-sm text-[#31302e]">
                            {{ jam(p.jam_mulai) }}–{{ jam(p.jam_akhir) }}<template v-if="p.ruang"> · {{ p.ruang.kode_ruang }}</template>
                        </p>
                    </Link>
                </div>
                <p v-else class="mt-2 text-sm text-[#615d59]">Tidak ada pertemuan hari ini.</p>
            </section>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border md:min-h-min">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
