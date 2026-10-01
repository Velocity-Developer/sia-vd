<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PilihKecamatan from '@/components/PilihKecamatan.vue';
import RecaptchaWidget from '@/components/RecaptchaWidget.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PmbLayout from '@/layouts/PmbLayout.vue';
import { rupiah } from '@/lib/tagihanRemidi';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Opsi = { value: string | number; label: string };
type Periode = { kode: string; tahun_angkatan: number; tanggal_tutup: string; biaya_pendaftaran: number; penuh: boolean };

const props = defineProps<{
    // Periode yang sedang dibuka; pendaftar tidak memilih periode.
    periode: Periode | null;
    agama: { id: number; nama: string }[];
    programStudi: { id: number; nama_prodi: string; jenjang: string | null }[];
    opsi: Record<string, Opsi[]>;
    recaptchaSiteKey?: string | null;
}>();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T00:00:00`));

const form = useForm({
    nama: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    nama_ibu: '',
    agama_id: '' as number | '',
    jenis_kelamin: '',
    status_sipil: '',
    nik: '',
    kewarganegaraan: 'ID',
    npwp: '',
    jalan: '',
    dusun: '',
    rt: '',
    rw: '',
    kelurahan: '',
    wilayah_kecamatan_id: null as number | null,
    kode_pos: '',
    alat_transportasi: '',
    jenis_tinggal: '',
    jenis_masuk: '',
    email: '',
    telepon_wali: '',
    hp: '',
    penerima_kps: false,
    nomor_kps: '',
    jenis_pembiayaan: '',
    jumlah_pembiayaan: '',
    kelas: '',
    program_studi_id: '' as number | '',
    status_masuk: 'B',
    asal_sekolah: '',
    nisn: '',
    nilai_un: '',
    asal_perguruan_tinggi: '',
    jenjang_asal: '',
    prodi_asal: '',
    nim_asal: '',
    sks_diakui: '',
    agen: '',
    info: '',
    'g-recaptcha-response': '',
});

const labelKecamatan = ref<string | null>(null);
const captcha = ref<InstanceType<typeof RecaptchaWidget> | null>(null);
const errors = computed(() => form.errors as Record<string, string | undefined>);

const submit = () =>
    form.post(route('pmb.daftar.store'), {
        preserveScroll: (page) => Object.keys(page.props.errors ?? {}).length === 0,
        onError: () => {
            captcha.value?.reset();
            // Bawa pendaftar ke isian pertama yang salah.
            requestAnimationFrame(() => document.querySelector('.pesan-galat')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },
    });
</script>

<template>
    <Head title="Pendaftaran Mahasiswa Baru" />
    <PmbLayout>
        <div v-if="!props.periode" class="kartu p-6 text-center sm:p-10">
            <h1 class="judul-halaman">Pendaftaran Belum Dibuka</h1>
            <p class="deskripsi-halaman mx-auto max-w-[480px]">
                Saat ini tidak ada periode penerimaan mahasiswa baru yang dibuka. Silakan kembali lagi saat masa pendaftaran berlangsung.
            </p>
        </div>

        <div v-else-if="props.periode.penuh" class="kartu p-6 text-center sm:p-10">
            <h1 class="judul-halaman">Kuota Pendaftaran Penuh</h1>
            <p class="deskripsi-halaman mx-auto max-w-[480px]">
                Kuota pendaftar periode {{ props.periode.kode }} sudah terpenuhi. Silakan hubungi panitia PMB untuk informasi lebih lanjut.
            </p>
        </div>

        <form v-else class="flex flex-col gap-6" novalidate @submit.prevent="submit">
            <div class="kartu p-6 sm:p-8">
                <h1 class="judul-halaman">Formulir Pendaftaran Mahasiswa Baru</h1>
                <p class="deskripsi-halaman">Isian bertanda * wajib diisi. Pastikan data sesuai KTP/KK dan ijazah.</p>

                <dl class="mt-5 grid gap-x-6 gap-y-2 rounded-xl bg-[#f6f5f4] p-4 text-sm dark:bg-muted sm:grid-cols-[180px_1fr]">
                    <dt class="text-[#615d59]">Periode pendaftaran</dt>
                    <dd class="font-medium text-black dark:text-foreground">
                        {{ props.periode.kode }} · angkatan {{ props.periode.tahun_angkatan }}
                    </dd>
                    <dt class="text-[#615d59]">Ditutup</dt>
                    <dd class="font-medium text-black dark:text-foreground">{{ formatDate(props.periode.tanggal_tutup) }}</dd>
                    <dt class="text-[#615d59]">Biaya pendaftaran</dt>
                    <dd class="font-medium text-black dark:text-foreground">{{ rupiah(props.periode.biaya_pendaftaran) }}</dd>
                </dl>
                <InputError class="pesan-galat mt-2" :message="errors.periode" />
            </div>

            <section class="kartu p-6 sm:p-8">
                <h2 class="judul-bagian">Data Diri</h2>
                <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="nama" class="label-isian">Nama Lengkap *</Label>
                        <Input id="nama" v-model="form.nama" placeholder="Sesuai ijazah" autocomplete="name" required />
                        <InputError class="pesan-galat" :message="form.errors.nama" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tempat_lahir" class="label-isian">Tempat Lahir *</Label>
                        <Input id="tempat_lahir" v-model="form.tempat_lahir" required />
                        <InputError class="pesan-galat" :message="form.errors.tempat_lahir" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tanggal_lahir" class="label-isian">Tanggal Lahir *</Label>
                        <Input id="tanggal_lahir" v-model="form.tanggal_lahir" type="date" required />
                        <InputError class="pesan-galat" :message="form.errors.tanggal_lahir" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="nama_ibu" class="label-isian">Nama Lengkap Ibu *</Label>
                        <Input id="nama_ibu" v-model="form.nama_ibu" required />
                        <InputError class="pesan-galat" :message="form.errors.nama_ibu" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="agama_id" class="label-isian">Agama *</Label>
                        <select id="agama_id" v-model="form.agama_id" class="isian isian-pilih" required>
                            <option value="" disabled>Pilih agama</option>
                            <option v-for="a in props.agama" :key="a.id" :value="a.id">{{ a.nama }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.agama_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jenis_kelamin" class="label-isian">Jenis Kelamin *</Label>
                        <select id="jenis_kelamin" v-model="form.jenis_kelamin" class="isian isian-pilih" required>
                            <option value="" disabled>Pilih jenis kelamin</option>
                            <option v-for="o in props.opsi.jenis_kelamin" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.jenis_kelamin" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status_sipil" class="label-isian">Status Perkawinan</Label>
                        <select id="status_sipil" v-model="form.status_sipil" class="isian isian-pilih">
                            <option value="">Pilih status</option>
                            <option v-for="o in props.opsi.status_sipil" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.status_sipil" />
                    </div>
                </div>
            </section>

            <section class="kartu p-6 sm:p-8">
                <h2 class="judul-bagian">Informasi Detail Calon Mahasiswa</h2>
                <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="nik" class="label-isian">NIK *</Label>
                        <Input id="nik" v-model="form.nik" inputmode="numeric" maxlength="16" placeholder="16 digit sesuai KTP/KK" required />
                        <InputError class="pesan-galat" :message="form.errors.nik" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="kewarganegaraan" class="label-isian">Kewarganegaraan *</Label>
                        <select id="kewarganegaraan" v-model="form.kewarganegaraan" class="isian isian-pilih" required>
                            <option v-for="o in props.opsi.kewarganegaraan" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.kewarganegaraan" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="npwp" class="label-isian">NPWP</Label>
                        <Input id="npwp" v-model="form.npwp" inputmode="numeric" maxlength="16" placeholder="Boleh dikosongkan" />
                        <InputError class="pesan-galat" :message="form.errors.npwp" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jalan" class="label-isian">Jalan *</Label>
                        <Input id="jalan" v-model="form.jalan" required />
                        <InputError class="pesan-galat" :message="form.errors.jalan" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dusun" class="label-isian">Dusun *</Label>
                        <Input id="dusun" v-model="form.dusun" required />
                        <InputError class="pesan-galat" :message="form.errors.dusun" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="rt" class="label-isian">RT *</Label>
                            <Input id="rt" v-model="form.rt" inputmode="numeric" maxlength="3" required />
                            <InputError class="pesan-galat" :message="form.errors.rt" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="rw" class="label-isian">RW *</Label>
                            <Input id="rw" v-model="form.rw" inputmode="numeric" maxlength="3" required />
                            <InputError class="pesan-galat" :message="form.errors.rw" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="kelurahan" class="label-isian">Kelurahan *</Label>
                        <Input id="kelurahan" v-model="form.kelurahan" required />
                        <InputError class="pesan-galat" :message="form.errors.kelurahan" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="wilayah_kecamatan_id" class="label-isian">Kecamatan *</Label>
                        <PilihKecamatan id="wilayah_kecamatan_id" v-model="form.wilayah_kecamatan_id" v-model:label="labelKecamatan" />
                        <InputError class="pesan-galat" :message="form.errors.wilayah_kecamatan_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="kode_pos" class="label-isian">Kode Pos *</Label>
                        <Input id="kode_pos" v-model="form.kode_pos" inputmode="numeric" maxlength="5" required />
                        <InputError class="pesan-galat" :message="form.errors.kode_pos" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="alat_transportasi" class="label-isian">Alat Transportasi</Label>
                        <select id="alat_transportasi" v-model="form.alat_transportasi" class="isian isian-pilih">
                            <option value="">Pilih kendaraan</option>
                            <option v-for="o in props.opsi.alat_transportasi" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.alat_transportasi" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jenis_tinggal" class="label-isian">Jenis Tinggal</Label>
                        <select id="jenis_tinggal" v-model="form.jenis_tinggal" class="isian isian-pilih">
                            <option value="">Pilih tempat tinggal</option>
                            <option v-for="o in props.opsi.jenis_tinggal" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.jenis_tinggal" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jenis_masuk" class="label-isian">Jenis Masuk</Label>
                        <select id="jenis_masuk" v-model="form.jenis_masuk" class="isian isian-pilih">
                            <option value="">Pilih jenis masuk</option>
                            <option v-for="o in props.opsi.jenis_masuk" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.jenis_masuk" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email" class="label-isian">Email *</Label>
                        <Input id="email" v-model="form.email" type="email" autocomplete="email" required />
                        <InputError class="pesan-galat" :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="hp" class="label-isian">No. HP *</Label>
                        <Input id="hp" v-model="form.hp" type="tel" inputmode="numeric" autocomplete="tel" placeholder="08…" required />
                        <InputError class="pesan-galat" :message="form.errors.hp" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="telepon_wali" class="label-isian">No. HP Wali/Ortu</Label>
                        <Input id="telepon_wali" v-model="form.telepon_wali" type="tel" inputmode="numeric" placeholder="08…" />
                        <InputError class="pesan-galat" :message="form.errors.telepon_wali" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="penerima_kps" class="label-isian">Penerima KPS (Kartu Perlindungan Sosial)?</Label>
                        <select id="penerima_kps" v-model="form.penerima_kps" class="isian isian-pilih">
                            <option :value="false">Tidak</option>
                            <option :value="true">Ya</option>
                        </select>
                    </div>
                    <div v-if="form.penerima_kps" class="grid gap-2">
                        <Label for="nomor_kps" class="label-isian">Nomor KPS *</Label>
                        <Input id="nomor_kps" v-model="form.nomor_kps" required />
                        <InputError class="pesan-galat" :message="form.errors.nomor_kps" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jenis_pembiayaan" class="label-isian">Jenis Pembiayaan</Label>
                        <select id="jenis_pembiayaan" v-model="form.jenis_pembiayaan" class="isian isian-pilih">
                            <option value="">Pilih jenis pembiayaan</option>
                            <option v-for="o in props.opsi.jenis_pembiayaan" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.jenis_pembiayaan" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="jumlah_pembiayaan" class="label-isian">Jumlah Pembiayaan</Label>
                        <Input id="jumlah_pembiayaan" v-model="form.jumlah_pembiayaan" type="number" min="0" />
                        <span v-if="form.jumlah_pembiayaan" class="teks-bantu">{{ rupiah(Number(form.jumlah_pembiayaan)) }}</span>
                        <InputError class="pesan-galat" :message="form.errors.jumlah_pembiayaan" />
                    </div>
                </div>
            </section>

            <section class="kartu p-6 sm:p-8">
                <h2 class="judul-bagian">Pilih Jurusan Calon Mahasiswa Baru</h2>
                <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="kelas" class="label-isian">Kelas *</Label>
                        <select id="kelas" v-model="form.kelas" class="isian isian-pilih" required>
                            <option value="" disabled>Pilih kelas</option>
                            <option v-for="o in props.opsi.kelas" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.kelas" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="program_studi_id" class="label-isian">Jurusan/Program Studi *</Label>
                        <select id="program_studi_id" v-model="form.program_studi_id" class="isian isian-pilih" required>
                            <option value="" disabled>Pilih program studi</option>
                            <option v-for="p in props.programStudi" :key="p.id" :value="p.id">
                                {{ [p.jenjang, p.nama_prodi].filter(Boolean).join(' ') }}
                            </option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.program_studi_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="status_masuk" class="label-isian">Status Calon Mahasiswa Baru *</Label>
                        <select id="status_masuk" v-model="form.status_masuk" class="isian isian-pilih" required>
                            <option v-for="o in props.opsi.status_masuk" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <InputError class="pesan-galat" :message="form.errors.status_masuk" />
                    </div>

                    <template v-if="form.status_masuk === 'B'">
                        <div class="grid gap-2">
                            <Label for="asal_sekolah" class="label-isian">Asal Sekolah</Label>
                            <Input id="asal_sekolah" v-model="form.asal_sekolah" />
                            <InputError class="pesan-galat" :message="form.errors.asal_sekolah" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nisn" class="label-isian">NISN</Label>
                            <Input id="nisn" v-model="form.nisn" inputmode="numeric" maxlength="10" />
                            <InputError class="pesan-galat" :message="form.errors.nisn" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nilai_un" class="label-isian">Nilai UN</Label>
                            <Input id="nilai_un" v-model="form.nilai_un" maxlength="10" />
                            <InputError class="pesan-galat" :message="form.errors.nilai_un" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="grid gap-2">
                            <Label for="asal_perguruan_tinggi" class="label-isian">Asal Perguruan Tinggi</Label>
                            <Input id="asal_perguruan_tinggi" v-model="form.asal_perguruan_tinggi" />
                            <InputError class="pesan-galat" :message="form.errors.asal_perguruan_tinggi" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="jenjang_asal" class="label-isian">Jenjang</Label>
                            <select id="jenjang_asal" v-model="form.jenjang_asal" class="isian isian-pilih">
                                <option value="">Pilih jenjang</option>
                                <option v-for="o in props.opsi.jenjang" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <InputError class="pesan-galat" :message="form.errors.jenjang_asal" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="prodi_asal" class="label-isian">Program Studi Asal</Label>
                            <Input id="prodi_asal" v-model="form.prodi_asal" />
                            <InputError class="pesan-galat" :message="form.errors.prodi_asal" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nim_asal" class="label-isian">NIM Asal</Label>
                            <Input id="nim_asal" v-model="form.nim_asal" maxlength="30" />
                            <InputError class="pesan-galat" :message="form.errors.nim_asal" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sks_diakui" class="label-isian">SKS Diakui</Label>
                            <Input id="sks_diakui" v-model="form.sks_diakui" type="number" min="0" max="200" />
                            <InputError class="pesan-galat" :message="form.errors.sks_diakui" />
                        </div>
                    </template>

                    <div class="grid gap-2">
                        <Label for="agen" class="label-isian">Nama Lengkap Agen / Referensi</Label>
                        <Input id="agen" v-model="form.agen" />
                        <InputError class="pesan-galat" :message="form.errors.agen" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="info" class="label-isian">Info Dari?</Label>
                        <Input id="info" v-model="form.info" placeholder="Brosur, baliho, Google, Instagram, saudara, dll." />
                        <InputError class="pesan-galat" :message="form.errors.info" />
                    </div>
                </div>
            </section>

            <div class="kartu flex flex-col gap-4 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8">
                <div v-if="recaptchaSiteKey" class="grid gap-2">
                    <RecaptchaWidget ref="captcha" v-model="form['g-recaptcha-response']" :site-key="recaptchaSiteKey" />
                    <InputError class="pesan-galat" :message="errors.captcha" />
                </div>
                <p v-else class="text-sm text-[#615d59]">Periksa kembali data Anda sebelum mengirim.</p>
                <Button type="submit" :disabled="form.processing" class="sm:min-w-[180px]">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ form.processing ? 'Mengirim…' : 'Kirim Pendaftaran' }}
                </Button>
            </div>
        </form>
    </PmbLayout>
</template>
