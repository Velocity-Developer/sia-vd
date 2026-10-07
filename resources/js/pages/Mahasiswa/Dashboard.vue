<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, formatTanggal, infoStatusPresensi, jam, statusTampil, type JenisPertemuan, type StatusPertemuan } from '@/lib/presensi';
import { STATUS_TAGIHAN_REMIDI, rupiah, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookOpenCheck, CalendarDays, CircleCheck, ClipboardList, GraduationCap, TriangleAlert, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';

type Pesan = { teks: string; penting: boolean };
type KunciPengingat = 'semester' | 'cuti' | 'jadwal' | 'tugasAkhir';

// Urutan tampil kelompok pengingat.
const KELOMPOK_PENGINGAT: { kunci: KunciPengingat; judul: string; labelTautan: string }[] = [
    { kunci: 'semester', judul: 'Semester ini', labelTautan: 'Buka' },
    { kunci: 'cuti', judul: 'Cuti akademik', labelTautan: 'Buka Pengajuan Cuti' },
    { kunci: 'jadwal', judul: 'Perubahan jadwal kuliah', labelTautan: 'Buka presensi' },
    { kunci: 'tugasAkhir', judul: 'Tugas akhir & wisuda', labelTautan: 'Buka halaman tugas akhir' },
];

const props = defineProps<{
    /** Null bila akun belum punya profil mahasiswa. */
    ringkasan: {
        nama: string;
        nim: string | null;
        prodi: string | null;
        angkatan: number | null;
        status: string | null;
        dosen_wali: string | null;
        ipk: number | null;
        sks_lulus: number;
        sks_semester: number;
        krs_tersimpan: boolean;
    } | null;
    tahunAkademik: string | null;
    /** Pengingat per kelompok; null bila kelompok itu tidak punya pesan atau pengguna tak berizin. */
    pengingat: Record<KunciPengingat, { pesan: Pesan[]; tautan: string } | null> | null;
    peringatanPresensi?: { kelas_id: number; nama_matkul: string; kode_kelas: string; persen: number; sisa_absen: number }[] | null;
    remidiMahasiswa?: {
        tagihan: { id: number; matkul: string | null; total: number; status: StatusTagihanRemidi; batas_bayar: string | null }[];
        ujian: { id: number; matkul: string | null; tanggal: string; jam_mulai: string; jam_akhir: string; label_mode: string }[];
    } | null;
    susulanMahasiswa?: {
        pengajuan: { id: number; ujian_id: number; judul: string }[];
        tagihan: { id: number; judul: string; total: number; status: StatusTagihanRemidi; batas_bayar: string }[];
        ujian: { id: number; judul: string; tanggal: string; jam_mulai: string; jam_akhir: string; label_mode: string }[];
    } | null;
    kuliahHariIni?:
        | {
              id: number;
              matkul: string | null;
              kode_kelas: string | null;
              pertemuan_ke: number;
              jenis: JenisPertemuan;
              jam_mulai: string;
              jam_akhir: string;
              ruang: string | null;
              status: StatusPertemuan;
              terlewat: boolean;
              presensi: string | null;
          }[]
        | null;
    tugasMendatang?: { id: number; judul: string; matkul: string | null; tenggat: string }[] | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Beranda', href: '/mahasiswa' }];

const namaDepan = computed(() => props.ringkasan?.nama.split(' ')[0] ?? '');

const WARNA_STATUS: Record<string, string> = {
    Aktif: 'bg-[#f0faf2] text-[#17702b]',
    Cuti: 'bg-[#fff6e0] text-[#8a5a00]',
    Lulus: 'bg-[#f2f9ff] text-[#0075de]',
};

const pengingat = computed(() =>
    KELOMPOK_PENGINGAT.flatMap((k) => {
        const isi = props.pengingat?.[k.kunci];
        return isi ? [{ ...k, ...isi }] : [];
    }),
);

const jumlahPenting = computed(() => pengingat.value.reduce((n, g) => n + g.pesan.filter((p) => p.penting).length, 0));

const sisaWaktu = (iso: string) => {
    const selisihJam = (new Date(iso).getTime() - Date.now()) / 3_600_000;
    if (selisihJam < 24) return { teks: `${Math.max(1, Math.round(selisihJam))} jam lagi`, mendesak: true };
    const hari = Math.floor(selisihJam / 24);
    return { teks: `${hari} hari lagi`, mendesak: hari <= 1 };
};
// Foto profil pengguna yang masuk (diunggah sendiri di Pengaturan Profil atau oleh admin).
const foto = computed(() => usePage<SharedData>().props.auth.user?.avatar ?? null);
</script>

<template>
    <Head title="Beranda" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Halo, {{ namaDepan || 'Mahasiswa' }}</h1>
                        <p class="deskripsi-halaman">
                            <template v-if="props.tahunAkademik">Tahun akademik {{ props.tahunAkademik }}. </template>Ringkasan studi dan hal yang
                            perlu Anda tindak lanjuti.
                        </p>
                    </div>
                </div>

                <div v-if="!props.ringkasan" class="alert-gagal">Akun ini belum terhubung dengan data mahasiswa. Hubungi bagian akademik.</div>

                <template v-else>
                    <section class="kartu flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#f2f9ff] text-[#0075de]">
                            <img v-if="foto" :src="foto" :alt="props.ringkasan.nama" class="size-full object-cover" />
                            <UserRound v-else class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-semibold text-black dark:text-foreground">{{ props.ringkasan.nama }}</p>
                                <span
                                    v-if="props.ringkasan.status"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="WARNA_STATUS[props.ringkasan.status] ?? 'bg-[#f6f5f4] text-[#615d59]'"
                                    >{{ props.ringkasan.status }}</span
                                >
                            </div>
                            <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                                {{ props.ringkasan.nim ?? '-' }} · {{ props.ringkasan.prodi ?? '-' }}
                                <template v-if="props.ringkasan.angkatan"> · Angkatan {{ props.ringkasan.angkatan }}</template>
                            </p>
                        </div>
                        <div class="text-sm sm:text-right">
                            <p class="teks-bantu">Dosen wali</p>
                            <p class="font-medium text-black dark:text-foreground">{{ props.ringkasan.dosen_wali ?? '-' }}</p>
                        </div>
                    </section>

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <Link href="/mahasiswa/transkrip" class="kartu flex flex-col gap-1 px-4 py-3 transition-colors hover:border-[#0075de]">
                            <div class="flex items-center justify-between gap-2">
                                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">IPK</p>
                                <GraduationCap class="size-4 text-[#a39e98]" />
                            </div>
                            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">
                                {{ props.ringkasan.ipk !== null ? props.ringkasan.ipk.toFixed(2) : '-' }}
                            </p>
                        </Link>
                        <div class="kartu flex flex-col gap-1 px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">SKS Lulus</p>
                                <BookOpenCheck class="size-4 text-[#a39e98]" />
                            </div>
                            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ props.ringkasan.sks_lulus }}</p>
                        </div>
                        <Link href="/mahasiswa/krs" class="kartu flex flex-col gap-1 px-4 py-3 transition-colors hover:border-[#0075de]">
                            <div class="flex items-center justify-between gap-2">
                                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">SKS Semester Ini</p>
                                <ClipboardList class="size-4 text-[#a39e98]" />
                            </div>
                            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ props.ringkasan.sks_semester }}</p>
                            <p class="teks-bantu -mt-1">{{ props.ringkasan.krs_tersimpan ? 'KRS tersimpan' : 'KRS belum disimpan' }}</p>
                        </Link>
                        <div class="kartu flex flex-col gap-1 px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Kuliah Hari Ini</p>
                                <CalendarDays class="size-4 text-[#a39e98]" />
                            </div>
                            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ props.kuliahHariIni?.length ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="flex flex-col gap-6 lg:col-span-2">
                            <section class="kartu p-6">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="judul-bagian">Pengingat</h2>
                                    <span v-if="jumlahPenting" class="rounded-full bg-[#fdf3ec] px-2.5 py-0.5 text-xs font-medium text-[#a84400]"
                                        >{{ jumlahPenting }} perlu tindakan</span
                                    >
                                </div>
                                <div v-if="pengingat.length" class="mt-2 divide-y divide-[#e6e6e6] dark:divide-border">
                                    <div v-for="g in pengingat" :key="g.judul" class="py-3">
                                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">{{ g.judul }}</p>
                                        <ul class="mt-1.5 flex flex-col gap-1.5">
                                            <li v-for="(p, i) in g.pesan" :key="i" class="flex items-start gap-2 text-sm">
                                                <TriangleAlert v-if="p.penting" class="mt-0.5 size-4 shrink-0 text-[#dd5b00]" />
                                                <span v-else class="mt-2 size-1.5 shrink-0 rounded-full bg-[#a39e98]" />
                                                <span
                                                    :class="p.penting ? 'text-black dark:text-foreground' : 'text-[#31302e] dark:text-foreground'"
                                                    >{{ p.teks }}</span
                                                >
                                            </li>
                                        </ul>
                                        <Link :href="g.tautan" class="mt-1.5 inline-block text-sm font-medium text-[#0075de] hover:underline">{{
                                            g.labelTautan
                                        }}</Link>
                                    </div>
                                </div>
                                <p v-else class="mt-3 flex items-center gap-2 text-sm text-[#615d59] dark:text-muted-foreground">
                                    <CircleCheck class="size-4 text-[#1aae39]" /> Tidak ada yang perlu ditindaklanjuti.
                                </p>
                            </section>

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

                            <section v-if="props.remidiMahasiswa?.tagihan.length || props.remidiMahasiswa?.ujian.length" class="kartu p-6">
                                <h2 class="judul-bagian">Remidi</h2>
                                <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                                    <li
                                        v-for="t in props.remidiMahasiswa.tagihan"
                                        :key="`t${t.id}`"
                                        class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                                    >
                                        <span class="font-medium text-black dark:text-foreground"
                                            >Tagihan remidi {{ t.matkul }}
                                            <span class="font-normal text-[#615d59]">· {{ rupiah(t.total) }}</span></span
                                        >
                                        <span class="flex items-center gap-2">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="STATUS_TAGIHAN_REMIDI[t.status].kelas"
                                                >{{ STATUS_TAGIHAN_REMIDI[t.status].label }}</span
                                            >
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
                                        <span class="text-[#31302e] dark:text-foreground"
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
                                v-if="
                                    props.susulanMahasiswa?.pengajuan.length ||
                                    props.susulanMahasiswa?.tagihan.length ||
                                    props.susulanMahasiswa?.ujian.length
                                "
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
                                        <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-medium text-[#0075de]"
                                            >Menunggu persetujuan</span
                                        >
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
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="STATUS_TAGIHAN_REMIDI[t.status].kelas"
                                                >{{ STATUS_TAGIHAN_REMIDI[t.status].label }}</span
                                            >
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
                                        <Link :href="route('mahasiswa.ujian.show', u.id)" class="font-medium text-[#0075de] hover:underline">{{
                                            u.judul
                                        }}</Link>
                                        <span class="text-[#31302e] dark:text-foreground"
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
                        </div>

                        <div class="flex flex-col gap-6">
                            <section v-if="props.kuliahHariIni" class="kartu p-6">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="judul-bagian">Kuliah hari ini</h2>
                                    <Link href="/mahasiswa/jadwal" class="text-sm font-medium text-[#0075de] hover:underline">Jadwal</Link>
                                </div>
                                <ul v-if="props.kuliahHariIni.length" class="mt-3 flex flex-col gap-3">
                                    <li
                                        v-for="k in props.kuliahHariIni"
                                        :key="k.id"
                                        class="rounded-lg border border-[#e6e6e6] p-3 dark:border-border"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-medium text-black dark:text-foreground">{{ k.matkul }}</p>
                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="statusTampil(k).kelas">{{
                                                statusTampil(k).label
                                            }}</span>
                                        </div>
                                        <p class="teks-bantu">
                                            {{ jam(k.jam_mulai) }}–{{ jam(k.jam_akhir) }}<template v-if="k.ruang"> · {{ k.ruang }}</template> ·
                                            {{ k.kode_kelas }} · Pertemuan {{ k.pertemuan_ke
                                            }}<template v-if="k.jenis !== 'kuliah'"> · {{ JENIS_PERTEMUAN[k.jenis] }}</template>
                                        </p>
                                        <span
                                            v-if="k.presensi && infoStatusPresensi(k.presensi)"
                                            class="mt-1.5 inline-block rounded-full border px-2 py-0.5 text-xs font-medium"
                                            :class="infoStatusPresensi(k.presensi)!.kelas"
                                            >Presensi: {{ infoStatusPresensi(k.presensi)!.label }}</span
                                        >
                                    </li>
                                </ul>
                                <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Tidak ada kuliah hari ini.</p>
                            </section>

                            <section v-if="props.tugasMendatang" class="kartu p-6">
                                <h2 class="judul-bagian">Tugas 7 hari ke depan</h2>
                                <ul v-if="props.tugasMendatang.length" class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                                    <li v-for="t in props.tugasMendatang" :key="t.id" class="py-2.5">
                                        <Link
                                            :href="route('mahasiswa.tugas.show', t.id)"
                                            class="text-sm font-medium text-[#0075de] hover:underline"
                                            >{{ t.judul }}</Link
                                        >
                                        <p class="teks-bantu">{{ t.matkul }}</p>
                                        <p class="text-xs">
                                            <span class="text-[#31302e] dark:text-foreground"
                                                >{{ formatTanggal(t.tenggat, false) }}, {{ t.tenggat.slice(11, 16) }}</span
                                            >
                                            <span
                                                class="ml-1 font-medium"
                                                :class="
                                                    sisaWaktu(t.tenggat).mendesak ? 'text-[#dd5b00]' : 'text-[#615d59] dark:text-muted-foreground'
                                                "
                                                >· {{ sisaWaktu(t.tenggat).teks }}</span
                                            >
                                        </p>
                                    </li>
                                </ul>
                                <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Tidak ada tugas yang belum dikumpulkan.</p>
                            </section>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
