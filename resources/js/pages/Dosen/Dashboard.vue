<script setup lang="ts">
import GrafikKampus, { type GrafikKampusData } from '@/components/GrafikKampus.vue';
import KartuStatistikKampus, { type KunciStatistikKampus } from '@/components/KartuStatistikKampus.vue';
import { useFitur } from '@/composables/useFitur';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, formatTanggal, jam, statusTampil, type JenisPertemuan, type StatusPertemuan } from '@/lib/presensi';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, CircleCheck, ClipboardList, GraduationCap, TriangleAlert, UserRound, Users, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';

type Pesan = { teks: string; penting: boolean };
type KelasRingkas = { id: number; kode_kelas: string; matkul: string | null };
type UjianSusulanRingkas = { id: number; judul: string; kode_kelas: string | null; tanggal: string };
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
    /** Null bila akun belum punya profil dosen. */
    ringkasan: {
        nama: string;
        nidn: string | null;
        prodi: string | null;
        jabatan: string | null;
        kelas_diampu: number;
        mahasiswa_diajar: number;
        mahasiswa_wali: number;
        bimbingan_ta: number;
    } | null;
    tahunAkademik: string | null;
    /** Angka kampus (jumlah mahasiswa, mahasiswa aktif, dosen aktif, program studi). */
    statistikKampus?: Partial<Record<KunciStatistikKampus, number>>;
    grafikKampus?: GrafikKampusData;
    presensiDosen?: { hariIni: PertemuanHariIni[]; izinMenunggu: number; mahasiswaBerisiko: number; minKehadiran: number } | null;
    /** Pengumpulan tugas & jawaban esai quiz yang belum dinilai, per tugas/quiz. */
    perluDinilai?: { jenis: string; judul: string; kelas: string; jumlah: number; tautan: string }[] | null;
    /** Kelas yang batas input nilainya dekat dan belum difinalisasi. */
    pengingatNilai?: { pesan: Pesan[]; tautan: string } | null;
    remidiDosen?: { kunci_daftar: KelasRingkas[]; isi_nilai: KelasRingkas[] } | null;
    susulanDosen?: { siapkan: UjianSusulanRingkas[]; nilai: UjianSusulanRingkas[] } | null;
    pengingatTugasAkhir?: { pesan: Pesan[]; tautan: string } | null;
    kelas?:
        | {
              id: number;
              kode_kelas: string;
              matkul: string | null;
              sks: number | null;
              peserta: number;
              pertemuan_selesai: number;
              jumlah_pertemuan: number;
              nilai_final: boolean;
          }[]
        | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Beranda', href: '/dosen' }];

const { can } = usePermissions();
const angka = new Intl.NumberFormat('id-ID');

const fitur = useFitur();
const kartuStatistik = computed(() => {
    const r = props.ringkasan;
    if (!r) return [];
    return [
        { label: 'Kelas Diampu', nilai: r.kelas_diampu, ikon: ClipboardList, href: can('dosen.kelas-kuliah') ? '/dosen/kelas-kuliah' : null },
        { label: 'Mahasiswa Diajar', nilai: r.mahasiswa_diajar, ikon: Users, href: can('dosen.mahasiswa-kelas') ? '/dosen/mahasiswa-kelas' : null },
        { label: 'Mahasiswa Wali', nilai: r.mahasiswa_wali, ikon: UsersRound, href: null },
        // Tanpa fitur pendadaran tidak ada pembimbing TA di sistem.
        ...(fitur.aktif('pendadaran')
            ? [{ label: 'Bimbingan TA', nilai: r.bimbingan_ta, ikon: GraduationCap, href: can('dosen.bimbingan') ? '/dosen/bimbingan' : null }]
            : []),
    ];
});

const pengingat = computed(() =>
    [
        { judul: 'Batas input nilai', isi: props.pengingatNilai, labelTautan: 'Buka Kelas Kuliah' },
        { judul: 'Tugas akhir & pendadaran', isi: props.pengingatTugasAkhir, labelTautan: 'Buka Bimbingan TA' },
    ].flatMap((g) => (g.isi ? [{ judul: g.judul, labelTautan: g.labelTautan, ...g.isi }] : [])),
);

const adaRemidi = computed(() => !!(props.remidiDosen?.kunci_daftar.length || props.remidiDosen?.isi_nilai.length));
const adaSusulan = computed(() => !!(props.susulanDosen?.siapkan.length || props.susulanDosen?.nilai.length));
const jumlahPerluDinilai = computed(() => (props.perluDinilai ?? []).reduce((n, t) => n + t.jumlah, 0));

const persen = (bagian: number, keseluruhan: number) => (keseluruhan > 0 ? Math.min(100, Math.round((bagian / keseluruhan) * 100)) : 0);
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
                        <h1 class="judul-halaman">Selamat datang{{ props.ringkasan ? `, ${props.ringkasan.nama}` : '' }}</h1>
                        <p class="deskripsi-halaman">
                            <template v-if="props.tahunAkademik">Tahun akademik {{ props.tahunAkademik }}. </template>Kelas, penilaian, dan hal yang
                            perlu Anda tindak lanjuti.
                        </p>
                    </div>
                </div>

                <div v-if="!props.ringkasan" class="alert-gagal">Akun ini belum terhubung dengan data dosen. Hubungi bagian akademik.</div>

                <template v-else>
                    <section class="kartu flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#f2f9ff] text-[#0075de]">
                            <img v-if="foto" :src="foto" :alt="props.ringkasan.nama" class="size-full object-cover" />
                            <UserRound v-else class="size-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold text-black dark:text-foreground">{{ props.ringkasan.nama }}</p>
                            <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                                NIDN {{ props.ringkasan.nidn ?? '-' }} · {{ props.ringkasan.prodi ?? '-' }}
                            </p>
                        </div>
                        <div v-if="props.ringkasan.jabatan" class="text-sm sm:text-right">
                            <p class="teks-bantu">Jabatan fungsional</p>
                            <p class="font-medium text-black dark:text-foreground">{{ props.ringkasan.jabatan }}</p>
                        </div>
                    </section>

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <component
                            :is="k.href ? Link : 'div'"
                            v-for="k in kartuStatistik"
                            :key="k.label"
                            :href="k.href ?? undefined"
                            class="kartu flex flex-col gap-1 px-4 py-3"
                            :class="k.href ? 'transition-colors hover:border-[#0075de]' : ''"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="teks-bantu font-medium uppercase tracking-[0.06em]">{{ k.label }}</p>
                                <component :is="k.ikon" class="size-4 shrink-0 text-[#a39e98]" />
                            </div>
                            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ angka.format(k.nilai) }}</p>
                        </component>
                    </div>

                    <section v-if="props.statistikKampus && Object.keys(props.statistikKampus).length" class="flex flex-col gap-3">
                        <h2 class="judul-bagian">Statistik kampus</h2>
                        <KartuStatistikKampus :statistik="props.statistikKampus" />
                        <GrafikKampus v-if="props.grafikKampus" :grafik="props.grafikKampus" />
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
                                <span
                                    v-if="props.presensiDosen.mahasiswaBerisiko"
                                    class="rounded-full bg-[#fdf3ec] px-3 py-1 font-medium text-[#a84400]"
                                >
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
                                <p class="mt-1 text-sm text-[#31302e] dark:text-foreground">
                                    {{ jam(p.jam_mulai) }}–{{ jam(p.jam_akhir) }}<template v-if="p.ruang"> · {{ p.ruang.kode_ruang }}</template>
                                </p>
                                <p v-if="p.status === 'dijadwalkan' && !p.terlewat" class="teks-bantu mt-1">
                                    Bisa dimulai pukul {{ jam(p.jam_mulai) }}
                                </p>
                            </Link>
                        </div>
                        <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Tidak ada pertemuan hari ini.</p>
                    </section>

                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="flex flex-col gap-6 lg:col-span-2">
                            <section v-if="props.perluDinilai" class="kartu p-6">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="judul-bagian">Perlu dinilai</h2>
                                    <span
                                        v-if="jumlahPerluDinilai"
                                        class="rounded-full bg-[#fff6e0] px-2.5 py-0.5 text-xs font-medium tabular-nums text-[#8a5a00]"
                                        >{{ angka.format(jumlahPerluDinilai) }} jawaban</span
                                    >
                                </div>
                                <ul v-if="props.perluDinilai.length" class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                                    <li v-for="t in props.perluDinilai" :key="t.tautan">
                                        <Link
                                            :href="t.tautan"
                                            class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-[#f6f5f4] dark:hover:bg-muted"
                                        >
                                            <span
                                                class="flex h-7 min-w-9 shrink-0 items-center justify-center rounded-full bg-[#fdf3ec] px-2 text-sm font-semibold tabular-nums text-[#a84400]"
                                                >{{ t.jumlah }}</span
                                            >
                                            <span class="min-w-0 flex-1 text-sm">
                                                <span class="font-medium text-black dark:text-foreground">{{ t.judul }}</span>
                                                <span class="block text-xs text-[#a39e98]">{{ t.jenis }} · {{ t.kelas }}</span>
                                            </span>
                                            <ChevronRight class="size-4 shrink-0 text-[#a39e98] group-hover:text-[#0075de]" />
                                        </Link>
                                    </li>
                                </ul>
                                <p v-else class="mt-3 flex items-center gap-2 text-sm text-[#615d59] dark:text-muted-foreground">
                                    <CircleCheck class="size-4 text-[#1aae39]" /> Semua tugas dan jawaban esai sudah dinilai.
                                </p>
                            </section>

                            <section v-if="pengingat.length" class="kartu p-6">
                                <h2 class="judul-bagian">Pengingat</h2>
                                <div class="mt-2 divide-y divide-[#e6e6e6] dark:divide-border">
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
                            </section>

                            <section v-if="adaRemidi && props.remidiDosen" class="kartu p-6">
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

                            <section v-if="adaSusulan && props.susulanDosen" class="kartu p-6">
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
                        </div>

                        <section v-if="props.kelas" class="kartu h-fit p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="judul-bagian">Kelas saya</h2>
                                <Link href="/dosen/kelas-kuliah" class="text-sm font-medium text-[#0075de] hover:underline">Semua</Link>
                            </div>
                            <ul v-if="props.kelas.length" class="mt-3 flex flex-col gap-2">
                                <li v-for="k in props.kelas" :key="k.id">
                                    <Link
                                        :href="route('dosen.kelas-kuliah.show', k.id)"
                                        class="block rounded-lg border border-[#e6e6e6] p-3 hover:border-[#0075de] dark:border-border"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-medium text-black dark:text-foreground">{{ k.matkul }}</p>
                                            <span
                                                v-if="k.nilai_final"
                                                class="shrink-0 rounded-full bg-[#f0faf2] px-2 py-0.5 text-xs font-medium text-[#17702b]"
                                                >Nilai final</span
                                            >
                                        </div>
                                        <p class="teks-bantu">
                                            {{ k.kode_kelas }}<template v-if="k.sks"> · {{ k.sks }} SKS</template> · {{ k.peserta }} mahasiswa
                                        </p>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-[#f6f5f4] dark:bg-muted">
                                                <span
                                                    class="block h-full rounded-full bg-[#0075de]"
                                                    :style="{ width: `${persen(k.pertemuan_selesai, k.jumlah_pertemuan)}%` }"
                                                />
                                            </span>
                                            <span class="text-xs tabular-nums text-[#615d59] dark:text-muted-foreground"
                                                >{{ k.pertemuan_selesai }}/{{ k.jumlah_pertemuan }} pertemuan</span
                                            >
                                        </div>
                                    </Link>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                                Belum ada kelas yang Anda ampu di tahun akademik aktif.
                            </p>
                        </section>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
