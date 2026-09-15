<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{ title: string; type: string; user: Record<string, any> | null; dosenWali: { id: number; name: string }[]; programStudi: { id: number; nama_prodi: string; jenjang: string; fakultas: string | null }[] }>();
const common = ['name', 'username', 'email', 'tempat_lahir', 'tanggal_lahir', 'no_telepon', 'kewarganegaraan'];
const roleFields =
    props.type === 'karyawan'
        ? ['nomor_induk']
        : props.type === 'dosen'
          ? ['nidn', 'jabatan_fungsional', 'pendidikan_terakhir', 'status_kepegawaian', 'prodi_id']
          : props.type === 'mahasiswa'
            ? [
                  'nim',
                  'angkatan',
                  'semester',
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
    semester: 'Semester',
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
    password: 'Kata Sandi',
    password_confirmation: 'Konfirmasi Kata Sandi',
};
const agama = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'];
const statuses = ['Aktif', 'Nonaktif', 'Lulus', 'Dropout', 'Cuti', 'Mengundurkan Diri', 'Meninggal', 'Transfer Masuk'];
const pekerjaanOptions = ['Tidak Bekerja', 'Karyawan Swasta', 'Pegawai Negeri Sipil (PNS)', 'TNI / Polri', 'Wiraswasta / Pengusaha', 'Profesional', 'Guru / Dosen', 'Tenaga Kesehatan', 'Petani', 'Peternak', 'Nelayan', 'Pedagang', 'Ibu Rumah Tangga', 'Freelancer', 'Pensiunan', 'Sudah Meninggal', 'Lainnya'];
const penghasilanOptions = ['Kurang dari Rp1.000.000', 'Rp1.000.000 – Rp2.999.999', 'Rp3.000.000 – Rp4.999.999', 'Rp5.000.000 – Rp7.499.999', 'Rp7.500.000 – Rp9.999.999', 'Rp10.000.000 – Rp14.999.999', 'Rp15.000.000 atau lebih', 'Tidak Berpenghasilan'];
const groupedProgramStudi = computed(() => {
    const groups: Record<string, typeof props.programStudi> = {};
    for (const prodi of props.programStudi) {
        const key = prodi.fakultas ?? 'Fakultas Lainnya';
        (groups[key] ??= []).push(prodi);
    }

    return Object.entries(groups);
});
// Fallback: tampilkan nilai lama yang tidak ada di opsi agar tidak hilang saat edit.
const pekerjaanAyahOptions = computed(() => (form.pekerjaan_ayah && !pekerjaanOptions.includes(form.pekerjaan_ayah) ? [...pekerjaanOptions, form.pekerjaan_ayah] : pekerjaanOptions));
const penghasilanAyahOptions = computed(() => (form.penghasilan_ayah && !penghasilanOptions.includes(form.penghasilan_ayah) ? [...penghasilanOptions, form.penghasilan_ayah] : penghasilanOptions));
const pekerjaanIbuOptions = computed(() => (form.pekerjaan_ibu && !pekerjaanOptions.includes(form.pekerjaan_ibu) ? [...pekerjaanOptions, form.pekerjaan_ibu] : pekerjaanOptions));
const penghasilanIbuOptions = computed(() => (form.penghasilan_ibu && !penghasilanOptions.includes(form.penghasilan_ibu) ? [...penghasilanOptions, form.penghasilan_ibu] : penghasilanOptions));
const isMahasiswa = props.type === 'mahasiswa';
const title = computed(() => `${props.user ? 'Edit' : 'Tambah'} Pengguna - ${props.type.charAt(0).toUpperCase()}${props.type.slice(1)}`);
const form = useForm(
    Object.fromEntries([...common, ...roleFields, 'jenis_kelamin', 'agama', 'alamat', 'password', 'password_confirmation'].map((field) => [field, props.user?.[field] ?? ''])),
);
const search = ref('');
const dosenWaliOpen = ref(false);
const dosenWaliRef = ref<HTMLElement | null>(null);
const filteredDosen = computed(() => props.dosenWali.filter((dosen) => dosen.name.toLowerCase().includes(search.value.toLowerCase())));
const selectedDosen = computed(() => props.dosenWali.find((dosen) => dosen.id === Number(form.dosen_wali_id)));
const toggleDosenWali = () => {
    dosenWaliOpen.value = !dosenWaliOpen.value;
    if (dosenWaliOpen.value) search.value = '';
};
const closeDosenWali = () => {
    dosenWaliOpen.value = false;
};
const selectDosenWali = (id: number) => {
    form.dosen_wali_id = id;
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
const submit = () => {
    if (props.user) {
        if (!form.password) {
            form.clearErrors('password', 'password_confirmation');
            form.transform((data) => {
                const { password, password_confirmation, ...rest } = data as Record<string, any>;
                void password;
                void password_confirmation;
                return rest;
            }).put(route(`admin.users.${props.type}.update`, props.user.id), {
                onFinish: () => form.transform((data) => data),
            });
        } else {
            form.transform((data) => data).put(route(`admin.users.${props.type}.update`, props.user.id));
        }
        return;
    }
    form.post(route(`admin.users.${props.type}.store`));
};

// DESIGN.md — Notion tokens
const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel = 'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>
<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title, href: '#' }]">
        <!-- Notion warm canvas -->
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <!-- Header on paper -->
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p v-if="isMahasiswa" class="max-w-xl text-sm leading-5 text-[#615d59]">Lengkapi data akun, pribadi, akademik, dan orang tua. Semua field wajib diisi.</p>
                    </div>
                    <Link :href="route(`admin.users.${props.type}`)"><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link>
                </div>

                <!-- Flash — toast on paper -->
                <div v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01)]" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">{{ page.props.flash.error }}</div>

                <!-- Mahasiswa: sectioned form — feature-cards on paper -->
                <form v-if="isMahasiswa" @submit.prevent="submit" class="space-y-4">
                    <!-- Data Akun -->
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Akun</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="name" class="text-sm font-medium text-black">{{ labels.name }}</Label><Input id="name" v-model="form.name" :class="inp" required /><InputError :message="form.errors.name" /></div>
                            <div class="grid gap-2"><Label for="username" class="text-sm font-medium text-black">{{ labels.username }}</Label><Input id="username" v-model="form.username" :class="inp" required /><InputError :message="form.errors.username" /></div>
                        </div>
                        <div class="mt-4 grid gap-2"><Label for="email" class="text-sm font-medium text-black">{{ labels.email }}</Label><Input id="email" type="email" v-model="form.email" :class="inp" required /><InputError :message="form.errors.email" /></div>
                    </section>

                    <!-- Data Pribadi -->
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Pribadi</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="tempat_lahir" class="text-sm font-medium text-black">{{ labels.tempat_lahir }}</Label><Input id="tempat_lahir" v-model="form.tempat_lahir" :class="inp" required /><InputError :message="form.errors.tempat_lahir" /></div>
                            <div class="grid gap-2"><Label for="tanggal_lahir" class="text-sm font-medium text-black">{{ labels.tanggal_lahir }}</Label><DatePicker id="tanggal_lahir" v-model="form.tanggal_lahir" placeholder="Pilih tanggal lahir" /><InputError :message="form.errors.tanggal_lahir" /></div>
                        </div>
                        <div class="mt-4 grid gap-2"><Label class="text-sm font-medium text-black">Jenis Kelamin</Label><div class="flex gap-6"><label v-for="gender in ['Laki-laki', 'Perempuan']" :key="gender" class="flex items-center gap-2 text-[15px] text-[#31302e]"><input v-model="form.jenis_kelamin" type="radio" :value="gender" class="accent-[#0075de]" required /> {{ gender }}</label></div><InputError :message="form.errors.jenis_kelamin" /></div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="agama" class="text-sm font-medium text-black">Agama</Label><select id="agama" v-model="form.agama" :class="sel" required><option value="">Pilih agama</option><option v-for="item in agama" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.agama" /></div>
                            <div class="grid gap-2"><Label for="no_telepon" class="text-sm font-medium text-black">{{ labels.no_telepon }}</Label><Input id="no_telepon" v-model="form.no_telepon" :class="inp" required /><InputError :message="form.errors.no_telepon" /></div>
                        </div>
                        <div class="mt-4 grid gap-2"><Label for="kewarganegaraan" class="text-sm font-medium text-black">{{ labels.kewarganegaraan }}</Label><Input id="kewarganegaraan" v-model="form.kewarganegaraan" :class="inp" required /><InputError :message="form.errors.kewarganegaraan" /></div>
                        <div class="mt-4 grid gap-2"><Label for="alamat" class="text-sm font-medium text-black">{{ labels.alamat }}</Label><textarea id="alamat" v-model="form.alamat" class="min-h-24 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]" required /><InputError :message="form.errors.alamat" /></div>
                    </section>

                    <!-- Data Akademik -->
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Akademik</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2"><Label for="nim" class="text-sm font-medium text-black">{{ labels.nim }}</Label><Input id="nim" v-model="form.nim" :class="inp" required /><InputError :message="form.errors.nim" /></div>
                            <div class="grid gap-2"><Label for="angkatan" class="text-sm font-medium text-black">{{ labels.angkatan }}</Label><Input id="angkatan" v-model="form.angkatan" type="number" :class="inp" required /><InputError :message="form.errors.angkatan" /></div>
                            <div class="grid gap-2"><Label for="semester" class="text-sm font-medium text-black">{{ labels.semester }}</Label><Input id="semester" v-model="form.semester" type="number" :class="inp" required /><InputError :message="form.errors.semester" /></div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="status" class="text-sm font-medium text-black">{{ labels.status }}</Label><select id="status" v-model="form.status" :class="sel" required><option value="">Pilih status</option><option v-for="item in statuses" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.status" /></div>
                            <div class="grid gap-2"><Label for="prodi_id" class="text-sm font-medium text-black">{{ labels.prodi_id }}</Label><select id="prodi_id" v-model="form.prodi_id" :class="sel" required><option value="">Pilih program studi</option><optgroup v-for="[fakultas, prodiList] in groupedProgramStudi" :key="fakultas" :label="fakultas"><option v-for="item in prodiList" :key="item.id" :value="item.id">{{ item.jenjang }} - {{ item.nama_prodi }}</option></optgroup></select><InputError :message="form.errors.prodi_id" /></div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="dosen_wali_id" class="text-sm font-medium text-black">{{ labels.dosen_wali_id }}</Label>
                            <div ref="dosenWaliRef" class="relative">
                                <button id="dosen_wali_id" type="button" class="flex h-10 w-full items-center justify-between rounded-[4px] border border-[#dddddd] bg-white px-3 text-left text-[15px] text-black" role="combobox" :aria-expanded="dosenWaliOpen" aria-controls="dosen-wali-options" :aria-invalid="!!form.errors.dosen_wali_id" @click="toggleDosenWali" @keydown="handleDosenWaliKeydown"><span>{{ selectedDosen?.name ?? 'Pilih dosen wali' }}</span><ChevronDown class="size-4 shrink-0 text-[#a39e98] transition-transform" :class="dosenWaliOpen ? 'rotate-180' : ''" /></button>
                                <div v-if="dosenWaliOpen" class="absolute z-10 mt-1 w-full rounded-xl border border-[#e6e6e6] bg-white p-2 shadow-[0_4px_18px_rgba(0,0,0,0.04),0_23px_52px_rgba(0,0,0,0.05)]" role="listbox" id="dosen-wali-options"><Input v-model="search" placeholder="Cari dosen wali" aria-label="Cari dosen wali" :class="inp" autofocus /><div class="mt-1 max-h-48 overflow-y-auto"><button v-for="dosen in filteredDosen" :key="dosen.id" type="button" class="block w-full rounded-lg px-2 py-2 text-left text-[15px] hover:bg-[#f6f5f4]" role="option" :aria-selected="Number(form.dosen_wali_id) === dosen.id" @click="selectDosenWali(dosen.id)">{{ dosen.name }}</button><p v-if="filteredDosen.length === 0" class="px-2 py-2 text-sm text-[#615d59]">Dosen tidak ditemukan</p></div></div>
                                <input id="dosen_wali_id-value" v-model="form.dosen_wali_id" type="hidden" required />
                            </div>
                            <InputError :message="form.errors.dosen_wali_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="sekolah_asal" class="text-sm font-medium text-black">{{ labels.sekolah_asal }}</Label><Input id="sekolah_asal" v-model="form.sekolah_asal" :class="inp" required /><InputError :message="form.errors.sekolah_asal" /></div>
                            <div class="grid gap-2"><Label for="nisn" class="text-sm font-medium text-black">{{ labels.nisn }}</Label><Input id="nisn" type="text" inputmode="numeric" v-model="form.nisn" :class="inp" required /><InputError :message="form.errors.nisn" /></div>
                        </div>
                        <div class="mt-4 grid gap-2"><Label for="email_alternatif" class="text-sm font-medium text-black">{{ labels.email_alternatif }}</Label><Input id="email_alternatif" type="email" v-model="form.email_alternatif" :class="inp" required /><InputError :message="form.errors.email_alternatif" /></div>
                    </section>

                    <!-- Data Ayah Kandung -->
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Ayah Kandung</h2>
                        <div class="mt-4 grid gap-2"><Label for="nama_ayah_kandung" class="text-sm font-medium text-black">{{ labels.nama_ayah_kandung }}</Label><Input id="nama_ayah_kandung" v-model="form.nama_ayah_kandung" :class="inp" required /><InputError :message="form.errors.nama_ayah_kandung" /></div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="tanggal_lahir_ayah" class="text-sm font-medium text-black">{{ labels.tanggal_lahir_ayah }}</Label><DatePicker id="tanggal_lahir_ayah" v-model="form.tanggal_lahir_ayah" placeholder="Pilih tanggal lahir ayah" /><InputError :message="form.errors.tanggal_lahir_ayah" /></div>
                            <div class="grid gap-2"><Label for="pendidikan_terakhir_ayah" class="text-sm font-medium text-black">{{ labels.pendidikan_terakhir_ayah }}</Label><Input id="pendidikan_terakhir_ayah" v-model="form.pendidikan_terakhir_ayah" :class="inp" required /><InputError :message="form.errors.pendidikan_terakhir_ayah" /></div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="pekerjaan_ayah" class="text-sm font-medium text-black">{{ labels.pekerjaan_ayah }}</Label><select id="pekerjaan_ayah" v-model="form.pekerjaan_ayah" :class="sel" required><option value="">Pilih pekerjaan</option><option v-for="item in pekerjaanAyahOptions" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.pekerjaan_ayah" /></div>
                            <div class="grid gap-2"><Label for="penghasilan_ayah" class="text-sm font-medium text-black">{{ labels.penghasilan_ayah }}</Label><select id="penghasilan_ayah" v-model="form.penghasilan_ayah" :class="sel" required><option value="">Pilih penghasilan</option><option v-for="item in penghasilanAyahOptions" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.penghasilan_ayah" /></div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="no_telepon_ayah" class="text-sm font-medium text-black">{{ labels.no_telepon_ayah }}</Label><Input id="no_telepon_ayah" v-model="form.no_telepon_ayah" :class="inp" required /><InputError :message="form.errors.no_telepon_ayah" /></div>
                            <div class="grid gap-2"><Label for="email_ayah" class="text-sm font-medium text-black">{{ labels.email_ayah }}</Label><Input id="email_ayah" type="email" v-model="form.email_ayah" :class="inp" required /><InputError :message="form.errors.email_ayah" /></div>
                        </div>
                        <div class="mt-4 grid gap-2"><Label for="alamat_ayah" class="text-sm font-medium text-black">{{ labels.alamat_ayah }}</Label><textarea id="alamat_ayah" v-model="form.alamat_ayah" class="min-h-20 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]" required /><InputError :message="form.errors.alamat_ayah" /></div>
                    </section>

                    <!-- Data Ibu Kandung -->
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Ibu Kandung</h2>
                        <div class="mt-4 grid gap-2"><Label for="nama_ibu_kandung" class="text-sm font-medium text-black">{{ labels.nama_ibu_kandung }}</Label><Input id="nama_ibu_kandung" v-model="form.nama_ibu_kandung" :class="inp" required /><InputError :message="form.errors.nama_ibu_kandung" /></div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="tanggal_lahir_ibu" class="text-sm font-medium text-black">{{ labels.tanggal_lahir_ibu }}</Label><DatePicker id="tanggal_lahir_ibu" v-model="form.tanggal_lahir_ibu" placeholder="Pilih tanggal lahir ibu" /><InputError :message="form.errors.tanggal_lahir_ibu" /></div>
                            <div class="grid gap-2"><Label for="pendidikan_terakhir_ibu" class="text-sm font-medium text-black">{{ labels.pendidikan_terakhir_ibu }}</Label><Input id="pendidikan_terakhir_ibu" v-model="form.pendidikan_terakhir_ibu" :class="inp" required /><InputError :message="form.errors.pendidikan_terakhir_ibu" /></div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="pekerjaan_ibu" class="text-sm font-medium text-black">{{ labels.pekerjaan_ibu }}</Label><select id="pekerjaan_ibu" v-model="form.pekerjaan_ibu" :class="sel" required><option value="">Pilih pekerjaan</option><option v-for="item in pekerjaanIbuOptions" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.pekerjaan_ibu" /></div>
                            <div class="grid gap-2"><Label for="penghasilan_ibu" class="text-sm font-medium text-black">{{ labels.penghasilan_ibu }}</Label><select id="penghasilan_ibu" v-model="form.penghasilan_ibu" :class="sel" required><option value="">Pilih penghasilan</option><option v-for="item in penghasilanIbuOptions" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.penghasilan_ibu" /></div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2"><Label for="no_telepon_ibu" class="text-sm font-medium text-black">{{ labels.no_telepon_ibu }}</Label><Input id="no_telepon_ibu" v-model="form.no_telepon_ibu" :class="inp" required /><InputError :message="form.errors.no_telepon_ibu" /></div>
                            <div class="grid gap-2"><Label for="email_ibu" class="text-sm font-medium text-black">{{ labels.email_ibu }}</Label><Input id="email_ibu" type="email" v-model="form.email_ibu" :class="inp" required /><InputError :message="form.errors.email_ibu" /></div>
                        </div>
                        <div class="mt-4 grid gap-2"><Label for="alamat_ibu" class="text-sm font-medium text-black">{{ labels.alamat_ibu }}</Label><textarea id="alamat_ibu" v-model="form.alamat_ibu" class="min-h-20 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]" required /><InputError :message="form.errors.alamat_ibu" /></div>
                    </section>

                    <!-- Keamanan -->
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Keamanan</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div v-for="field in ['password', 'password_confirmation']" :key="field" class="grid gap-2"><Label :for="field" class="text-sm font-medium text-black">{{ labels[field] }}</Label><Input :id="field" type="password" v-model="form[field]" :class="inp" :required="!props.user" autocomplete="new-password" /><InputError :message="form.errors[field]" /></div>
                        </div>
                    </section>

                    <div class="flex justify-end pt-2"><Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button></div>
                </form>

                <!-- Dosen / Karyawan: flat form on paper card -->
                <form v-else @submit.prevent="submit" class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                    <div class="space-y-4">
                        <template v-for="field in common" :key="field">
                            <div v-if="field === 'tanggal_lahir'" class="grid gap-2"><Label :for="field" class="text-sm font-medium text-black">{{ labels[field] }}</Label><DatePicker :id="field" v-model="form[field]" placeholder="Pilih tanggal lahir" /><InputError :message="form.errors[field]" /></div>
                            <div v-else class="grid gap-2"><Label :for="field" class="text-sm font-medium text-black">{{ labels[field] }}</Label><Input :id="field" :type="field.includes('email') ? 'email' : 'text'" v-model="form[field]" :class="inp" required /><InputError :message="form.errors[field]" /></div>
                        </template>
                        <div class="grid gap-2"><Label class="text-sm font-medium text-black">Jenis Kelamin</Label><div class="flex gap-6"><label v-for="gender in ['Laki-laki', 'Perempuan']" :key="gender" class="flex items-center gap-2 text-[15px] text-[#31302e]"><input v-model="form.jenis_kelamin" type="radio" :value="gender" class="accent-[#0075de]" required /> {{ gender }}</label></div><InputError :message="form.errors.jenis_kelamin" /></div>
                        <div class="grid gap-2"><Label for="agama" class="text-sm font-medium text-black">Agama</Label><select id="agama" v-model="form.agama" :class="sel" required><option value="">Pilih agama</option><option v-for="item in agama" :key="item" :value="item">{{ item }}</option></select><InputError :message="form.errors.agama" /></div>
                        <div v-for="field in roleFields" :key="field" class="grid gap-2"><Label :for="field" class="text-sm font-medium text-black">{{ labels[field] }}</Label><select v-if="field === 'status'" :id="field" v-model="form[field]" :class="sel" required><option value="">Pilih status</option><option v-for="item in statuses" :key="item" :value="item">{{ item }}</option></select><select v-else-if="field === 'prodi_id'" :id="field" v-model="form[field]" :class="sel" required><option value="">Pilih program studi</option><optgroup v-for="[fakultas, prodiList] in groupedProgramStudi" :key="fakultas" :label="fakultas"><option v-for="item in prodiList" :key="item.id" :value="item.id">{{ item.jenjang }} - {{ item.nama_prodi }}</option></optgroup></select><template v-else-if="field === 'dosen_wali_id'"><div ref="dosenWaliRef" class="relative"><button :id="field" type="button" class="flex h-10 w-full items-center justify-between rounded-[4px] border border-[#dddddd] bg-white px-3 text-left text-[15px] text-black" role="combobox" :aria-expanded="dosenWaliOpen" @click="toggleDosenWali" @keydown="handleDosenWaliKeydown"><span>{{ selectedDosen?.name ?? 'Pilih dosen wali' }}</span><ChevronDown class="size-4 shrink-0 text-[#a39e98] transition-transform" :class="dosenWaliOpen ? 'rotate-180' : ''" /></button><div v-if="dosenWaliOpen" class="absolute z-10 mt-1 w-full rounded-xl border border-[#e6e6e6] bg-white p-2 shadow-[0_23px_52px_rgba(0,0,0,0.05)]" role="listbox" id="dosen-wali-options"><Input v-model="search" placeholder="Cari dosen wali" :class="inp" autofocus /><div class="mt-1 max-h-48 overflow-y-auto"><button v-for="dosen in filteredDosen" :key="dosen.id" type="button" class="block w-full rounded-lg px-2 py-2 text-left text-[15px] hover:bg-[#f6f5f4]" @click="selectDosenWali(dosen.id)">{{ dosen.name }}</button><p v-if="filteredDosen.length === 0" class="px-2 py-2 text-sm text-[#615d59]">Dosen tidak ditemukan</p></div></div><input :id="`${field}-value`" v-model="form[field]" type="hidden" required /></div></template><Input v-else :id="field" v-model="form[field]" :class="inp" :type="['angkatan', 'semester'].includes(field) ? 'number' : 'text'" required /><InputError :message="form.errors[field]" /></div>
                        <div class="grid gap-2"><Label for="alamat" class="text-sm font-medium text-black">{{ labels.alamat }}</Label><textarea id="alamat" v-model="form.alamat" class="min-h-24 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]" required /><InputError :message="form.errors.alamat" /></div>
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            <div v-for="field in ['password', 'password_confirmation']" :key="field" class="grid gap-2"><Label :for="field" class="text-sm font-medium text-black">{{ labels[field] }}</Label><Input :id="field" type="password" v-model="form[field]" :class="inp" :required="!props.user" autocomplete="new-password" /><InputError :message="form.errors[field]" /></div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end"><Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button></div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
