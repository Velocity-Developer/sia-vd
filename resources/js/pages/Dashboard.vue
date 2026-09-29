<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, formatTanggal, jam, statusTampil, type JenisPertemuan, type StatusPertemuan } from '@/lib/presensi';
import { STATUS_TAGIHAN_REMIDI, rupiah, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
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
    name?: string;
    /** Beranda mahasiswa: mata kuliah dengan kehadiran di bawah/mendekati batas. */
    peringatanPresensi?: { kelas_id: number; nama_matkul: string; kode_kelas: string; persen: number; sisa_absen: number }[] | null;
    /** Beranda dosen: ringkasan presensi hari ini. */
    presensiDosen?: { hariIni: PertemuanHariIni[]; izinMenunggu: number; mahasiswaBerisiko: number; minKehadiran: number } | null;
    /** Beranda mahasiswa: tagihan remidi yang belum lunas dan jadwal remidi mendatang. */
    remidiMahasiswa?: {
        tagihan: { id: number; matkul: string | null; total: number; status: StatusTagihanRemidi; batas_bayar: string | null }[];
        ujian: { id: number; matkul: string | null; tanggal: string; jam_mulai: string; jam_akhir: string; label_mode: string }[];
    } | null;
    /** Beranda mahasiswa: pengajuan, tagihan, dan jadwal ujian susulan. */
    susulanMahasiswa?: {
        pengajuan: { id: number; ujian_id: number; judul: string }[];
        tagihan: { id: number; judul: string; total: number; status: StatusTagihanRemidi; batas_bayar: string }[];
        ujian: { id: number; judul: string; tanggal: string; jam_mulai: string; jam_akhir: string; label_mode: string }[];
    } | null;
    /** Beranda dosen: ujian susulan yang perlu disiapkan atau dinilai. */
    susulanDosen?: { siapkan: UjianSusulanRingkas[]; nilai: UjianSusulanRingkas[] } | null;
    /** Beranda semua peran: langkah tugas akhir, pendadaran, dan wisuda yang perlu ditindaklanjuti. */
    pengingatTugasAkhir?: { pesan: { teks: string; penting: boolean }[]; tautan: string } | null;
    /** Beranda mahasiswa: pertemuan mendatang yang baru dijadwal ulang. */
    jadwalPertemuanBerubah?: { pesan: { teks: string; penting: boolean }[]; tautan: string } | null;
    /** Beranda dosen: kelas yang menunggu langkah remidi. */
    remidiDosen?: {
        kunci_daftar: KelasRingkas[];
        isi_nilai: KelasRingkas[];
    } | null;
}>();

type KelasRingkas = { id: number; kode_kelas: string; matkul: string | null };
type UjianSusulanRingkas = { id: number; judul: string; kode_kelas: string | null; tanggal: string };
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Dashboard</h1>
                        <p class="deskripsi-halaman">Ringkasan hal yang perlu Anda perhatikan dan tindak lanjuti.</p>
                    </div>
                </div>
                <section v-if="props.peringatanPresensi?.length" class="kartu p-6">
                    <h2 class="judul-bagian flex items-center gap-2 text-[#dd5b00] dark:text-[#dd5b00]">
                        <TriangleAlert class="size-5" /> Perhatikan kehadiran Anda
                    </h2>
                    <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                        <li
                            v-for="k in props.peringatanPresensi"
                            :key="k.kelas_id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                        >
                            <span class="font-medium text-black dark:text-foreground"
                                >{{ k.nama_matkul }} <span class="font-normal text-[#a39e98]">· {{ k.kode_kelas }}</span></span
                            >
                            <span :class="k.sisa_absen < 0 ? 'font-medium text-[#dd5b00]' : 'text-[#dd5b00]'">
                                Kehadiran {{ k.persen }}% ·
                                {{ k.sisa_absen < 0 ? 'sudah melewati batas tidak hadir' : `sisa boleh tidak hadir ${k.sisa_absen}` }}
                            </span>
                        </li>
                    </ul>
                    <Link :href="route('mahasiswa.presensi')" class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Lihat riwayat presensi</Link
                    >
                </section>

                <section v-if="props.jadwalPertemuanBerubah" class="kartu p-6">
                    <h2 class="judul-bagian">Perubahan jadwal kuliah</h2>
                    <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                        <li v-for="(p, i) in props.jadwalPertemuanBerubah.pesan" :key="i" class="flex items-start gap-2 py-2.5 text-sm">
                            <TriangleAlert v-if="p.penting" class="mt-0.5 size-4 shrink-0 text-[#dd5b00]" />
                            <span :class="p.penting ? 'text-black dark:text-foreground' : 'text-[#31302e] dark:text-foreground'">{{ p.teks }}</span>
                        </li>
                    </ul>
                    <Link :href="props.jadwalPertemuanBerubah.tautan" class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Buka presensi</Link
                    >
                </section>

                <section v-if="props.pengingatTugasAkhir" class="kartu p-6">
                    <h2 class="judul-bagian">Tugas akhir & wisuda</h2>
                    <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                        <li v-for="(p, i) in props.pengingatTugasAkhir.pesan" :key="i" class="flex items-start gap-2 py-2.5 text-sm">
                            <TriangleAlert v-if="p.penting" class="mt-0.5 size-4 shrink-0 text-[#dd5b00]" />
                            <span :class="p.penting ? 'text-black dark:text-foreground' : 'text-[#31302e] dark:text-foreground'">{{ p.teks }}</span>
                        </li>
                    </ul>
                    <Link :href="props.pengingatTugasAkhir.tautan" class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Buka halaman tugas akhir</Link
                    >
                </section>

                <section v-if="props.remidiMahasiswa?.tagihan.length || props.remidiMahasiswa?.ujian.length" class="kartu p-6">
                    <h2 class="judul-bagian">Remidi</h2>
                    <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                        <li
                            v-for="t in props.remidiMahasiswa.tagihan"
                            :key="`t${t.id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                        >
                            <span class="font-medium text-black dark:text-foreground"
                                >Tagihan remidi {{ t.matkul }} <span class="font-normal text-[#615d59]">· {{ rupiah(t.total) }}</span></span
                            >
                            <span class="flex items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_TAGIHAN_REMIDI[t.status].kelas">{{
                                    STATUS_TAGIHAN_REMIDI[t.status].label
                                }}</span>
                                <span v-if="t.batas_bayar && t.status !== 'menunggu_verifikasi'" class="text-[#dd5b00]"
                                    >bayar sebelum {{ formatTanggal(t.batas_bayar, false) }}</span
                                >
                            </span>
                        </li>
                        <li
                            v-for="u in props.remidiMahasiswa.ujian"
                            :key="`u${u.id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                        >
                            <Link :href="route('mahasiswa.ujian.show', u.id)" class="font-medium text-[#0075de] hover:underline"
                                >Ujian remidi {{ u.matkul }}</Link
                            >
                            <span class="text-[#31302e]"
                                >{{ formatTanggal(u.tanggal) }}, {{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }} · {{ u.label_mode }}</span
                            >
                        </li>
                    </ul>
                    <Link
                        v-if="props.remidiMahasiswa.tagihan.length"
                        :href="route('mahasiswa.info-biaya-kuliah')"
                        class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Unggah bukti bayar di Biaya Kuliah</Link
                    >
                </section>

                <section
                    v-if="props.susulanMahasiswa?.pengajuan.length || props.susulanMahasiswa?.tagihan.length || props.susulanMahasiswa?.ujian.length"
                    class="kartu p-6"
                >
                    <h2 class="judul-bagian">Ujian susulan</h2>
                    <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                        <li
                            v-for="p in props.susulanMahasiswa.pengajuan"
                            :key="`p${p.id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                        >
                            <Link :href="route('mahasiswa.ujian.show', p.ujian_id)" class="font-medium text-[#0075de] hover:underline"
                                >Pengajuan susulan {{ p.judul }}</Link
                            >
                            <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-medium text-[#0075de]">Menunggu persetujuan</span>
                        </li>
                        <li
                            v-for="t in props.susulanMahasiswa.tagihan"
                            :key="`t${t.id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                        >
                            <span class="font-medium text-black dark:text-foreground"
                                >Tagihan {{ t.judul }} <span class="font-normal text-[#615d59]">· {{ rupiah(t.total) }}</span></span
                            >
                            <span class="flex items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_TAGIHAN_REMIDI[t.status].kelas">{{
                                    STATUS_TAGIHAN_REMIDI[t.status].label
                                }}</span>
                                <span v-if="t.status !== 'menunggu_verifikasi'" class="text-[#dd5b00]"
                                    >bayar sebelum {{ formatTanggal(t.batas_bayar, false) }}</span
                                >
                            </span>
                        </li>
                        <li
                            v-for="u in props.susulanMahasiswa.ujian"
                            :key="`u${u.id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                        >
                            <Link :href="route('mahasiswa.ujian.show', u.id)" class="font-medium text-[#0075de] hover:underline">{{ u.judul }}</Link>
                            <span class="text-[#31302e]"
                                >{{ formatTanggal(u.tanggal) }}, {{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }} · {{ u.label_mode }}</span
                            >
                        </li>
                    </ul>
                    <Link
                        v-if="props.susulanMahasiswa.tagihan.length"
                        :href="route('mahasiswa.info-biaya-kuliah')"
                        class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Unggah bukti bayar di Biaya Kuliah</Link
                    >
                </section>

                <section v-if="props.susulanDosen?.siapkan.length || props.susulanDosen?.nilai.length" class="kartu p-6">
                    <h2 class="judul-bagian">Ujian susulan</h2>
                    <div v-if="props.susulanDosen.siapkan.length" class="mt-3 text-sm">
                        <p class="text-[#615d59] dark:text-muted-foreground">Terjadwal, siapkan soalnya:</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <Link
                                v-for="u in props.susulanDosen.siapkan"
                                :key="u.id"
                                :href="route('dosen.ujian.show', u.id)"
                                class="rounded-full bg-[#f2f9ff] px-3 py-1 font-medium text-[#0075de] hover:underline"
                                >{{ u.judul }} · {{ u.kode_kelas }} · {{ formatTanggal(u.tanggal, false) }}</Link
                            >
                        </div>
                    </div>
                    <div v-if="props.susulanDosen.nilai.length" class="mt-3 text-sm">
                        <p class="text-[#615d59] dark:text-muted-foreground">Sudah selesai, isi dan rilis nilainya:</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <Link
                                v-for="u in props.susulanDosen.nilai"
                                :key="u.id"
                                :href="route('dosen.ujian.show', u.id)"
                                class="rounded-full bg-[#fff6e0] px-3 py-1 font-medium text-[#8a5a00] hover:underline"
                                >{{ u.judul }} · {{ u.kode_kelas }}</Link
                            >
                        </div>
                    </div>
                </section>

                <section v-if="props.remidiDosen?.kunci_daftar.length || props.remidiDosen?.isi_nilai.length" class="kartu p-6">
                    <h2 class="judul-bagian">Remidi menunggu Anda</h2>
                    <div v-if="props.remidiDosen.kunci_daftar.length" class="mt-3 text-sm">
                        <p class="text-[#615d59] dark:text-muted-foreground">Nilai sudah final, daftar remidi belum dikunci:</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <Link
                                v-for="k in props.remidiDosen.kunci_daftar"
                                :key="k.id"
                                :href="`${route('dosen.kelas-kuliah.show', k.id)}#daftar-remidi`"
                                class="rounded-full bg-[#fff6e0] px-3 py-1 font-medium text-[#8a5a00] hover:underline"
                                >{{ k.kode_kelas }} · {{ k.matkul }}</Link
                            >
                        </div>
                    </div>
                    <div v-if="props.remidiDosen.isi_nilai.length" class="mt-3 text-sm">
                        <p class="text-[#615d59] dark:text-muted-foreground">
                            Ujian remidi selesai, isi nilai dan huruf akhir peserta lalu finalisasi:
                        </p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <Link
                                v-for="k in props.remidiDosen.isi_nilai"
                                :key="k.id"
                                :href="`${route('dosen.kelas-kuliah.show', k.id)}#daftar-remidi`"
                                class="rounded-full bg-[#f2f9ff] px-3 py-1 font-medium text-[#0075de] hover:underline"
                                >{{ k.kode_kelas }} · {{ k.matkul }}</Link
                            >
                        </div>
                    </div>
                </section>

                <section v-if="props.presensiDosen" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h2 class="judul-bagian">Presensi hari ini</h2>
                        <div class="flex flex-wrap gap-2 text-sm">
                            <Link
                                v-if="props.presensiDosen.izinMenunggu"
                                :href="route('dosen.presensi.izin.index')"
                                class="rounded-full bg-[#fff6e0] px-3 py-1 font-medium text-[#8a5a00] hover:underline"
                                >{{ props.presensiDosen.izinMenunggu }} pengajuan izin menunggu</Link
                            >
                            <span v-if="props.presensiDosen.mahasiswaBerisiko" class="rounded-full bg-[#fdf3ec] px-3 py-1 font-medium text-[#a84400]">
                                {{ props.presensiDosen.mahasiswaBerisiko }} mahasiswa di bawah {{ props.presensiDosen.minKehadiran }}%
                            </span>
                        </div>
                    </div>
                    <div v-if="props.presensiDosen.hariIni.length" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="p in props.presensiDosen.hariIni"
                            :key="p.id"
                            :href="route('dosen.presensi.pertemuan.show', p.id)"
                            class="rounded-lg border border-[#e6e6e6] p-4 hover:border-[#0075de] dark:border-border"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-medium text-black dark:text-foreground">{{ p.kelas_kuliah?.mata_kuliah?.nama_matkul }}</p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="statusTampil(p).kelas">{{
                                    statusTampil(p).label
                                }}</span>
                            </div>
                            <p class="teks-bantu">
                                {{ p.kelas_kuliah?.kode_kelas }} · Pertemuan {{ p.pertemuan_ke
                                }}<template v-if="p.jenis !== 'kuliah'"> · {{ JENIS_PERTEMUAN[p.jenis] }}</template>
                            </p>
                            <p class="mt-1 text-sm text-[#31302e]">
                                {{ jam(p.jam_mulai) }}–{{ jam(p.jam_akhir) }}<template v-if="p.ruang"> · {{ p.ruang.kode_ruang }}</template>
                            </p>
                            <p v-if="p.status === 'dijadwalkan' && !p.terlewat" class="teks-bantu mt-1">Bisa dimulai pukul {{ jam(p.jam_mulai) }}</p>
                        </Link>
                    </div>
                    <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Tidak ada pertemuan hari ini.</p>
                </section>

                <div class="grid auto-rows-min gap-6 md:grid-cols-3">
                    <div class="kartu relative aspect-video overflow-hidden">
                        <PlaceholderPattern />
                    </div>
                    <div class="kartu relative aspect-video overflow-hidden">
                        <PlaceholderPattern />
                    </div>
                    <div class="kartu relative aspect-video overflow-hidden">
                        <PlaceholderPattern />
                    </div>
                </div>
                <div class="kartu relative min-h-[100vh] flex-1 overflow-hidden md:min-h-min">
                    <PlaceholderPattern />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
