<script setup lang="ts">
import BiodataPddikti from '@/components/BiodataPddikti.vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import UnggahFoto from '@/components/UnggahFoto.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    title: string;
    type: string;
    user: Record<string, any> | null;
    roles: { id: number; name: string }[];
    defaultRoleId: number | null;
    dosenWali: { id: number; name: string }[];
    programStudi: { id: number; nama_prodi: string; jenjang: string; fakultas: string | null }[];
    opsi: Record<string, { value: string | number; label: string }[]>;
}>();
// Biodata PDDIKTI (asal formulir PMB), sama dengan BiodataPddiktiRules::FIELDS.
const biodataFields = [
    'nik',
    'npwp',
    'status_sipil',
    'telepon_wali',
    'dusun',
    'rt',
    'rw',
    'kelurahan',
    'wilayah_kecamatan_id',
    'kode_pos',
    'alat_transportasi',
    'jenis_tinggal',
    'jenis_masuk',
    'penerima_kps',
    'nomor_kps',
    'jenis_pembiayaan',
    'jumlah_pembiayaan',
    'jalur_kelas',
    'nilai_un',
    'asal_perguruan_tinggi',
    'jenjang_asal',
    'prodi_asal',
    'nim_asal',
    'sks_diakui',
];
const common = ['name', 'username', 'email', 'tempat_lahir', 'tanggal_lahir', 'no_telepon', 'kewarganegaraan'];
const roleFields =
    props.type === 'karyawan'
        ? ['nomor_induk']
        : props.type === 'dosen'
          ? ['nidn', 'jabatan_fungsional', 'pendidikan_terakhir', 'status_kepegawaian', 'status', 'prodi_id']
          : props.type === 'mahasiswa'
            ? [
                  'nim',
                  'angkatan',
                  'semester_masuk',
                  'status',
                  'dosen_wali_id',
                  'prodi_id',
                  'sekolah_asal',
                  'nisn',
                  'email_alternatif',
                  'nama_ayah_kandung',
                  'tanggal_lahir_ayah',
                  'pendidikan_terakhir_ayah',
                  'pekerjaan_ayah',
                  'penghasilan_ayah',
                  'no_telepon_ayah',
                  'email_ayah',
                  'alamat_ayah',
                  'nama_ibu_kandung',
                  'tanggal_lahir_ibu',
                  'pendidikan_terakhir_ibu',
                  'pekerjaan_ibu',
                  'penghasilan_ibu',
                  'no_telepon_ibu',
                  'email_ibu',
                  'alamat_ibu',
                  ...biodataFields,
              ]
            : [];
const labels: Record<string, string> = {
    name: 'Nama',
    username: 'Username',
    email: 'Email',
    tempat_lahir: 'Tempat Lahir',
    tanggal_lahir: 'Tanggal Lahir',
    no_telepon: 'Nomor Telepon',
    kewarganegaraan: 'Kewarganegaraan',
    nomor_induk: 'Nomor Induk',
    nidn: 'NIDN',
    jabatan_fungsional: 'Jabatan Fungsional',
    pendidikan_terakhir: 'Pendidikan Terakhir',
    status_kepegawaian: 'Status Kepegawaian',
    nim: 'NIM',
    angkatan: 'Angkatan',
    semester_masuk: 'Semester Masuk',
    status: 'Status',
    dosen_wali_id: 'Dosen Wali',
    prodi_id: 'Program Studi',
    sekolah_asal: 'Sekolah Asal',
    nisn: 'NISN',
    email_alternatif: 'Email Alternatif',
    nama_ayah_kandung: 'Nama Ayah Kandung',
    tanggal_lahir_ayah: 'Tanggal Lahir Ayah',
    pendidikan_terakhir_ayah: 'Pendidikan Terakhir Ayah',
    pekerjaan_ayah: 'Pekerjaan Ayah',
    penghasilan_ayah: 'Penghasilan Ayah',
    no_telepon_ayah: 'Nomor Telepon Ayah',
    email_ayah: 'Email Ayah',
    alamat_ayah: 'Alamat Ayah',
    nama_ibu_kandung: 'Nama Ibu Kandung',
    tanggal_lahir_ibu: 'Tanggal Lahir Ibu',
    pendidikan_terakhir_ibu: 'Pendidikan Terakhir Ibu',
    pekerjaan_ibu: 'Pekerjaan Ibu',
    penghasilan_ibu: 'Penghasilan Ibu',
    no_telepon_ibu: 'Nomor Telepon Ibu',
    email_ibu: 'Email Ibu',
    alamat_ibu: 'Alamat Ibu',
    alamat: 'Alamat',
    role_id: 'Role',
    password: 'Kata Sandi',
    password_confirmation: 'Konfirmasi Kata Sandi',
};
const agama = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];
// Dosen hanya Aktif/Nonaktif. Mahasiswa Pindahan (dari PMB) diperlakukan sama dengan Aktif.
const statuses =
    props.type === 'dosen' ? ['Aktif', 'Nonaktif'] : ['Aktif', 'Pindahan', 'Nonaktif', 'Lulus', 'Dropout', 'Cuti', 'Mengundurkan Diri', 'Meninggal'];
const pekerjaanOptions = [
    'Tidak Bekerja',
    'Karyawan Swasta',
    'Pegawai Negeri Sipil (PNS)',
    'TNI / Polri',
    'Wiraswasta / Pengusaha',
    'Profesional',
    'Guru / Dosen',
    'Tenaga Kesehatan',
    'Petani',
    'Peternak',
    'Nelayan',
    'Pedagang',
    'Ibu Rumah Tangga',
    'Freelancer',
    'Pensiunan',
    'Sudah Meninggal',
    'Lainnya',
];
const penghasilanOptions = [
    'Kurang dari Rp1.000.000',
    'Rp1.000.000 – Rp2.999.999',
    'Rp3.000.000 – Rp4.999.999',
    'Rp5.000.000 – Rp7.499.999',
    'Rp7.500.000 – Rp9.999.999',
    'Rp10.000.000 – Rp14.999.999',
    'Rp15.000.000 atau lebih',
    'Tidak Berpenghasilan',
];
const groupedProgramStudi = computed(() => {
    const groups: Record<string, typeof props.programStudi> = {};
    for (const prodi of props.programStudi) {
        const key = prodi.fakultas ?? 'Fakultas Lainnya';
        (groups[key] ??= []).push(prodi);
    }

    return Object.entries(groups);
});
// Fallback: tampilkan nilai lama yang tidak ada di opsi agar tidak hilang saat edit.
const pekerjaanAyahOptions = computed(() =>
    form.pekerjaan_ayah && !pekerjaanOptions.includes(form.pekerjaan_ayah) ? [...pekerjaanOptions, form.pekerjaan_ayah] : pekerjaanOptions,
);
const penghasilanAyahOptions = computed(() =>
    form.penghasilan_ayah && !penghasilanOptions.includes(form.penghasilan_ayah)
        ? [...penghasilanOptions, form.penghasilan_ayah]
        : penghasilanOptions,
);
const pekerjaanIbuOptions = computed(() =>
    form.pekerjaan_ibu && !pekerjaanOptions.includes(form.pekerjaan_ibu) ? [...pekerjaanOptions, form.pekerjaan_ibu] : pekerjaanOptions,
);
const penghasilanIbuOptions = computed(() =>
    form.penghasilan_ibu && !penghasilanOptions.includes(form.penghasilan_ibu) ? [...penghasilanOptions, form.penghasilan_ibu] : penghasilanOptions,
);
const isMahasiswa = props.type === 'mahasiswa';
const title = computed(() => `${props.user ? 'Edit' : 'Tambah'} Pengguna - ${props.type.charAt(0).toUpperCase()}${props.type.slice(1)}`);
// Field dibangun dinamis sesuai jenis user, jadi tipenya berupa peta nama field -> nilai.
const form = useForm<Record<string, string>>({
    role_id: props.defaultRoleId ? String(props.defaultRoleId) : '',
    ...Object.fromEntries(
        [...common, ...roleFields, 'jenis_kelamin', 'agama', 'alamat', 'password', 'password_confirmation'].map((field) => [
            field,
            field === 'penerima_kps'
                ? props.user?.[field]
                    ? '1'
                    : '0'
                : props.user?.[field] == null
                  ? field === 'status' && props.type === 'dosen'
                      ? 'Aktif'
                      : ''
                  : String(props.user[field]),
        ]),
    ),
});
const search = ref('');
const dosenWaliOpen = ref(false);
const dosenWaliRef = ref<HTMLElement | null>(null);
const filteredDosen = computed(() => props.dosenWali.filter((dosen) => dosen.name.toLowerCase().includes(search.value.toLowerCase())));
const selectedDosen = computed(() => props.dosenWali.find((dosen) => dosen.id === Number(form.dosen_wali_id)));
const toggleDosenWali = () => {
    dosenWaliOpen.value = !dosenWaliOpen.value;
    if (dosenWaliOpen.value) search.value = '';
};
const selectDosenWali = (id: number) => {
    form.dosen_wali_id = String(id);
    dosenWaliOpen.value = false;
    search.value = '';
};
const handleDosenWaliKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        dosenWaliOpen.value = false;
    }
};
const onClickOutside = (event: MouseEvent) => {
    if (!dosenWaliOpen.value || !dosenWaliRef.value) return;
    if (!dosenWaliRef.value.contains(event.target as Node)) {
        dosenWaliOpen.value = false;
    }
};
onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
// Foto dikirim sebagai berkas, jadi form selalu dikirim sebagai multipart (PUT lewat _method).
const foto = ref<File | null>(null);
const hapusFoto = ref(false);
const submit = () => {
    const denganFoto = (data: Record<string, any>) => ({
        ...data,
        ...(foto.value ? { foto: foto.value } : {}),
        ...(hapusFoto.value ? { hapus_foto: '1' } : {}),
    });
    if (props.user) {
        if (!form.password) form.clearErrors('password', 'password_confirmation');
        form.transform((data) => {
            const { password, password_confirmation, ...rest } = data as Record<string, any>;
            const isian = form.password ? { ...rest, password, password_confirmation } : rest;
            return { ...denganFoto(isian), _method: 'put' };
        }).post(route(`admin.users.${props.type}.update`, props.user.id), { forceFormData: true });
        return;
    }
    form.transform(denganFoto).post(route(`admin.users.${props.type}.store`), { forceFormData: true });
};
</script>
<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title, href: '#' }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p v-if="isMahasiswa" class="deskripsi-halaman">
                            Lengkapi data akun, pribadi, akademik, dan orang tua. NIM, dosen wali, email alternatif, dan data orang tua (selain nama
                            ibu) boleh dilengkapi belakangan.
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route(`admin.users.${props.type}`)">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form v-if="isMahasiswa" class="flex flex-col gap-6" @submit.prevent="submit">
                    <!-- Data Akun -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Akun</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name" class="label-isian">{{ labels.name }}</Label
                                ><Input id="name" v-model="form.name" required /><InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="username" class="label-isian">{{ labels.username }}</Label
                                ><Input id="username" v-model="form.username" required /><InputError :message="form.errors.username" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="email" class="label-isian">{{ labels.email }}</Label
                                ><Input id="email" type="email" v-model="form.email" required /><InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="role_id" class="label-isian">{{ labels.role_id }}</Label
                                ><select id="role_id" v-model="form.role_id" class="isian isian-pilih" required>
                                    <option value="">Pilih role</option>
                                    <option v-for="role in props.roles" :key="role.id" :value="role.id">{{ role.name }}</option></select
                                ><InputError :message="form.errors.role_id" />
                            </div>
                        </div>
                    </section>

                    <!-- Data Pribadi -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Pribadi</h2>
                        <div class="mt-4">
                            <UnggahFoto v-model="foto" v-model:hapus="hapusFoto" :url-tersimpan="props.user?.foto_url" :error="form.errors.foto" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tempat_lahir" class="label-isian">{{ labels.tempat_lahir }}</Label
                                ><Input id="tempat_lahir" v-model="form.tempat_lahir" required /><InputError :message="form.errors.tempat_lahir" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_lahir" class="label-isian">{{ labels.tanggal_lahir }}</Label
                                ><DatePicker id="tanggal_lahir" v-model="form.tanggal_lahir" placeholder="Pilih tanggal lahir" /><InputError
                                    :message="form.errors.tanggal_lahir"
                                />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label class="label-isian">Jenis Kelamin</Label>
                            <div class="flex gap-6">
                                <label
                                    v-for="gender in ['Laki-laki', 'Perempuan']"
                                    :key="gender"
                                    class="flex items-center gap-2 text-sm text-[#31302e] dark:text-foreground"
                                    ><input v-model="form.jenis_kelamin" type="radio" :value="gender" class="accent-[#0075de]" required />
                                    {{ gender }}</label
                                >
                            </div>
                            <InputError :message="form.errors.jenis_kelamin" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="agama" class="label-isian">Agama</Label
                                ><select id="agama" v-model="form.agama" class="isian isian-pilih" required>
                                    <option value="">Pilih agama</option>
                                    <option v-for="item in agama" :key="item" :value="item">{{ item }}</option></select
                                ><InputError :message="form.errors.agama" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="no_telepon" class="label-isian">{{ labels.no_telepon }}</Label
                                ><Input id="no_telepon" v-model="form.no_telepon" required /><InputError :message="form.errors.no_telepon" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="kewarganegaraan" class="label-isian">{{ labels.kewarganegaraan }}</Label
                            ><Input id="kewarganegaraan" v-model="form.kewarganegaraan" required /><InputError
                                :message="form.errors.kewarganegaraan"
                            />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="alamat" class="label-isian">{{ labels.alamat }}</Label
                            ><textarea id="alamat" v-model="form.alamat" class="isian isian-area" required /><InputError
                                :message="form.errors.alamat"
                            />
                        </div>
                    </section>

                    <!-- Data Akademik -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Akademik</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="nim" class="label-isian">{{ labels.nim }}</Label
                                ><Input id="nim" v-model="form.nim" /><InputError :message="form.errors.nim" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="angkatan" class="label-isian">{{ labels.angkatan }}</Label
                                ><Input id="angkatan" v-model="form.angkatan" type="number" required /><InputError :message="form.errors.angkatan" />
                                <p class="teks-bantu">Tahun masuk, misalnya 2026. Semester mahasiswa dihitung otomatis dari angkatan.</p>
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="semester_masuk" class="label-isian">{{ labels.semester_masuk }}</Label
                                ><Input
                                    id="semester_masuk"
                                    v-model="form.semester_masuk"
                                    type="number"
                                    min="1"
                                    max="14"
                                    placeholder="Kosongkan bila dari semester 1"
                                /><InputError :message="form.errors.semester_masuk" />
                                <p class="teks-bantu">
                                    Untuk mahasiswa pindahan: semester pada tahun akademik aktif sesuai hasil konversi SKS. Ganjil = 1, 3, 5…; Genap =
                                    2, 4, 6….
                                    <template v-if="props.user?.semester_masuk_pada">Tercatat pada {{ props.user.semester_masuk_pada }}.</template>
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="status" class="label-isian">{{ labels.status }}</Label
                                ><select id="status" v-model="form.status" class="isian isian-pilih" required>
                                    <option value="">Pilih status</option>
                                    <option v-for="item in statuses" :key="item" :value="item">{{ item }}</option></select
                                ><InputError :message="form.errors.status" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="prodi_id" class="label-isian">{{ labels.prodi_id }}</Label
                                ><select id="prodi_id" v-model="form.prodi_id" class="isian isian-pilih" required>
                                    <option value="">Pilih program studi</option>
                                    <optgroup v-for="[fakultas, prodiList] in groupedProgramStudi" :key="fakultas" :label="fakultas">
                                        <option v-for="item in prodiList" :key="item.id" :value="item.id">
                                            {{ item.jenjang }} - {{ item.nama_prodi }}
                                        </option>
                                    </optgroup></select
                                ><InputError :message="form.errors.prodi_id" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="dosen_wali_id" class="label-isian">{{ labels.dosen_wali_id }}</Label>
                            <div ref="dosenWaliRef" class="relative">
                                <button
                                    id="dosen_wali_id"
                                    type="button"
                                    class="isian flex items-center justify-between text-left"
                                    role="combobox"
                                    :aria-expanded="dosenWaliOpen"
                                    aria-controls="dosen-wali-options"
                                    :aria-invalid="!!form.errors.dosen_wali_id"
                                    @click="toggleDosenWali"
                                    @keydown="handleDosenWaliKeydown"
                                >
                                    <span>{{ selectedDosen?.name ?? 'Pilih dosen wali' }}</span
                                    ><ChevronDown
                                        class="size-4 shrink-0 text-[#a39e98] transition-transform"
                                        :class="dosenWaliOpen ? 'rotate-180' : ''"
                                    />
                                </button>
                                <div
                                    v-if="dosenWaliOpen"
                                    class="absolute z-10 mt-1 w-full rounded-xl border border-[#e6e6e6] bg-white p-2 shadow-lg dark:border-border dark:bg-popover"
                                    role="listbox"
                                    id="dosen-wali-options"
                                >
                                    <Input v-model="search" placeholder="Cari dosen wali" aria-label="Cari dosen wali" autofocus />
                                    <div class="mt-1 max-h-48 overflow-y-auto">
                                        <button
                                            v-for="dosen in filteredDosen"
                                            :key="dosen.id"
                                            type="button"
                                            class="block w-full rounded-lg px-2 py-2 text-left text-sm hover:bg-[#f6f5f4] dark:hover:bg-accent"
                                            role="option"
                                            :aria-selected="Number(form.dosen_wali_id) === dosen.id"
                                            @click="selectDosenWali(dosen.id)"
                                        >
                                            {{ dosen.name }}
                                        </button>
                                        <p v-if="filteredDosen.length === 0" class="px-2 py-2 text-sm text-[#615d59]">Dosen tidak ditemukan</p>
                                    </div>
                                </div>
                                <input id="dosen_wali_id-value" v-model="form.dosen_wali_id" type="hidden" />
                            </div>
                            <InputError :message="form.errors.dosen_wali_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="sekolah_asal" class="label-isian">{{ labels.sekolah_asal }}</Label
                                ><Input id="sekolah_asal" v-model="form.sekolah_asal" /><InputError :message="form.errors.sekolah_asal" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nisn" class="label-isian">{{ labels.nisn }}</Label
                                ><Input id="nisn" type="text" inputmode="numeric" v-model="form.nisn" /><InputError :message="form.errors.nisn" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="email_alternatif" class="label-isian">{{ labels.email_alternatif }}</Label
                            ><Input id="email_alternatif" type="email" v-model="form.email_alternatif" /><InputError
                                :message="form.errors.email_alternatif"
                            />
                        </div>
                    </section>

                    <BiodataPddikti :form="form" :opsi="props.opsi" :kecamatan-label="props.user?.kecamatan_label" />

                    <!-- Data Ayah Kandung -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Ayah Kandung</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="nama_ayah_kandung" class="label-isian">{{ labels.nama_ayah_kandung }}</Label
                            ><Input id="nama_ayah_kandung" v-model="form.nama_ayah_kandung" /><InputError :message="form.errors.nama_ayah_kandung" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_lahir_ayah" class="label-isian">{{ labels.tanggal_lahir_ayah }}</Label
                                ><DatePicker
                                    id="tanggal_lahir_ayah"
                                    v-model="form.tanggal_lahir_ayah"
                                    placeholder="Pilih tanggal lahir ayah"
                                /><InputError :message="form.errors.tanggal_lahir_ayah" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pendidikan_terakhir_ayah" class="label-isian">{{ labels.pendidikan_terakhir_ayah }}</Label
                                ><Input id="pendidikan_terakhir_ayah" v-model="form.pendidikan_terakhir_ayah" /><InputError
                                    :message="form.errors.pendidikan_terakhir_ayah"
                                />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="pekerjaan_ayah" class="label-isian">{{ labels.pekerjaan_ayah }}</Label
                                ><select id="pekerjaan_ayah" v-model="form.pekerjaan_ayah" class="isian isian-pilih">
                                    <option value="">Pilih pekerjaan</option>
                                    <option v-for="item in pekerjaanAyahOptions" :key="item" :value="item">{{ item }}</option></select
                                ><InputError :message="form.errors.pekerjaan_ayah" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="penghasilan_ayah" class="label-isian">{{ labels.penghasilan_ayah }}</Label
                                ><select id="penghasilan_ayah" v-model="form.penghasilan_ayah" class="isian isian-pilih">
                                    <option value="">Pilih penghasilan</option>
                                    <option v-for="item in penghasilanAyahOptions" :key="item" :value="item">{{ item }}</option></select
                                ><InputError :message="form.errors.penghasilan_ayah" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="no_telepon_ayah" class="label-isian">{{ labels.no_telepon_ayah }}</Label
                                ><Input id="no_telepon_ayah" v-model="form.no_telepon_ayah" /><InputError :message="form.errors.no_telepon_ayah" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email_ayah" class="label-isian">{{ labels.email_ayah }}</Label
                                ><Input id="email_ayah" type="email" v-model="form.email_ayah" /><InputError :message="form.errors.email_ayah" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="alamat_ayah" class="label-isian">{{ labels.alamat_ayah }}</Label
                            ><textarea id="alamat_ayah" v-model="form.alamat_ayah" class="isian isian-area min-h-20" /><InputError
                                :message="form.errors.alamat_ayah"
                            />
                        </div>
                    </section>

                    <!-- Data Ibu Kandung -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Ibu Kandung</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="nama_ibu_kandung" class="label-isian">{{ labels.nama_ibu_kandung }}</Label
                            ><Input id="nama_ibu_kandung" v-model="form.nama_ibu_kandung" required /><InputError
                                :message="form.errors.nama_ibu_kandung"
                            />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_lahir_ibu" class="label-isian">{{ labels.tanggal_lahir_ibu }}</Label
                                ><DatePicker
                                    id="tanggal_lahir_ibu"
                                    v-model="form.tanggal_lahir_ibu"
                                    placeholder="Pilih tanggal lahir ibu"
                                /><InputError :message="form.errors.tanggal_lahir_ibu" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pendidikan_terakhir_ibu" class="label-isian">{{ labels.pendidikan_terakhir_ibu }}</Label
                                ><Input id="pendidikan_terakhir_ibu" v-model="form.pendidikan_terakhir_ibu" /><InputError
                                    :message="form.errors.pendidikan_terakhir_ibu"
                                />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="pekerjaan_ibu" class="label-isian">{{ labels.pekerjaan_ibu }}</Label
                                ><select id="pekerjaan_ibu" v-model="form.pekerjaan_ibu" class="isian isian-pilih">
                                    <option value="">Pilih pekerjaan</option>
                                    <option v-for="item in pekerjaanIbuOptions" :key="item" :value="item">{{ item }}</option></select
                                ><InputError :message="form.errors.pekerjaan_ibu" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="penghasilan_ibu" class="label-isian">{{ labels.penghasilan_ibu }}</Label
                                ><select id="penghasilan_ibu" v-model="form.penghasilan_ibu" class="isian isian-pilih">
                                    <option value="">Pilih penghasilan</option>
                                    <option v-for="item in penghasilanIbuOptions" :key="item" :value="item">{{ item }}</option></select
                                ><InputError :message="form.errors.penghasilan_ibu" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="no_telepon_ibu" class="label-isian">{{ labels.no_telepon_ibu }}</Label
                                ><Input id="no_telepon_ibu" v-model="form.no_telepon_ibu" /><InputError :message="form.errors.no_telepon_ibu" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email_ibu" class="label-isian">{{ labels.email_ibu }}</Label
                                ><Input id="email_ibu" type="email" v-model="form.email_ibu" /><InputError :message="form.errors.email_ibu" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="alamat_ibu" class="label-isian">{{ labels.alamat_ibu }}</Label
                            ><textarea id="alamat_ibu" v-model="form.alamat_ibu" class="isian isian-area min-h-20" /><InputError
                                :message="form.errors.alamat_ibu"
                            />
                        </div>
                    </section>

                    <!-- Keamanan -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Keamanan</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div v-for="field in ['password', 'password_confirmation']" :key="field" class="grid gap-2">
                                <Label :for="field" class="label-isian">{{ labels[field] }}</Label
                                ><Input
                                    :id="field"
                                    type="password"
                                    v-model="form[field]"
                                    :required="!props.user"
                                    autocomplete="new-password"
                                /><InputError :message="form.errors[field]" />
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>

                <form v-else class="kartu p-6" @submit.prevent="submit">
                    <div class="space-y-4">
                        <UnggahFoto v-model="foto" v-model:hapus="hapusFoto" :url-tersimpan="props.user?.foto_url" :error="form.errors.foto" />
                        <div class="grid gap-2">
                            <Label for="role_id" class="label-isian">{{ labels.role_id }}</Label
                            ><select id="role_id" v-model="form.role_id" class="isian isian-pilih" required>
                                <option value="">Pilih role</option>
                                <option v-for="role in props.roles" :key="role.id" :value="role.id">{{ role.name }}</option></select
                            ><InputError :message="form.errors.role_id" />
                        </div>
                        <template v-for="field in common" :key="field">
                            <div v-if="field === 'tanggal_lahir'" class="grid gap-2">
                                <Label :for="field" class="label-isian">{{ labels[field] }}</Label
                                ><DatePicker :id="field" v-model="form[field]" placeholder="Pilih tanggal lahir" /><InputError
                                    :message="form.errors[field]"
                                />
                            </div>
                            <div v-else class="grid gap-2">
                                <Label :for="field" class="label-isian">{{ labels[field] }}</Label
                                ><Input :id="field" :type="field.includes('email') ? 'email' : 'text'" v-model="form[field]" required /><InputError
                                    :message="form.errors[field]"
                                />
                            </div>
                        </template>
                        <div class="grid gap-2">
                            <Label class="label-isian">Jenis Kelamin</Label>
                            <div class="flex gap-6">
                                <label
                                    v-for="gender in ['Laki-laki', 'Perempuan']"
                                    :key="gender"
                                    class="flex items-center gap-2 text-sm text-[#31302e] dark:text-foreground"
                                    ><input v-model="form.jenis_kelamin" type="radio" :value="gender" class="accent-[#0075de]" required />
                                    {{ gender }}</label
                                >
                            </div>
                            <InputError :message="form.errors.jenis_kelamin" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="agama" class="label-isian">Agama</Label
                            ><select id="agama" v-model="form.agama" class="isian isian-pilih" required>
                                <option value="">Pilih agama</option>
                                <option v-for="item in agama" :key="item" :value="item">{{ item }}</option></select
                            ><InputError :message="form.errors.agama" />
                        </div>
                        <div v-for="field in roleFields" :key="field" class="grid gap-2">
                            <Label :for="field" class="label-isian"
                                >{{ labels[field]
                                }}<span v-if="field === 'prodi_id' && props.type === 'dosen'" class="font-normal text-[#a39e98]">
                                    (opsional)</span
                                ></Label
                            ><select v-if="field === 'status'" :id="field" v-model="form[field]" class="isian isian-pilih" required>
                                <option value="">Pilih status</option>
                                <option v-for="item in statuses" :key="item" :value="item">{{ item }}</option></select
                            ><select
                                v-else-if="field === 'prodi_id'"
                                :id="field"
                                v-model="form[field]"
                                class="isian isian-pilih"
                                :required="props.type !== 'dosen'"
                            >
                                <!-- Prodi dosen opsional: dosen pertama (dekan/kaprodi) dibuat sebelum ada program studi. -->
                                <option value="">{{ props.type === 'dosen' ? 'Tanpa program studi' : 'Pilih program studi' }}</option>
                                <optgroup v-for="[fakultas, prodiList] in groupedProgramStudi" :key="fakultas" :label="fakultas">
                                    <option v-for="item in prodiList" :key="item.id" :value="item.id">
                                        {{ item.jenjang }} - {{ item.nama_prodi }}
                                    </option>
                                </optgroup></select
                            ><template v-else-if="field === 'dosen_wali_id'"
                                ><div ref="dosenWaliRef" class="relative">
                                    <button
                                        :id="field"
                                        type="button"
                                        class="isian flex items-center justify-between text-left"
                                        role="combobox"
                                        :aria-expanded="dosenWaliOpen"
                                        @click="toggleDosenWali"
                                        @keydown="handleDosenWaliKeydown"
                                    >
                                        <span>{{ selectedDosen?.name ?? 'Pilih dosen wali' }}</span
                                        ><ChevronDown
                                            class="size-4 shrink-0 text-[#a39e98] transition-transform"
                                            :class="dosenWaliOpen ? 'rotate-180' : ''"
                                        />
                                    </button>
                                    <div
                                        v-if="dosenWaliOpen"
                                        class="absolute z-10 mt-1 w-full rounded-xl border border-[#e6e6e6] bg-white p-2 shadow-lg dark:border-border dark:bg-popover"
                                        role="listbox"
                                        id="dosen-wali-options"
                                    >
                                        <Input v-model="search" placeholder="Cari dosen wali" autofocus />
                                        <div class="mt-1 max-h-48 overflow-y-auto">
                                            <button
                                                v-for="dosen in filteredDosen"
                                                :key="dosen.id"
                                                type="button"
                                                class="block w-full rounded-lg px-2 py-2 text-left text-sm hover:bg-[#f6f5f4] dark:hover:bg-accent"
                                                @click="selectDosenWali(dosen.id)"
                                            >
                                                {{ dosen.name }}
                                            </button>
                                            <p v-if="filteredDosen.length === 0" class="px-2 py-2 text-sm text-[#615d59]">Dosen tidak ditemukan</p>
                                        </div>
                                    </div>
                                    <input :id="`${field}-value`" v-model="form[field]" type="hidden" required /></div></template
                            ><Input v-else :id="field" v-model="form[field]" :type="field === 'angkatan' ? 'number' : 'text'" required /><InputError
                                :message="form.errors[field]"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="alamat" class="label-isian">{{ labels.alamat }}</Label
                            ><textarea id="alamat" v-model="form.alamat" class="isian isian-area" required /><InputError
                                :message="form.errors.alamat"
                            />
                        </div>
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            <div v-for="field in ['password', 'password_confirmation']" :key="field" class="grid gap-2">
                                <Label :for="field" class="label-isian">{{ labels[field] }}</Label
                                ><Input
                                    :id="field"
                                    type="password"
                                    v-model="form[field]"
                                    :required="!props.user"
                                    autocomplete="new-password"
                                /><InputError :message="form.errors[field]" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
