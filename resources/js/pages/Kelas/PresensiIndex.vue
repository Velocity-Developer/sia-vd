<script setup lang="ts">
import FilterKonten, { type NilaiFilter, type Opsi } from '@/components/FilterKonten.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, jam, statusTampil, type JenisPertemuan, type StatusPertemuan } from '@/lib/presensi';
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
    terlewat?: boolean;
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
    kelas: { data: Kelas[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
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
</script>

<template>
    <Head title="Presensi" />
    <AppLayout :breadcrumbs="[{ title: 'Presensi', href: rute('presensi.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Presensi</h1>
                        <p class="deskripsi-halaman">
                            {{
                                isAdmin
                                    ? 'Pertemuan, jurnal perkuliahan, dan kehadiran mahasiswa di seluruh kelas.'
                                    : 'Buka pertemuan saat kuliah dimulai, catat kehadiran mahasiswa, lalu isi jurnal sebelum menutupnya.'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <Link :href="rute('presensi.izin.index')">
                                Pengajuan izin
                                <span v-if="props.izinMenunggu" class="rounded-full bg-[#dd5b00] px-1.5 text-xs text-white">{{
                                    props.izinMenunggu
                                }}</span>
                            </Link>
                        </Button>
                        <Button v-if="isAdmin" as-child variant="outline">
                            <Link :href="route('admin.presensi.laporan-dosen')">Laporan kehadiran dosen</Link>
                        </Button>
                    </div>
                </div>

                <div
                    v-if="props.bisaLingkupProdi"
                    class="flex gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium dark:border-border dark:bg-card sm:w-fit"
                >
                    <Link
                        v-for="l in [
                            { value: 'saya', label: 'Kelas saya' },
                            { value: 'prodi', label: 'Kelas prodi (kaprodi)' },
                        ]"
                        :key="l.value"
                        :href="rute('presensi.index', l.value === 'prodi' ? { lingkup: 'prodi' } : {})"
                        class="flex-1 rounded-md px-4 py-2 text-center sm:flex-none"
                        :class="
                            props.lingkup === l.value
                                ? 'bg-[#0075de] text-white'
                                : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                        "
                    >
                        {{ l.label }}
                    </Link>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Pertemuan hari ini</h2>
                    <div v-if="props.hariIni.length" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="item in props.hariIni"
                            :key="item.id"
                            :href="rute('presensi.pertemuan.show', item.id)"
                            class="rounded-lg border border-[#e6e6e6] p-4 transition-colors hover:border-[#0075de] hover:bg-[#f6f9fd] dark:border-border dark:hover:bg-accent"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-black dark:text-foreground">
                                        {{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}
                                    </p>
                                    <p class="teks-bantu">
                                        {{ item.kelas_kuliah?.kode_kelas }} · Pertemuan {{ item.pertemuan_ke }}
                                        <template v-if="item.jenis !== 'kuliah'"> · {{ JENIS_PERTEMUAN[item.jenis] }}</template>
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusTampil(item).kelas">
                                    {{ statusTampil(item).label }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-[#31302e] dark:text-foreground">
                                {{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir)
                                }}<template v-if="item.ruang"> · {{ item.ruang.kode_ruang }}</template>
                            </p>
                            <p v-if="item.status === 'dijadwalkan' && !item.terlewat" class="teks-bantu mt-1">
                                Bisa dimulai pukul {{ jam(item.jam_mulai) }}
                            </p>
                        </Link>
                    </div>
                    <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Tidak ada pertemuan terjadwal hari ini.</p>
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

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kelas</th>
                                    <th>Mata Kuliah</th>
                                    <th v-if="tampilDosen">Dosen</th>
                                    <th>Pertemuan</th>
                                    <th>Peserta</th>
                                    <th>Rata-rata hadir</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.kelas.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.kelas.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ item.kode_kelas }}</span>
                                        <span class="teks-bantu block">{{ item.tahun_akademik?.tahun }} {{ item.tahun_akademik?.semester }}</span>
                                    </td>
                                    <td>
                                        <span class="block">{{ item.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="teks-bantu block">{{ item.mata_kuliah?.kode_matkul }}</span>
                                    </td>
                                    <td v-if="tampilDosen">{{ item.dosen?.user?.name ?? '-' }}</td>
                                    <td>
                                        <span class="block">{{ item.pertemuan_selesai }} / {{ item.jumlah_pertemuan }} selesai</span>
                                        <span v-if="item.pertemuan_dibuat < item.jumlah_pertemuan" class="block text-xs text-[#dd5b00]">
                                            {{
                                                item.pertemuan_dibuat === 0
                                                    ? 'Pertemuan belum dibuat'
                                                    : `${item.pertemuan_dibuat} dari ${item.jumlah_pertemuan} dibuat`
                                            }}
                                        </span>
                                    </td>
                                    <td class="tabular-nums">{{ item.jumlah_peserta }}</td>
                                    <td class="tabular-nums">{{ item.rata_kehadiran === null ? '-' : `${item.rata_kehadiran}%` }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="sm">
                                                <Link :href="rute('presensi.kelas', item.id)">Kelola</Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.kelas.data.length" class="baris-kosong">
                                    <td :colspan="tampilDosen ? 8 : 7" class="tabel-kosong">Tidak ada kelas yang cocok dengan filter.</td>
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
