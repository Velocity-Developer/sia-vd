<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFitur } from '@/composables/useFitur';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

type BatasSks = { ips_minimal: number | string; maks_sks: number | string };
type SkalaNilai = {
    huruf: string;
    bobot: number | string;
    angka_minimal: number | string | null;
    lulus: boolean;
    boleh_diulang: boolean;
    dipakai?: number;
};

type Presensi = {
    jumlah_pertemuan: number;
    min_kehadiran_ujian: number;
    toleransi_terlambat_menit: number;
    durasi_presensi_mandiri_menit: number;
    batas_pengajuan_izin_hari: number;
    syarat_ujian_aktif: boolean;
};

const props = defineProps<{
    maksSksTanpaIps: number;
    kunciKrsAktif: boolean;
    hurufMaksRemidi: string | null;
    susulan: { batas_pengajuan_susulan_hari: number; batas_bayar_susulan_hari: number };
    minSksPendadaran: number;
    minSksAmbilTa: number;
    maksCuti: number;
    pindahKelasAktif: boolean;
    presensi: Presensi;
    batasSks: BatasSks[];
    skalaNilai: SkalaNilai[];
}>();

const sksForm = useForm({
    maks_sks_tanpa_ips: props.maksSksTanpaIps,
    batas_sks: props.batasSks.map((row) => ({ ...row })),
});

const nilaiForm = useForm({
    skala_nilai: props.skalaNilai.map(({ huruf, bobot, angka_minimal, lulus, boleh_diulang }) => ({
        huruf,
        bobot,
        angka_minimal: angka_minimal ?? '',
        lulus,
        boleh_diulang,
    })),
});

const dipakai = (huruf: string) => props.skalaNilai.find((row) => row.huruf === huruf.toUpperCase())?.dipakai ?? 0;
const errorOf = (form: { errors: object }, key: string) => (form.errors as Record<string, string | undefined>)[key];

// Penguncian KRS dan batas bayar susulan hanya berlaku selama fitur keuangan aktif.
const fitur = useFitur();
const keuangan = fitur.aktif('keuangan');
const kunciForm = useForm({ kunci_krs_aktif: props.kunciKrsAktif });

const susulanForm = useForm({ ...props.susulan });
const simpanSusulan = () => susulanForm.put(route('admin.pengaturan-akademik.susulan'), { preserveScroll: true });

const tugasAkhirForm = useForm({ min_sks_ambil_ta: props.minSksAmbilTa, min_sks_pendadaran: props.minSksPendadaran });
const simpanTugasAkhir = () => tugasAkhirForm.put(route('admin.pengaturan-akademik.tugas-akhir'), { preserveScroll: true });
const cutiForm = useForm({ maks_cuti: props.maksCuti });
const simpanCuti = () => cutiForm.put(route('admin.pengaturan-akademik.cuti'), { preserveScroll: true });

const remidiForm = useForm({ huruf_maks_remidi: props.hurufMaksRemidi ?? '' });
const simpanRemidi = () =>
    remidiForm
        .transform((data) => ({ huruf_maks_remidi: data.huruf_maks_remidi || null }))
        .put(route('admin.pengaturan-akademik.remidi'), { preserveScroll: true });

const presensiForm = useForm({ ...props.presensi });
const simpanPresensi = () => presensiForm.put(route('admin.pengaturan-akademik.presensi'), { preserveScroll: true });

const pindahKelasForm = useForm({ is_active: props.pindahKelasAktif });
const simpanPindahKelas = () => pindahKelasForm.put(route('admin.pengaturan-akademik.pindah-kelas'), { preserveScroll: true });

// Bagian milik fitur per klien yang mati tidak ditampilkan.
const fiturPindahKelas = fitur.aktif('pindah_kelas');
const susulanAktif = fitur.aktif('ujian_susulan');
const presensiQr = fitur.aktif('presensi_qr');
const daftarBagian = [
    { id: 'krs', judul: 'KRS & SKS' },
    ...(fiturPindahKelas ? [{ id: 'pindah-kelas', judul: 'Pindah Kelas' }] : []),
    { id: 'nilai', judul: 'Nilai' },
    { id: 'remidi', judul: 'Remidi' },
    ...(susulanAktif ? [{ id: 'susulan', judul: 'Ujian Susulan' }] : []),
    { id: 'tugas-akhir', judul: 'Tugas Akhir' },
    { id: 'cuti', judul: 'Cuti' },
    { id: 'presensi', judul: 'Presensi' },
];

const simpanKunciKrs = () => kunciForm.put(route('admin.pengaturan-akademik.kunci-krs'), { preserveScroll: true });

const saveSks = () => sksForm.put(route('admin.pengaturan-akademik.batas-sks'), { preserveScroll: true });
const saveNilai = () =>
    nilaiForm
        .transform((data) => ({
            skala_nilai: data.skala_nilai.map((row) => ({ ...row, angka_minimal: row.angka_minimal === '' ? null : row.angka_minimal })),
        }))
        .put(route('admin.pengaturan-akademik.skala-nilai'), { preserveScroll: true });
</script>

<template>
    <Head title="Pengaturan Akademik" />
    <PengaturanSistemLayout>
        <!-- Lompat ke sub-bagian; halaman ini panjang. -->
        <nav class="flex flex-wrap gap-2" aria-label="Bagian pengaturan akademik">
            <Button v-for="bagian in daftarBagian" :key="bagian.id" as-child variant="outline" size="sm">
                <a :href="`#${bagian.id}`">{{ bagian.judul }}</a>
            </Button>
        </nav>

        <section id="krs" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">KRS &amp; SKS</h2>
            <form v-if="keuangan" class="kartu p-6" @submit.prevent="simpanKunciKrs">
                <h3 class="judul-bagian">Penguncian KRS oleh Pembayaran</h3>
                <p class="teks-bantu mt-1">
                    Saat menyala, mahasiswa yang tagihan semester berjalannya belum lunas tidak bisa membuka halaman KRS. Mahasiswa yang tagihannya
                    belum diterbitkan tidak terpengaruh, dan jadwal, KHS, transkrip, serta Info Biaya Kuliah tetap terbuka.
                </p>

                <Label for="kunci_krs_aktif" class="label-isian mt-4 flex w-fit items-center gap-2.5 font-normal">
                    <Checkbox id="kunci_krs_aktif" v-model="kunciForm.kunci_krs_aktif" />
                    <span>Kunci pengisian KRS bila tagihan semester berjalan belum lunas</span>
                </Label>

                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="kunciForm.processing"> Simpan Pengaturan Kunci </Button>
                </div>
            </form>

            <form class="kartu p-6" @submit.prevent="saveSks">
                <h3 class="judul-bagian">Batas SKS per Semester</h3>
                <p class="teks-bantu mt-1">
                    Ditentukan dari IPS semester terakhir mahasiswa yang sudah bernilai. Baris dengan IPS minimal tertinggi yang terpenuhi yang
                    dipakai.
                </p>

                <div class="tabel-wadah mt-4 shadow-none">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[460px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>IPS minimal</th>
                                    <th>Maks SKS</th>
                                    <th class="kolom-aksi"><span class="sr-only">Hapus</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in sksForm.batas_sks" :key="index">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <Input v-model="row.ips_minimal" type="number" min="0" max="4" step="0.01" aria-label="IPS minimal" />
                                        <InputError :message="errorOf(sksForm, `batas_sks.${index}.ips_minimal`)" />
                                    </td>
                                    <td>
                                        <Input v-model="row.maks_sks" type="number" min="1" max="40" aria-label="Maks SKS" />
                                        <InputError :message="errorOf(sksForm, `batas_sks.${index}.maks_sks`)" />
                                    </td>
                                    <td class="kolom-aksi">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            class="text-[#dd5b00]"
                                            aria-label="Hapus baris"
                                            :disabled="sksForm.batas_sks.length === 1"
                                            @click="sksForm.batas_sks.splice(index, 1)"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <InputError class="mt-2" :message="sksForm.errors.batas_sks" />
                <Button type="button" variant="outline" size="sm" class="mt-3" @click="sksForm.batas_sks.push({ ips_minimal: '', maks_sks: '' })">
                    <Plus /> Tambah baris
                </Button>

                <div class="mt-6 grid max-w-xs gap-2">
                    <Label for="maks_sks_tanpa_ips" class="label-isian">Maks SKS bila belum ada IPS</Label>
                    <Input id="maks_sks_tanpa_ips" v-model="sksForm.maks_sks_tanpa_ips" type="number" min="1" max="40" />
                    <p class="teks-bantu">Untuk mahasiswa baru atau yang nilai semester lalunya belum keluar.</p>
                    <InputError :message="sksForm.errors.maks_sks_tanpa_ips" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="sksForm.processing">Simpan Batas SKS</Button>
                </div>
            </form>
        </section>

        <section v-if="fiturPindahKelas" id="pindah-kelas" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Pindah Kelas</h2>
            <form class="kartu p-6" @submit.prevent="simpanPindahKelas">
                <h3 class="judul-bagian">Form Pindah Kelas</h3>
                <p class="teks-bantu mt-1">
                    Saat dibuka, mahasiswa bisa mengajukan pindah kelas dari menu Pindah Kelas. Pengajuan tetap diproses admin di halaman Pindah
                    Kelas.
                </p>

                <Label for="pindah_kelas_aktif" class="label-isian mt-4 flex w-fit items-center gap-2.5 font-normal">
                    <Checkbox id="pindah_kelas_aktif" v-model="pindahKelasForm.is_active" />
                    <span>Buka form pengajuan pindah kelas untuk mahasiswa</span>
                </Label>

                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="pindahKelasForm.processing"> Simpan Pengaturan Pindah Kelas </Button>
                </div>
            </form>
        </section>

        <section id="nilai" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Nilai</h2>
            <form class="kartu p-6" @submit.prevent="saveNilai">
                <h3 class="judul-bagian">Skala Nilai</h3>
                <p class="teks-bantu mt-1">
                    Dipakai untuk pilihan nilai di kelas, IP/IPK, KHS, dan transkrip. Mengubah bobot akan mengubah IP/IPK semua mahasiswa yang
                    memiliki nilai tersebut. Angka minimal (0–100) dipakai mengubah nilai angka pendadaran menjadi huruf. Wajib ada minimal satu huruf
                    lulus dan satu huruf tidak lulus, dan huruf tidak lulus harus boleh diulang.
                </p>

                <div class="tabel-wadah mt-4 shadow-none">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[620px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Huruf</th>
                                    <th>Bobot</th>
                                    <th>Angka min.</th>
                                    <th class="text-center">Lulus</th>
                                    <th class="text-center">Boleh diulang</th>
                                    <th class="kolom-aksi"><span class="sr-only">Hapus</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in nilaiForm.skala_nilai" :key="index">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <Input v-model="row.huruf" maxlength="2" class="w-20 uppercase" aria-label="Huruf" />
                                        <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.huruf`)" />
                                    </td>
                                    <td>
                                        <Input v-model="row.bobot" type="number" min="0" max="4" step="0.01" class="w-28" aria-label="Bobot" />
                                        <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.bobot`)" />
                                    </td>
                                    <td>
                                        <Input
                                            v-model="row.angka_minimal"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="w-28"
                                            aria-label="Angka minimal"
                                        />
                                        <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.angka_minimal`)" />
                                    </td>
                                    <td class="text-center">
                                        <input v-model="row.lulus" type="checkbox" class="size-4 accent-[#0075de]" aria-label="Lulus" />
                                    </td>
                                    <td class="text-center">
                                        <input
                                            v-model="row.boleh_diulang"
                                            type="checkbox"
                                            class="size-4 accent-[#0075de]"
                                            aria-label="Boleh diulang"
                                        />
                                    </td>
                                    <td class="kolom-aksi">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            class="text-[#dd5b00]"
                                            :aria-label="dipakai(row.huruf) ? `Nilai ${row.huruf} dipakai ${dipakai(row.huruf)} KRS` : 'Hapus baris'"
                                            :title="dipakai(row.huruf) ? `Dipakai ${dipakai(row.huruf)} KRS, tidak bisa dihapus` : 'Hapus baris'"
                                            :disabled="dipakai(row.huruf) > 0 || nilaiForm.skala_nilai.length === 1"
                                            @click="nilaiForm.skala_nilai.splice(index, 1)"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <InputError class="mt-2" :message="nilaiForm.errors.skala_nilai" />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    @click="nilaiForm.skala_nilai.push({ huruf: '', bobot: '', angka_minimal: '', lulus: true, boleh_diulang: false })"
                >
                    <Plus /> Tambah nilai
                </Button>

                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="nilaiForm.processing">Simpan Skala Nilai</Button>
                </div>
            </form>
        </section>

        <section id="remidi" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Remidi</h2>
            <form class="kartu p-6" @submit.prevent="simpanRemidi">
                <h3 class="judul-bagian">Huruf Akhir Setelah Remidi</h3>
                <p class="teks-bantu mt-1">
                    Setelah ujian remidi selesai, dosen bisa mengubah huruf akhir peserta remidi yang lunas sampai batas input nilai remidi. Batasi
                    huruf tertinggi yang boleh diberikan, atau biarkan bebas.
                </p>
                <div class="mt-4 grid max-w-xs gap-2">
                    <Label for="huruf_maks_remidi" class="label-isian">Huruf maksimal</Label>
                    <select id="huruf_maks_remidi" v-model="remidiForm.huruf_maks_remidi" class="isian isian-pilih">
                        <option value="">Bebas</option>
                        <option v-for="row in props.skalaNilai" :key="row.huruf" :value="row.huruf">{{ row.huruf }}</option>
                    </select>
                    <InputError :message="remidiForm.errors.huruf_maks_remidi" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="remidiForm.processing"> Simpan Pengaturan Remidi </Button>
                </div>
            </form>
        </section>

        <section v-if="susulanAktif" id="susulan" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Ujian Susulan</h2>
            <form class="kartu p-6" @submit.prevent="simpanSusulan">
                <h3 class="judul-bagian">Batas Waktu Ujian Susulan</h3>
                <p class="teks-bantu mt-1">
                    Mahasiswa bisa mengajukan susulan sejak jadwal UTS/UAS terbit sampai beberapa hari sesudah ujian.
                    <template v-if="keuangan">
                        Tagihan susulan harus dibayar dalam beberapa hari sejak diterbitkan; lewat batas itu hak susulan gugur.
                    </template>
                </p>
                <div class="mt-4 grid content-start gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="batas_pengajuan_susulan_hari" class="label-isian">Batas pengajuan (hari sesudah ujian)</Label>
                        <Input id="batas_pengajuan_susulan_hari" v-model="susulanForm.batas_pengajuan_susulan_hari" type="number" min="0" max="30" />
                        <InputError :message="susulanForm.errors.batas_pengajuan_susulan_hari" />
                    </div>
                    <div v-if="keuangan" class="grid content-start gap-2">
                        <Label for="batas_bayar_susulan_hari" class="label-isian">Batas bayar (hari sejak tagihan terbit)</Label>
                        <Input id="batas_bayar_susulan_hari" v-model="susulanForm.batas_bayar_susulan_hari" type="number" min="1" max="30" />
                        <InputError :message="susulanForm.errors.batas_bayar_susulan_hari" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="susulanForm.processing"> Simpan Pengaturan Susulan </Button>
                </div>
            </form>
        </section>

        <section id="tugas-akhir" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Tugas Akhir</h2>
            <form class="kartu p-6" @submit.prevent="simpanTugasAkhir">
                <h3 class="judul-bagian">Syarat SKS</h3>
                <p class="teks-bantu mt-1">
                    Dihitung dari transkrip tanpa mata kuliah TA/Skripsi. Mata kuliah TA/Skripsi ditandai di Master Akademik → Mata Kuliah.
                </p>
                <div class="mt-4 grid content-start gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="min_sks_ambil_ta" class="label-isian">SKS lulus minimal untuk mengambil TA/Skripsi di KRS</Label>
                        <Input id="min_sks_ambil_ta" v-model="tugasAkhirForm.min_sks_ambil_ta" type="number" min="0" max="300" />
                        <InputError :message="tugasAkhirForm.errors.min_sks_ambil_ta" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="min_sks_pendadaran" class="label-isian">SKS bernilai minimal untuk mendaftar pendadaran</Label>
                        <Input id="min_sks_pendadaran" v-model="tugasAkhirForm.min_sks_pendadaran" type="number" min="0" max="300" />
                        <InputError :message="tugasAkhirForm.errors.min_sks_pendadaran" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="tugasAkhirForm.processing"> Simpan Pengaturan Tugas Akhir </Button>
                </div>
            </form>
        </section>

        <section id="cuti" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Cuti</h2>
            <form class="kartu p-6" @submit.prevent="simpanCuti">
                <h3 class="judul-bagian">Batas Cuti</h3>
                <p class="teks-bantu mt-1">
                    Jumlah semester cuti yang boleh disetujui selama studi. Periode pengajuan cuti diatur per semester di Master Akademik → Tahun
                    Akademik.
                </p>
                <div class="mt-4 grid content-start gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="maks_cuti" class="label-isian">Maksimal semester cuti</Label>
                        <Input id="maks_cuti" v-model="cutiForm.maks_cuti" type="number" min="0" max="14" />
                        <InputError :message="cutiForm.errors.maks_cuti" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="cutiForm.processing"> Simpan Pengaturan Cuti </Button>
                </div>
            </form>
        </section>

        <section id="presensi" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="teks-bantu font-semibold uppercase tracking-[0.06em]">Presensi</h2>
            <form class="kartu p-6" @submit.prevent="simpanPresensi">
                <h3 class="judul-bagian">Presensi</h3>
                <p class="teks-bantu mt-1">
                    Kehadiran dihitung dari pertemuan kuliah yang sudah selesai (UTS dan UAS tidak dihitung). Izin dan sakit dihitung tidak hadir;
                    terlambat dihitung hadir.
                </p>

                <div class="mt-4 grid content-start items-start gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid content-start gap-2">
                        <Label for="jumlah_pertemuan" class="label-isian">Jumlah pertemuan per kelas</Label>
                        <Input id="jumlah_pertemuan" v-model="presensiForm.jumlah_pertemuan" type="number" min="1" max="32" />
                        <p class="teks-bantu">Nilai awal untuk kelas baru, bisa diubah per kelas. Kelas lama tidak berubah.</p>
                        <InputError :message="presensiForm.errors.jumlah_pertemuan" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="min_kehadiran_ujian" class="label-isian">Minimal kehadiran ujian (%)</Label>
                        <Input id="min_kehadiran_ujian" v-model="presensiForm.min_kehadiran_ujian" type="number" min="0" max="100" />
                        <p class="teks-bantu">Mahasiswa di bawah batas ini ditandai di rekap presensi.</p>
                        <InputError :message="presensiForm.errors.min_kehadiran_ujian" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="toleransi_terlambat_menit" class="label-isian">Toleransi terlambat (menit)</Label>
                        <Input id="toleransi_terlambat_menit" v-model="presensiForm.toleransi_terlambat_menit" type="number" min="0" max="180" />
                        <p class="teks-bantu">Lewat dari jam mulai + toleransi, presensi mandiri tercatat Terlambat.</p>
                        <InputError :message="presensiForm.errors.toleransi_terlambat_menit" />
                    </div>
                    <div v-if="presensiQr" class="grid content-start gap-2">
                        <Label for="durasi_presensi_mandiri_menit" class="label-isian">Durasi presensi mandiri (menit)</Label>
                        <Input
                            id="durasi_presensi_mandiri_menit"
                            v-model="presensiForm.durasi_presensi_mandiri_menit"
                            type="number"
                            min="1"
                            max="180"
                        />
                        <p class="teks-bantu">Lama QR/PIN bisa dipakai setelah dosen membukanya; bisa diperpanjang.</p>
                        <InputError :message="presensiForm.errors.durasi_presensi_mandiri_menit" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="batas_pengajuan_izin_hari" class="label-isian">Batas pengajuan izin (hari)</Label>
                        <Input id="batas_pengajuan_izin_hari" v-model="presensiForm.batas_pengajuan_izin_hari" type="number" min="0" max="14" />
                        <p class="teks-bantu">Izin/sakit diajukan paling lambat sekian hari sesudah tanggal pertemuan (0 = hari itu juga).</p>
                        <InputError :message="presensiForm.errors.batas_pengajuan_izin_hari" />
                    </div>
                </div>

                <Label for="syarat_ujian_aktif" class="label-isian mt-4 flex w-fit items-start gap-2.5 font-normal">
                    <Checkbox id="syarat_ujian_aktif" v-model="presensiForm.syarat_ujian_aktif" class="mt-0.5" />
                    <span>
                        Terapkan syarat kehadiran ujian: mahasiswa di bawah batas minimal ditandai <strong>tidak memenuhi syarat</strong> UTS/UAS di
                        daftar peserta ujian dan halaman presensinya, tidak bisa mengerjakan ujian online, dan
                        <strong>tidak bisa mencetak kartu ujian</strong> jenis itu, kecuali mendapat dispensasi.
                    </span>
                </Label>

                <div class="mt-6 flex justify-end gap-2">
                    <Button type="submit" :disabled="presensiForm.processing"> Simpan Pengaturan Presensi </Button>
                </div>
            </form>
        </section>
    </PengaturanSistemLayout>
</template>
