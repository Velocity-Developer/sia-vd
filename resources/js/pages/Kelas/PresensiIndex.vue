<script setup lang="ts">
import FilterKonten, { type NilaiFilter, type Opsi } from '@/components/FilterKonten.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, STATUS_PERTEMUAN, jam, type JenisPertemuan, type StatusPertemuan } from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Kelas = {
    id: number;
    kode_kelas: string;
    jumlah_pertemuan: number;
    pertemuan_dibuat: number;
    pertemuan_selesai: number;
    jumlah_peserta: number;
    rata_kehadiran: number | null;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    dosen?: { user?: { name: string } | null } | null;
    tahun_akademik?: { tahun: string; semester: string } | null;
};
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
    kelas: { data: Kelas[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    hariIni: PertemuanHariIni[];
    peran: Peran;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
    lingkup: 'saya' | 'prodi';
    bisaLingkupProdi: boolean;
    izinMenunggu: number;
}>();

const rute = rutePeran(props.peran);
const isAdmin = computed(() => props.peran === 'admin');
// Kolom dosen perlu terlihat bila daftar tidak hanya berisi kelas sendiri.
const tampilDosen = computed(() => isAdmin.value || props.lingkup === 'prodi');
const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
</script>

<template>
    <Head title="Presensi" />
    <AppLayout :breadcrumbs="[{ title: 'Presensi', href: rute('presensi.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Presensi</h1>
                        <p class="text-sm text-[#615d59]">
                            {{
                                isAdmin
                                    ? 'Pertemuan, jurnal perkuliahan, dan kehadiran mahasiswa di seluruh kelas.'
                                    : 'Buka pertemuan saat kuliah dimulai, catat kehadiran mahasiswa, lalu isi jurnal sebelum menutupnya.'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="rute('presensi.izin.index')">
                            <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black">
                                Pengajuan izin
                                <span v-if="props.izinMenunggu" class="ml-1.5 rounded-full bg-[#dd5b00] px-1.5 text-xs text-white">{{
                                    props.izinMenunggu
                                }}</span>
                            </Button>
                        </Link>
                        <Link v-if="isAdmin" :href="route('admin.presensi.laporan-dosen')">
                            <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black">Laporan kehadiran dosen</Button>
                        </Link>
                    </div>
                </div>

                <div v-if="props.bisaLingkupProdi" class="flex gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium sm:w-fit">
                    <Link
                        v-for="l in [
                            { value: 'saya', label: 'Kelas saya' },
                            { value: 'prodi', label: 'Kelas prodi (kaprodi)' },
                        ]"
                        :key="l.value"
                        :href="rute('presensi.index', l.value === 'prodi' ? { lingkup: 'prodi' } : {})"
                        class="flex-1 rounded-md px-4 py-2 text-center sm:flex-none"
                        :class="props.lingkup === l.value ? 'bg-[#0075de] text-white' : 'text-[#615d59] hover:bg-[#f6f5f4]'"
                    >
                        {{ l.label }}
                    </Link>
                </div>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pertemuan hari ini</h2>
                    <div v-if="props.hariIni.length" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="item in props.hariIni"
                            :key="item.id"
                            :href="rute('presensi.pertemuan.show', item.id)"
                            class="rounded-lg border border-[#e6e6e6] p-4 transition-colors hover:border-[#0075de] hover:bg-[#f6f9fd]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-black">{{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}</p>
                                    <p class="text-xs text-[#a39e98]">
                                        {{ item.kelas_kuliah?.kode_kelas }} · Pertemuan {{ item.pertemuan_ke }}
                                        <template v-if="item.jenis !== 'kuliah'"> · {{ JENIS_PERTEMUAN[item.jenis] }}</template>
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_PERTEMUAN[item.status].kelas">
                                    {{ STATUS_PERTEMUAN[item.status].label }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-[#31302e]">
                                {{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir)
                                }}<template v-if="item.ruang"> · {{ item.ruang.kode_ruang }}</template>
                            </p>
                        </Link>
                    </div>
                    <p v-else class="mt-2 text-sm text-[#615d59]">Tidak ada pertemuan terjadwal hari ini.</p>
                </section>

                <FilterKonten
                    :url="rute('presensi.index')"
                    :filter="props.filter"
                    :tahun-akademik-options="props.tahunAkademikOptions"
                    :prodi-options="props.prodiOptions"
                    :mata-kuliah-options="props.mataKuliahOptions"
                    :kelas-options="props.kelasOptions"
                    :dosen-options="props.dosenOptions"
                    :total="props.kelas.total"
                    placeholder="Cari kode kelas atau mata kuliah"
                    :tambahan="props.lingkup === 'prodi' ? { lingkup: 'prodi' } : {}"
                />

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[860px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th :class="th">Kelas</th>
                                    <th :class="th">Mata Kuliah</th>
                                    <th v-if="tampilDosen" :class="th">Dosen</th>
                                    <th :class="th">Pertemuan</th>
                                    <th :class="th">Peserta</th>
                                    <th :class="th">Rata-rata hadir</th>
                                    <th :class="[th, 'text-right']">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="item in props.kelas.data" :key="item.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px]">
                                        <span class="block font-medium text-black">{{ item.kode_kelas }}</span>
                                        <span class="block text-xs text-[#a39e98]"
                                            >{{ item.tahun_akademik?.tahun }} {{ item.tahun_akademik?.semester }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ item.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.mata_kuliah?.kode_matkul }}</span>
                                    </td>
                                    <td v-if="tampilDosen" class="px-4 py-3 text-[15px] text-[#31302e]">{{ item.dosen?.user?.name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ item.pertemuan_selesai }} / {{ item.jumlah_pertemuan }} selesai</span>
                                        <span v-if="item.pertemuan_dibuat < item.jumlah_pertemuan" class="block text-xs text-[#dd5b00]">
                                            {{
                                                item.pertemuan_dibuat === 0
                                                    ? 'Pertemuan belum dibuat'
                                                    : `${item.pertemuan_dibuat} dari ${item.jumlah_pertemuan} dibuat`
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ item.jumlah_peserta }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        {{ item.rata_kehadiran === null ? '-' : `${item.rata_kehadiran}%` }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link :href="rute('presensi.kelas', item.id)" class="text-sm font-medium text-[#0075de] hover:underline">
                                            Kelola
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!props.kelas.data.length">
                                    <td :colspan="tampilDosen ? 7 : 6" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tidak ada kelas yang cocok dengan filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.kelas.links" :total="props.kelas.total" />
            </div>
        </div>
    </AppLayout>
</template>
