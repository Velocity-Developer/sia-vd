<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    title: string;
    type: string;
    user: Record<string, any>;
}>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    if (typeof val === 'string') {
        // Hanya potong bila string adalah ISO datetime (YYYY-MM-DDTHH:MM:SS), bukan teks yang kebetulan mengandung huruf T.
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 10);
        return val;
    }
    return String(val);
};

const isMahasiswa = props.type === 'mahasiswa';
const isDosen = props.type === 'dosen';

const detailTitle = isMahasiswa ? 'Detail Mahasiswa' : isDosen ? 'Detail Dosen' : 'Detail Karyawan';
const detailSubtitle = isMahasiswa
    ? 'Ringkasan data akun, pribadi, akademik, dan orang tua mahasiswa.'
    : isDosen
      ? 'Ringkasan data akun, pribadi, dan akademik dosen beserta home base prodi.'
      : 'Ringkasan data akun dan profil karyawan.';

const akun = [
    { label: 'Nama', key: 'name' },
    { label: 'Username', key: 'username' },
    { label: 'Email', key: 'email' },
    { label: 'Role', key: 'role_name' },
];

const pribadi = [
    { label: 'Tempat Lahir', key: 'tempat_lahir' },
    { label: 'Tanggal Lahir', key: 'tanggal_lahir' },
    { label: 'Jenis Kelamin', key: 'jenis_kelamin' },
    { label: 'Agama', key: 'agama' },
    { label: 'No Telepon', key: 'no_telepon' },
    { label: 'Kewarganegaraan', key: 'kewarganegaraan' },
    { label: 'Alamat', key: 'alamat' },
];

const akademik = [
    { label: 'NIM', key: 'nim' },
    { label: 'Angkatan', key: 'angkatan' },
    { label: 'Semester', key: 'semester' },
    { label: 'Status', key: 'status' },
    { label: 'Program Studi', key: 'prodi_name' },
    { label: 'Jenjang', key: 'prodi_jenjang' },
    { label: 'Fakultas', key: 'fakultas_name' },
    { label: 'Dosen Wali', key: 'dosen_wali_name' },
    { label: 'Sekolah Asal', key: 'sekolah_asal' },
    { label: 'NISN', key: 'nisn' },
    { label: 'Email Alternatif', key: 'email_alternatif' },
];

const akademikDosen = [
    { label: 'NIDN', key: 'nidn' },
    { label: 'Jabatan Fungsional', key: 'jabatan_fungsional' },
    { label: 'Pendidikan Terakhir', key: 'pendidikan_terakhir' },
    { label: 'Status Kepegawaian', key: 'status_kepegawaian' },
    { label: 'Program Studi', key: 'prodi_name' },
    { label: 'Jenjang', key: 'prodi_jenjang' },
    { label: 'Fakultas', key: 'fakultas_name' },
    { label: 'Kode Prodi', key: 'prodi_kode' },
    { label: 'Kode Fakultas', key: 'fakultas_kode' },
];

const identitasKaryawan = [{ label: 'Nomor Induk', key: 'nomor_induk' }];

const ayah = [
    { label: 'Nama Ayah Kandung', key: 'nama_ayah_kandung' },
    { label: 'Tanggal Lahir Ayah', key: 'tanggal_lahir_ayah' },
    { label: 'Pendidikan Terakhir Ayah', key: 'pendidikan_terakhir_ayah' },
    { label: 'Pekerjaan Ayah', key: 'pekerjaan_ayah' },
    { label: 'Penghasilan Ayah', key: 'penghasilan_ayah' },
    { label: 'No Telepon Ayah', key: 'no_telepon_ayah' },
    { label: 'Email Ayah', key: 'email_ayah' },
    { label: 'Alamat Ayah', key: 'alamat_ayah' },
];

const ibu = [
    { label: 'Nama Ibu Kandung', key: 'nama_ibu_kandung' },
    { label: 'Tanggal Lahir Ibu', key: 'tanggal_lahir_ibu' },
    { label: 'Pendidikan Terakhir Ibu', key: 'pendidikan_terakhir_ibu' },
    { label: 'Pekerjaan Ibu', key: 'pekerjaan_ibu' },
    { label: 'Penghasilan Ibu', key: 'penghasilan_ibu' },
    { label: 'No Telepon Ibu', key: 'no_telepon_ibu' },
    { label: 'Email Ibu', key: 'email_ibu' },
    { label: 'Alamat Ibu', key: 'alamat_ibu' },
];
</script>

<template>
    <Head :title="props.title" />
    <AppLayout :breadcrumbs="[{ title: detailTitle, href: '#' }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <!-- Header on paper -->
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ props.title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">{{ detailSubtitle }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route(`admin.users.${props.type}`)"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route(`admin.users.${props.type}.edit`, props.user.id)"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <!-- Akun — feature-card on paper -->
                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Akun</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="f in akun" :key="f.key" class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                            <dd class="break-all text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Data Pribadi -->
                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Pribadi</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="f in pribadi" :key="f.key" class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                        </div>
                    </dl>
                </section>

                <template v-if="isMahasiswa">
                    <!-- Data Akademik -->
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Akademik</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in akademik" :key="f.key" class="space-y-1">
                                <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                                <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <!-- Ayah & Ibu — 2-up on desktop -->
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section
                            class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                        >
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Ayah</h2>
                            <dl class="mt-4 grid gap-4">
                                <div v-for="f in ayah" :key="f.key" class="space-y-1">
                                    <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                                    <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                                </div>
                            </dl>
                        </section>
                        <section
                            class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                        >
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Ibu</h2>
                            <dl class="mt-4 grid gap-4">
                                <div v-for="f in ibu" :key="f.key" class="space-y-1">
                                    <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                                    <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </template>

                <template v-else-if="isDosen">
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Akademik Dosen</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in akademikDosen" :key="f.key" class="space-y-1">
                                <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                                <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                            </div>
                        </dl>
                    </section>
                </template>

                <template v-else>
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Identitas Karyawan</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in identitasKaryawan" :key="f.key" class="space-y-1">
                                <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                                <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.user[f.key]) }}</dd>
                            </div>
                        </dl>
                    </section>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
