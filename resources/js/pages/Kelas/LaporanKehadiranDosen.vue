<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';

type Baris = {
    dosen: string;
    nidn: string | null;
    kelas_id: number;
    kode_kelas: string;
    mata_kuliah: string | null;
    rencana: number;
    terlaksana: number;
    dibatalkan: number;
    oleh_pengganti: number;
    terlambat: number;
    tanpa_jurnal: number;
    rata_kehadiran: number | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    baris: Baris[];
    toleransi: number;
    tahunAkademikId: number | null;
    prodiId: number | null;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const saring = (perubahan: Record<string, string | number | null>) =>
    router.get(
        route('admin.presensi.laporan-dosen'),
        { tahun_akademik_id: props.tahunAkademikId, prodi_id: props.prodiId, ...perubahan },
        { preserveScroll: true },
    );
const urlCsv = computed(() =>
    route('admin.presensi.laporan-dosen', { tahun_akademik_id: props.tahunAkademikId, prodi_id: props.prodiId, format: 'csv' }),
);

// Baris dikelompokkan per dosen dengan subtotal.
const perDosen = computed(() => {
    const grup = new Map<string, Baris[]>();
    props.baris.forEach((b) => grup.set(`${b.dosen}|${b.nidn}`, [...(grup.get(`${b.dosen}|${b.nidn}`) ?? []), b]));

    return [...grup.values()].map((kelas) => ({
        dosen: kelas[0].dosen,
        nidn: kelas[0].nidn,
        kelas,
        rencana: kelas.reduce((n, b) => n + b.rencana, 0),
        terlaksana: kelas.reduce((n, b) => n + b.terlaksana, 0),
        terlambat: kelas.reduce((n, b) => n + b.terlambat, 0),
        tanpa_jurnal: kelas.reduce((n, b) => n + b.tanpa_jurnal, 0),
    }));
});

const th = 'px-3 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
const td = 'px-3 py-2.5 text-sm text-[#31302e]';
const sel = 'h-10 rounded-lg border border-[#dddddd] bg-white px-3 text-sm';
</script>

<template>
    <Head title="Laporan Kehadiran Dosen" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi', href: route('admin.presensi.index') },
            { title: 'Laporan Kehadiran Dosen', href: route('admin.presensi.laporan-dosen') },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Laporan Kehadiran Dosen</h1>
                        <p class="max-w-2xl text-sm text-[#615d59]">
                            Pertemuan terlaksana dibanding rencana per kelas. Terlambat = jam masuk lewat {{ props.toleransi }} menit dari jam mulai;
                            tanpa jurnal = pertemuan selesai tanpa topik.
                        </p>
                    </div>
                    <a
                        :href="urlCsv"
                        class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-[#e6e6e6] bg-white px-4 text-sm font-medium"
                    >
                        <Download class="size-4" /> Unduh CSV
                    </a>
                </div>

                <div class="flex flex-wrap gap-3">
                    <select
                        :value="props.tahunAkademikId ?? ''"
                        aria-label="Tahun akademik"
                        :class="sel"
                        @change="saring({ tahun_akademik_id: ($event.target as HTMLSelectElement).value })"
                    >
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                    <select
                        :value="props.prodiId ?? ''"
                        aria-label="Program studi"
                        :class="sel"
                        @change="saring({ prodi_id: ($event.target as HTMLSelectElement).value || null })"
                    >
                        <option value="">Semua program studi</option>
                        <option v-for="p in props.prodiOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[980px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th :class="th">Dosen / Kelas</th>
                                    <th :class="[th, 'text-center']">Terlaksana</th>
                                    <th :class="[th, 'text-center']">Batal</th>
                                    <th :class="[th, 'text-center']">Oleh pengganti</th>
                                    <th :class="[th, 'text-center']">Masuk terlambat</th>
                                    <th :class="[th, 'text-center']">Tanpa jurnal</th>
                                    <th :class="[th, 'text-center']">Rata hadir mhs</th>
                                </tr>
                            </thead>
                            <tbody v-for="d in perDosen" :key="`${d.dosen}${d.nidn}`" class="divide-y divide-[#e6e6e6] border-b border-[#e6e6e6]">
                                <tr class="bg-[#fbfaf9]">
                                    <td class="px-3 py-2.5 font-medium text-black">
                                        {{ d.dosen }} <span class="text-xs font-normal text-[#a39e98]">{{ d.nidn }}</span>
                                    </td>
                                    <td :class="[td, 'text-center font-medium']">{{ d.terlaksana }} / {{ d.rencana }}</td>
                                    <td :class="td" />
                                    <td :class="td" />
                                    <td :class="[td, 'text-center font-medium', d.terlambat ? 'text-[#dd5b00]' : '']">{{ d.terlambat }}</td>
                                    <td :class="[td, 'text-center font-medium', d.tanpa_jurnal ? 'text-[#dd5b00]' : '']">{{ d.tanpa_jurnal }}</td>
                                    <td :class="td" />
                                </tr>
                                <tr v-for="b in d.kelas" :key="b.kelas_id">
                                    <td :class="[td, 'pl-7']">
                                        <Link :href="route('admin.presensi.kelas', b.kelas_id)" class="text-[#0075de] hover:underline">{{
                                            b.kode_kelas
                                        }}</Link>
                                        <span class="text-xs text-[#a39e98]"> · {{ b.mata_kuliah }}</span>
                                    </td>
                                    <td :class="[td, 'text-center']">{{ b.terlaksana }} / {{ b.rencana }}</td>
                                    <td :class="[td, 'text-center']">{{ b.dibatalkan }}</td>
                                    <td :class="[td, 'text-center']">{{ b.oleh_pengganti }}</td>
                                    <td :class="[td, 'text-center']">{{ b.terlambat }}</td>
                                    <td :class="[td, 'text-center']">{{ b.tanpa_jurnal }}</td>
                                    <td :class="[td, 'text-center']">{{ b.rata_kehadiran === null ? '-' : `${b.rata_kehadiran}%` }}</td>
                                </tr>
                            </tbody>
                            <tbody v-if="!perDosen.length">
                                <tr>
                                    <td colspan="7" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tidak ada kelas pada tahun akademik ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
