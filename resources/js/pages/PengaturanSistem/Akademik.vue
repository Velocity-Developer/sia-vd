<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

type BatasSks = { ips_minimal: number | string; maks_sks: number | string };
type SkalaNilai = { huruf: string; bobot: number | string; lulus: boolean; boleh_diulang: boolean; dipakai?: number };

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
    skala_nilai: props.skalaNilai.map(({ huruf, bobot, lulus, boleh_diulang }) => ({ huruf, bobot, lulus, boleh_diulang })),
});

const dipakai = (huruf: string) => props.skalaNilai.find((row) => row.huruf === huruf.toUpperCase())?.dipakai ?? 0;
const errorOf = (form: { errors: object }, key: string) => (form.errors as Record<string, string | undefined>)[key];

const kunciForm = useForm({ kunci_krs_aktif: props.kunciKrsAktif });

const presensiForm = useForm({ ...props.presensi });
const simpanPresensi = () => presensiForm.put(route('admin.pengaturan-akademik.presensi'), { preserveScroll: true });

const pindahKelasForm = useForm({ is_active: props.pindahKelasAktif });
const simpanPindahKelas = () => pindahKelasForm.put(route('admin.pengaturan-akademik.pindah-kelas'), { preserveScroll: true });

const daftarBagian = [
    { id: 'krs', judul: 'KRS & SKS' },
    { id: 'pindah-kelas', judul: 'Pindah Kelas' },
    { id: 'nilai', judul: 'Nilai' },
    { id: 'presensi', judul: 'Presensi' },
];

const simpanKunciKrs = () => kunciForm.put(route('admin.pengaturan-akademik.kunci-krs'), { preserveScroll: true });

const saveSks = () => sksForm.put(route('admin.pengaturan-akademik.batas-sks'), { preserveScroll: true });
const saveNilai = () => nilaiForm.put(route('admin.pengaturan-akademik.skala-nilai'), { preserveScroll: true });

const inp = 'h-9 rounded-lg border-[#dddddd]';
</script>

<template>
    <Head title="Pengaturan Akademik" />
    <PengaturanSistemLayout>
        <!-- Lompat ke sub-bagian; halaman ini panjang. -->
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Bagian pengaturan akademik">
            <a
                v-for="bagian in daftarBagian"
                :key="bagian.id"
                :href="`#${bagian.id}`"
                class="rounded-full border border-[#e6e6e6] bg-white px-3 py-1.5 font-medium text-[#31302e] hover:border-[#0075de] hover:text-[#0075de]"
                >{{ bagian.judul }}</a
            >
        </nav>

        <section id="krs" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">KRS &amp; SKS</h2>
            <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="simpanKunciKrs">
                <h2 class="text-lg font-semibold text-black">Penguncian KRS oleh Pembayaran</h2>
                <p class="mt-1 text-sm text-[#615d59]">
                    Saat menyala, mahasiswa yang tagihan semester berjalannya belum lunas tidak bisa membuka halaman KRS. Mahasiswa yang tagihannya
                    belum diterbitkan tidak terpengaruh, dan jadwal, KHS, transkrip, serta Info Biaya Kuliah tetap terbuka.
                </p>

                <Label for="kunci_krs_aktif" class="mt-4 flex w-fit items-center gap-2.5 text-sm text-[#31302e]">
                    <Checkbox id="kunci_krs_aktif" v-model="kunciForm.kunci_krs_aktif" />
                    <span>Kunci pengisian KRS bila tagihan semester berjalan belum lunas</span>
                </Label>

                <div class="mt-5 flex justify-end">
                    <Button
                        type="submit"
                        :disabled="kunciForm.processing"
                        class="h-10 rounded-lg bg-[#0075de] px-5 text-sm font-medium text-white hover:bg-[#005bab]"
                    >
                        Simpan Pengaturan Kunci
                    </Button>
                </div>
            </form>

            <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="saveSks">
                <h2 class="text-lg font-semibold text-black">Batas SKS per Semester</h2>
                <p class="mt-1 text-sm text-[#615d59]">
                    Ditentukan dari IPS semester terakhir mahasiswa yang sudah bernilai. Baris dengan IPS minimal tertinggi yang terpenuhi yang
                    dipakai.
                </p>

                <div class="relative mt-5 overflow-x-auto rounded-xl border border-[#e6e6e6]">
                    <table class="w-full min-w-[420px] text-left">
                        <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">IPS minimal</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Maks SKS</th>
                                <th class="w-12 px-4 py-3"><span class="sr-only">Hapus</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e6e6e6]">
                            <tr v-for="(row, index) in sksForm.batas_sks" :key="index">
                                <td class="px-4 py-2">
                                    <Input
                                        v-model="row.ips_minimal"
                                        type="number"
                                        min="0"
                                        max="4"
                                        step="0.01"
                                        :class="inp"
                                        aria-label="IPS minimal"
                                    />
                                    <InputError :message="errorOf(sksForm, `batas_sks.${index}.ips_minimal`)" />
                                </td>
                                <td class="px-4 py-2">
                                    <Input v-model="row.maks_sks" type="number" min="1" max="40" :class="inp" aria-label="Maks SKS" />
                                    <InputError :message="errorOf(sksForm, `batas_sks.${index}.maks_sks`)" />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Hapus baris"
                                        :disabled="sksForm.batas_sks.length === 1"
                                        @click="sksForm.batas_sks.splice(index, 1)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError class="mt-2" :message="sksForm.errors.batas_sks" />
                <Button type="button" variant="outline" size="sm" class="mt-3" @click="sksForm.batas_sks.push({ ips_minimal: '', maks_sks: '' })">
                    <Plus class="size-4" /> Tambah baris
                </Button>

                <div class="mt-6 grid max-w-xs gap-2">
                    <Label for="maks_sks_tanpa_ips">Maks SKS bila belum ada IPS</Label>
                    <Input id="maks_sks_tanpa_ips" v-model="sksForm.maks_sks_tanpa_ips" type="number" min="1" max="40" :class="inp" />
                    <p class="text-xs text-[#a39e98]">Untuk mahasiswa baru atau yang nilai semester lalunya belum keluar.</p>
                    <InputError :message="sksForm.errors.maks_sks_tanpa_ips" />
                </div>

                <div class="mt-6 flex justify-end">
                    <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="sksForm.processing">Simpan Batas SKS</Button>
                </div>
            </form>
        </section>

        <section id="pindah-kelas" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pindah Kelas</h2>
            <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="simpanPindahKelas">
                <h2 class="text-lg font-semibold text-black">Form Pindah Kelas</h2>
                <p class="mt-1 text-sm text-[#615d59]">
                    Saat dibuka, mahasiswa bisa mengajukan pindah kelas dari menu Pindah Kelas. Pengajuan tetap diproses admin di halaman Pindah
                    Kelas.
                </p>

                <Label for="pindah_kelas_aktif" class="mt-4 flex w-fit items-center gap-2.5 text-sm text-[#31302e]">
                    <Checkbox id="pindah_kelas_aktif" v-model="pindahKelasForm.is_active" />
                    <span>Buka form pengajuan pindah kelas untuk mahasiswa</span>
                </Label>

                <div class="mt-5 flex justify-end">
                    <Button
                        type="submit"
                        :disabled="pindahKelasForm.processing"
                        class="h-10 rounded-lg bg-[#0075de] px-5 text-sm font-medium text-white hover:bg-[#005bab]"
                    >
                        Simpan Pengaturan Pindah Kelas
                    </Button>
                </div>
            </form>
        </section>

        <section id="nilai" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nilai</h2>
            <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="saveNilai">
                <h2 class="text-lg font-semibold text-black">Skala Nilai</h2>
                <p class="mt-1 text-sm text-[#615d59]">
                    Dipakai untuk pilihan nilai di kelas, IP/IPK, KHS, dan transkrip. Mengubah bobot akan mengubah IP/IPK semua mahasiswa yang
                    memiliki nilai tersebut.
                </p>

                <div class="relative mt-5 overflow-x-auto rounded-xl border border-[#e6e6e6]">
                    <table class="w-full min-w-[560px] text-left">
                        <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Huruf</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Bobot</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-[#a39e98]">Lulus</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-[#a39e98]">Boleh diulang</th>
                                <th class="w-12 px-4 py-3"><span class="sr-only">Hapus</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e6e6e6]">
                            <tr v-for="(row, index) in nilaiForm.skala_nilai" :key="index">
                                <td class="px-4 py-2">
                                    <Input v-model="row.huruf" maxlength="2" :class="[inp, 'w-20 uppercase']" aria-label="Huruf" />
                                    <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.huruf`)" />
                                </td>
                                <td class="px-4 py-2">
                                    <Input v-model="row.bobot" type="number" min="0" max="4" step="0.01" :class="[inp, 'w-28']" aria-label="Bobot" />
                                    <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.bobot`)" />
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <input v-model="row.lulus" type="checkbox" class="size-4 accent-[#0075de]" aria-label="Lulus" />
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <input v-model="row.boleh_diulang" type="checkbox" class="size-4 accent-[#0075de]" aria-label="Boleh diulang" />
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="dipakai(row.huruf) ? `Nilai ${row.huruf} dipakai ${dipakai(row.huruf)} KRS` : 'Hapus baris'"
                                        :title="dipakai(row.huruf) ? `Dipakai ${dipakai(row.huruf)} KRS, tidak bisa dihapus` : 'Hapus baris'"
                                        :disabled="dipakai(row.huruf) > 0 || nilaiForm.skala_nilai.length === 1"
                                        @click="nilaiForm.skala_nilai.splice(index, 1)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError class="mt-2" :message="nilaiForm.errors.skala_nilai" />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    @click="nilaiForm.skala_nilai.push({ huruf: '', bobot: '', lulus: true, boleh_diulang: false })"
                >
                    <Plus class="size-4" /> Tambah nilai
                </Button>

                <div class="mt-6 flex justify-end">
                    <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="nilaiForm.processing"
                        >Simpan Skala Nilai</Button
                    >
                </div>
            </form>
        </section>

        <section id="presensi" class="flex scroll-mt-4 flex-col gap-4">
            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Presensi</h2>
            <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="simpanPresensi">
                <h2 class="text-lg font-semibold text-black">Presensi</h2>
                <p class="mt-1 text-sm text-[#615d59]">
                    Kehadiran dihitung dari pertemuan kuliah yang sudah selesai (UTS, UAS, dan pertemuan batal tidak dihitung). Izin dan sakit
                    dihitung tidak hadir; terlambat dihitung hadir.
                </p>

                <div class="mt-5 grid content-start items-start gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid content-start gap-2">
                        <Label for="jumlah_pertemuan">Jumlah pertemuan per kelas</Label>
                        <Input id="jumlah_pertemuan" v-model="presensiForm.jumlah_pertemuan" type="number" min="1" max="32" :class="inp" />
                        <p class="text-xs text-[#a39e98]">Nilai awal untuk kelas baru, bisa diubah per kelas. Kelas lama tidak berubah.</p>
                        <InputError :message="presensiForm.errors.jumlah_pertemuan" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="min_kehadiran_ujian">Minimal kehadiran ujian (%)</Label>
                        <Input id="min_kehadiran_ujian" v-model="presensiForm.min_kehadiran_ujian" type="number" min="0" max="100" :class="inp" />
                        <p class="text-xs text-[#a39e98]">Mahasiswa di bawah batas ini ditandai di rekap presensi.</p>
                        <InputError :message="presensiForm.errors.min_kehadiran_ujian" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="toleransi_terlambat_menit">Toleransi terlambat (menit)</Label>
                        <Input
                            id="toleransi_terlambat_menit"
                            v-model="presensiForm.toleransi_terlambat_menit"
                            type="number"
                            min="0"
                            max="180"
                            :class="inp"
                        />
                        <p class="text-xs text-[#a39e98]">Lewat dari jam mulai + toleransi, presensi mandiri tercatat Terlambat.</p>
                        <InputError :message="presensiForm.errors.toleransi_terlambat_menit" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="durasi_presensi_mandiri_menit">Durasi presensi mandiri (menit)</Label>
                        <Input
                            id="durasi_presensi_mandiri_menit"
                            v-model="presensiForm.durasi_presensi_mandiri_menit"
                            type="number"
                            min="1"
                            max="180"
                            :class="inp"
                        />
                        <p class="text-xs text-[#a39e98]">Lama QR/PIN bisa dipakai setelah dosen membukanya; bisa diperpanjang.</p>
                        <InputError :message="presensiForm.errors.durasi_presensi_mandiri_menit" />
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="batas_pengajuan_izin_hari">Batas pengajuan izin (hari)</Label>
                        <Input
                            id="batas_pengajuan_izin_hari"
                            v-model="presensiForm.batas_pengajuan_izin_hari"
                            type="number"
                            min="0"
                            max="14"
                            :class="inp"
                        />
                        <p class="text-xs text-[#a39e98]">
                            Izin/sakit diajukan paling lambat sekian hari sesudah tanggal pertemuan (0 = hari itu juga).
                        </p>
                        <InputError :message="presensiForm.errors.batas_pengajuan_izin_hari" />
                    </div>
                </div>

                <Label for="syarat_ujian_aktif" class="mt-5 flex w-fit items-start gap-2.5 text-sm text-[#31302e]">
                    <Checkbox id="syarat_ujian_aktif" v-model="presensiForm.syarat_ujian_aktif" class="mt-0.5" />
                    <span>
                        Terapkan syarat kehadiran ujian: mahasiswa di bawah batas minimal ditandai <strong>tidak memenuhi syarat</strong> UTS/UAS di
                        daftar peserta ujian dan halaman presensinya, kecuali mendapat dispensasi.
                    </span>
                </Label>

                <div class="mt-5 flex justify-end">
                    <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="presensiForm.processing">
                        Simpan Pengaturan Presensi
                    </Button>
                </div>
            </form>
        </section>
    </PengaturanSistemLayout>
</template>
