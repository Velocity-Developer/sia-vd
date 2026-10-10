<script setup lang="ts">
import GrafikKampus, { type GrafikKampusData } from '@/components/GrafikKampus.vue';
import KartuStatistikKampus, { type KunciStatistikKampus } from '@/components/KartuStatistikKampus.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { rupiah } from '@/lib/tagihanRemidi';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarRange, ChevronRight, CircleCheck, LayoutGrid, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

type Tindakan = { judul: string; keterangan: string | null; jumlah: number; tautan: string; penting: boolean };
type BuktiBayar = {
    jenis: string;
    mahasiswa: string | null;
    nim: string | null;
    keterangan: string | null;
    total: number;
    diunggah: string | null;
    tautan: string;
};

const props = defineProps<{
    tahunAkademik: {
        label: string;
        tanggal_mulai: string | null;
        tanggal_akhir: string | null;
        tanggal_krs_awal: string | null;
        tanggal_krs_akhir: string | null;
    } | null;
    /** Hanya kartu yang diizinkan untuk role pengguna yang dikirim server. */
    statistik: Partial<Record<KunciStatistikKampus, number>>;
    /** Grafik status & sebaran mahasiswa; null bila role tidak boleh melihat data mahasiswa. */
    grafik: GrafikKampusData | null;
    /** Null bila role tidak memegang satu pun izin sumber tindak lanjut. */
    tindakan: Tindakan[] | null;
    /** Rekap tagihan semester tahun akademik aktif; null bila tanpa izin admin.tagihan atau belum ada TA aktif. */
    tagihan: {
        total: number;
        terbit: number;
        belum_terbit: number;
        lunas: number;
        menunggu: number;
        belum_bayar: number;
        nominal_terbit: number;
        nominal_lunas: number;
        tautan: string;
    } | null;
    /** Bukti bayar terbaru yang menunggu verifikasi; null tanpa izin admin.tagihan. */
    buktiTerbaru: BuktiBayar[] | null;
    perkuliahanHariIni: { dijadwalkan: number; berlangsung: number; selesai: number; tautan: string } | null;
    mahasiswaPerProdi: { nama: string; jumlah: number }[] | null;
    pengingatTugasAkhir: { pesan: { teks: string; penting: boolean }[]; tautan: string } | null;
}>();

const { can } = usePermissions();
const page = usePage<SharedData>();

// Judul mengikuti nama role (mis. "Dashboard Direktorat Keuangan"), karena halaman ini juga dipakai role karyawan.
const judul = computed(() => (page.props.auth.role?.name ? `Dashboard ${page.props.auth.role.name}` : 'Dashboard'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: judul.value, href: '/admin' }]);

const angka = new Intl.NumberFormat('id-ID');

const tautanStatistik = computed(() => ({
    mahasiswa: can('admin.users.mahasiswa') ? '/admin/users/mahasiswa' : null,
    mahasiswa_aktif: can('admin.users.mahasiswa') ? '/admin/users/mahasiswa' : null,
    dosen_aktif: can('admin.users.dosen') ? '/admin/users/dosen' : null,
    kelas_kuliah: can('admin.kelas-kuliah') ? route('admin.kelas-kuliah.index') : null,
    mata_kuliah: can('admin.mata-kuliah') ? route('admin.mata-kuliah.index') : null,
    program_studi: can('admin.program-studi') ? route('admin.program-studi.index') : null,
    mahasiswa_cuti: can('admin.pengajuan-cuti') ? route('admin.pengajuan-cuti.index') : null,
}));

const totalTindakan = computed(() => (props.tindakan ?? []).reduce((jumlah, t) => jumlah + t.jumlah, 0));

const persen = (bagian: number, keseluruhan: number) => (keseluruhan > 0 ? Math.round((bagian / keseluruhan) * 100) : 0);

// Batang tagihan: proporsi dari seluruh mahasiswa aktif, termasuk yang belum ditagih.
const segmenTagihan = computed(() => {
    const t = props.tagihan;
    if (!t) return [];
    return [
        { label: 'Lunas', nilai: t.lunas, warna: 'bg-[#1aae39]' },
        { label: 'Menunggu verifikasi', nilai: t.menunggu, warna: 'bg-[#0075de]' },
        { label: 'Belum bayar', nilai: t.belum_bayar, warna: 'bg-[#dd5b00]' },
        { label: 'Belum ditagih', nilai: t.belum_terbit, warna: 'bg-[#d8d5d2] dark:bg-muted' },
    ];
});

const prodiTerbanyak = computed(() => Math.max(1, ...(props.mahasiswaPerProdi ?? []).map((p) => p.jumlah)));

const masaKrs = computed(() => {
    const ta = props.tahunAkademik;
    if (!ta?.tanggal_krs_awal || !ta.tanggal_krs_akhir) return null;
    const hariIni = new Date().toLocaleDateString('en-CA');
    const status = hariIni < ta.tanggal_krs_awal ? 'belum dibuka' : hariIni > ta.tanggal_krs_akhir ? 'sudah ditutup' : 'sedang dibuka';
    return { teks: `${formatTanggal(ta.tanggal_krs_awal, false)} – ${formatTanggal(ta.tanggal_krs_akhir, false)}`, status };
});

// Tata letak mengikuti bagian yang benar-benar tampil untuk role ini: kolom kiri (daftar) dan kolom kanan (ringkasan).
const adaKiri = computed(
    () => props.tindakan !== null || !!props.buktiTerbaru || !!props.pengingatTugasAkhir || (!!props.mahasiswaPerProdi && !props.grafik),
);
const adaKanan = computed(() => !!props.tagihan || !!props.perkuliahanHariIni || !!masaKrs.value);
const kosong = computed(() => !adaKiri.value && !adaKanan.value && Object.keys(props.statistik).length === 0 && !props.grafik);
</script>

<template>
    <Head :title="judul" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ judul }}</h1>
                        <p class="deskripsi-halaman">Ringkasan dan pekerjaan yang menunggu tindak lanjut, sesuai hak akses role Anda.</p>
                    </div>
                    <div v-if="props.tahunAkademik" class="kartu flex items-center gap-3 px-4 py-2.5">
                        <CalendarRange class="size-5 shrink-0 text-[#0075de]" />
                        <div class="text-sm">
                            <p class="font-semibold text-black dark:text-foreground">TA {{ props.tahunAkademik.label }}</p>
                            <p class="teks-bantu">
                                {{ formatTanggal(props.tahunAkademik.tanggal_mulai, false) }} –
                                {{ formatTanggal(props.tahunAkademik.tanggal_akhir, false) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="!props.tahunAkademik" class="alert-gagal">
                    Belum ada tahun akademik aktif. Kelas kuliah, KRS, dan tagihan semester mengikuti tahun akademik aktif.
                    <Link v-if="can('admin.tahun-akademik')" :href="route('admin.tahun-akademik.index')" class="font-medium underline"
                        >Atur tahun akademik</Link
                    >
                </div>

                <KartuStatistikKampus :statistik="props.statistik" :tautan="tautanStatistik" />

                <GrafikKampus v-if="props.grafik" :grafik="props.grafik" />

                <section v-if="kosong" class="kartu flex flex-col items-center gap-2 px-6 py-12 text-center">
                    <LayoutGrid class="size-8 text-[#a39e98]" />
                    <p class="font-medium text-black dark:text-foreground">Belum ada ringkasan untuk role Anda</p>
                    <p class="max-w-md text-sm text-[#615d59] dark:text-muted-foreground">
                        Menu yang dapat Anda buka tersedia di sidebar. Ringkasan di halaman ini mengikuti hak akses yang diatur di Kelola Role.
                    </p>
                </section>

                <div v-if="adaKiri || adaKanan" class="grid gap-6" :class="{ 'lg:grid-cols-3': adaKiri && adaKanan }">
                    <div v-if="adaKiri" class="flex flex-col gap-6" :class="{ 'lg:col-span-2': adaKanan }">
                        <section v-if="props.tindakan !== null" class="kartu p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="judul-bagian">Perlu ditindaklanjuti</h2>
                                <span
                                    v-if="totalTindakan"
                                    class="rounded-full bg-[#fff6e0] px-2.5 py-0.5 text-xs font-medium tabular-nums text-[#8a5a00]"
                                    >{{ angka.format(totalTindakan) }} menunggu</span
                                >
                            </div>
                            <ul v-if="props.tindakan.length" class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                                <li v-for="t in props.tindakan" :key="t.tautan">
                                    <Link
                                        :href="t.tautan"
                                        class="group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 hover:bg-[#f6f5f4] dark:hover:bg-muted"
                                    >
                                        <span
                                            class="flex h-7 min-w-9 shrink-0 items-center justify-center rounded-full px-2 text-sm font-semibold tabular-nums"
                                            :class="
                                                t.penting
                                                    ? 'bg-[#fdf3ec] text-[#a84400]'
                                                    : 'bg-[#f6f5f4] text-[#31302e] dark:bg-muted dark:text-foreground'
                                            "
                                            >{{ angka.format(t.jumlah) }}</span
                                        >
                                        <span class="min-w-0 flex-1 text-sm">
                                            <span class="font-medium text-black dark:text-foreground">{{ t.judul }}</span>
                                            <span v-if="t.keterangan" class="text-[#a39e98]"> · {{ t.keterangan }}</span>
                                        </span>
                                        <ChevronRight class="size-4 shrink-0 text-[#a39e98] group-hover:text-[#0075de]" />
                                    </Link>
                                </li>
                            </ul>
                            <p v-else class="mt-3 flex items-center gap-2 text-sm text-[#615d59] dark:text-muted-foreground">
                                <CircleCheck class="size-4 text-[#1aae39]" /> Tidak ada pekerjaan yang menunggu.
                            </p>
                        </section>

                        <section v-if="props.buktiTerbaru" class="flex flex-col gap-3">
                            <h2 class="judul-bagian">Bukti bayar terbaru menunggu verifikasi</h2>
                            <div class="tabel-wadah">
                                <div class="tabel-gulir">
                                    <table class="tabel min-w-[640px]">
                                        <thead>
                                            <tr>
                                                <th>Mahasiswa</th>
                                                <th>Tagihan</th>
                                                <th class="text-right">Nominal</th>
                                                <th>Diunggah</th>
                                                <th class="kolom-aksi"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(b, i) in props.buktiTerbaru" :key="i">
                                                <td>
                                                    <p class="font-medium text-black dark:text-foreground">{{ b.mahasiswa ?? '-' }}</p>
                                                    <p class="teks-bantu">{{ b.nim ?? '-' }}</p>
                                                </td>
                                                <td>
                                                    <p>{{ b.jenis }}</p>
                                                    <p v-if="b.keterangan" class="teks-bantu">{{ b.keterangan }}</p>
                                                </td>
                                                <td class="text-right tabular-nums">{{ rupiah(b.total) }}</td>
                                                <td class="whitespace-nowrap">{{ formatTanggal(b.diunggah, false) }}</td>
                                                <td class="kolom-aksi">
                                                    <Link :href="b.tautan" class="text-sm font-medium text-[#0075de] hover:underline">Periksa</Link>
                                                </td>
                                            </tr>
                                            <tr v-if="!props.buktiTerbaru.length" class="baris-kosong">
                                                <td colspan="5" class="tabel-kosong">Tidak ada bukti bayar yang menunggu verifikasi.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <section v-if="props.pengingatTugasAkhir" class="kartu p-6">
                            <h2 class="judul-bagian">Tugas akhir & wisuda</h2>
                            <ul class="mt-3 divide-y divide-[#e6e6e6] dark:divide-border">
                                <li v-for="(p, i) in props.pengingatTugasAkhir.pesan" :key="i" class="flex items-start gap-2 py-2.5 text-sm">
                                    <TriangleAlert v-if="p.penting" class="mt-0.5 size-4 shrink-0 text-[#dd5b00]" />
                                    <span class="text-[#31302e] dark:text-foreground">{{ p.teks }}</span>
                                </li>
                            </ul>
                            <Link
                                :href="props.pengingatTugasAkhir.tautan"
                                class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                                >Buka halaman tugas akhir</Link
                            >
                        </section>

                        <section v-if="props.mahasiswaPerProdi && !props.grafik" class="kartu p-6">
                            <h2 class="judul-bagian">Mahasiswa aktif per program studi</h2>
                            <ul v-if="props.mahasiswaPerProdi.length" class="mt-4 flex flex-col gap-3">
                                <li
                                    v-for="p in props.mahasiswaPerProdi"
                                    :key="p.nama"
                                    class="grid grid-cols-[minmax(0,12rem)_1fr_auto] items-center gap-3 text-sm"
                                >
                                    <span class="truncate text-[#31302e] dark:text-foreground" :title="p.nama">{{ p.nama }}</span>
                                    <span class="h-2 overflow-hidden rounded-full bg-[#f6f5f4] dark:bg-muted">
                                        <span
                                            class="block h-full rounded-full bg-[#0075de]"
                                            :style="{ width: `${persen(p.jumlah, prodiTerbanyak)}%` }"
                                        />
                                    </span>
                                    <span class="w-10 text-right font-medium tabular-nums text-black dark:text-foreground">{{
                                        angka.format(p.jumlah)
                                    }}</span>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada program studi.</p>
                        </section>
                    </div>

                    <div v-if="adaKanan" :class="adaKiri ? 'flex flex-col gap-6' : 'grid items-start gap-6 sm:grid-cols-2 lg:grid-cols-3'">
                        <section v-if="props.tagihan" class="kartu p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="judul-bagian">Tagihan semester</h2>
                                <Link :href="props.tagihan.tautan" class="text-sm font-medium text-[#0075de] hover:underline">Lihat</Link>
                            </div>
                            <p class="mt-3 text-2xl font-bold tabular-nums text-black dark:text-foreground">
                                {{ persen(props.tagihan.lunas, props.tagihan.total) }}%
                                <span class="text-sm font-normal text-[#615d59] dark:text-muted-foreground">mahasiswa aktif lunas</span>
                            </p>
                            <div
                                class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-[#f6f5f4] dark:bg-muted"
                                role="img"
                                aria-label="Komposisi status tagihan"
                            >
                                <span
                                    v-for="s in segmenTagihan"
                                    :key="s.label"
                                    :class="s.warna"
                                    class="h-full"
                                    :style="{ width: `${persen(s.nilai, props.tagihan.total)}%` }"
                                />
                            </div>
                            <ul class="mt-3 flex flex-col gap-1.5 text-sm">
                                <li v-for="s in segmenTagihan" :key="s.label" class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full" :class="s.warna" />
                                    <span class="flex-1 text-[#31302e] dark:text-foreground">{{ s.label }}</span>
                                    <span class="font-medium tabular-nums text-black dark:text-foreground">{{ angka.format(s.nilai) }}</span>
                                </li>
                            </ul>
                            <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-[#e6e6e6] pt-4 text-sm dark:border-border">
                                <div>
                                    <dt class="teks-bantu">Diterbitkan</dt>
                                    <dd class="font-semibold tabular-nums text-black dark:text-foreground">
                                        {{ rupiah(props.tagihan.nominal_terbit) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="teks-bantu">Diterima</dt>
                                    <dd class="font-semibold tabular-nums text-[#1aae39]">{{ rupiah(props.tagihan.nominal_lunas) }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section v-if="props.perkuliahanHariIni" class="kartu p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="judul-bagian">Perkuliahan hari ini</h2>
                                <Link :href="props.perkuliahanHariIni.tautan" class="text-sm font-medium text-[#0075de] hover:underline"
                                    >Presensi</Link
                                >
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-[#f6f5f4] px-2 py-3 dark:bg-muted">
                                    <p class="text-xl font-bold tabular-nums text-black dark:text-foreground">
                                        {{ props.perkuliahanHariIni.dijadwalkan }}
                                    </p>
                                    <p class="teks-bantu">Terjadwal</p>
                                </div>
                                <div class="rounded-lg bg-[#f2f9ff] px-2 py-3 dark:bg-muted">
                                    <p class="text-xl font-bold tabular-nums text-[#0075de]">{{ props.perkuliahanHariIni.berlangsung }}</p>
                                    <p class="teks-bantu">Berlangsung</p>
                                </div>
                                <div class="rounded-lg bg-[#f0faf2] px-2 py-3 dark:bg-muted">
                                    <p class="text-xl font-bold tabular-nums text-[#1aae39]">{{ props.perkuliahanHariIni.selesai }}</p>
                                    <p class="teks-bantu">Selesai</p>
                                </div>
                            </div>
                        </section>

                        <section v-if="masaKrs" class="kartu p-6">
                            <h2 class="judul-bagian">Masa KRS</h2>
                            <p class="mt-2 text-sm text-[#31302e] dark:text-foreground">{{ masaKrs.teks }}</p>
                            <span
                                class="mt-2 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    masaKrs.status === 'sedang dibuka' ? 'bg-[#f0faf2] text-[#17702b]' : 'bg-[#f6f5f4] text-[#615d59] dark:bg-muted'
                                "
                                >{{ masaKrs.status }}</span
                            >
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
