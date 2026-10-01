<script setup lang="ts">
import DialogBukaKunciKrs from '@/components/DialogBukaKunciKrs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_KRS, type RingkasanKrs } from '@/lib/krs';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    title: string;
    type: string;
    user: Record<string, any>;
    bolehKelola: boolean;
    kunciKrs?:
        | (RingkasanKrs & {
              tahun_akademik_id: number;
              tahun_akademik: string;
              terkunci: boolean;
              masa_revisi_berjalan: boolean;
              verifikasi: boolean;
          })
        | null;
}>();

const page = usePage<{ flash?: { success?: string | null; error?: string | null } }>();
const memproses = ref(false);
const aksiVerifikasi = (aksi: 'verifikasi-email' | 'tandai-terverifikasi') => {
    if (aksi === 'tandai-terverifikasi' && !confirm('Tandai email ini terverifikasi tanpa mengeklik tautan?')) return;
    const url = route(`admin.users.${props.type}.${aksi}`, props.user.id);
    const opsi = { preserveScroll: true, onStart: () => (memproses.value = true), onFinish: () => (memproses.value = false) };
    if (aksi === 'verifikasi-email') router.post(url, {}, opsi);
    else router.put(url, {}, opsi);
};

const bukaKunciOpen = ref(false);

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
    { label: 'Semester (tahun aktif)', key: 'semester' },
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
    { label: 'Status', key: 'status' },
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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.title }}</h1>
                        <p class="deskripsi-halaman">{{ detailSubtitle }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"><Link :href="route(`admin.users.${props.type}`)">Kembali</Link></Button>
                        <Button as-child><Link :href="route(`admin.users.${props.type}.edit`, props.user.id)">Edit</Link></Button>
                    </div>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Akun</h2>
                    <div class="mt-4 flex flex-col gap-6 sm:flex-row sm:items-start">
                        <div
                            class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] dark:border-border dark:bg-muted"
                        >
                            <img
                                v-if="props.user.foto_url"
                                :src="props.user.foto_url"
                                :alt="`Foto ${props.user.name}`"
                                class="size-full object-cover"
                            />
                            <UserRound v-else class="size-12 text-[#a39e98]" aria-label="Belum ada foto" />
                        </div>
                        <dl class="grid flex-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in akun" :key="f.key" class="space-y-1">
                                <dt class="teks-bantu">{{ f.label }}</dt>
                                <dd class="break-all text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="teks-bantu">Verifikasi Email</dt>
                                <dd class="text-sm font-medium">
                                    <span v-if="props.user.email_verified_at" class="text-[#1aae39]"
                                        >Terverifikasi {{ v(props.user.email_verified_at) }}</span
                                    >
                                    <span v-else class="text-[#dd5b00]">Belum terverifikasi</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div v-if="!props.user.email_verified_at && props.bolehKelola" class="mt-4 flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" :disabled="memproses" @click="aksiVerifikasi('verifikasi-email')"
                            >Kirim Ulang Tautan Verifikasi</Button
                        >
                        <Button variant="outline" size="sm" :disabled="memproses" @click="aksiVerifikasi('tandai-terverifikasi')"
                            >Tandai Terverifikasi</Button
                        >
                    </div>
                    <p v-if="page.props.flash?.success" class="alert-sukses mt-4" role="status">{{ page.props.flash.success }}</p>
                    <p v-if="page.props.flash?.error" class="alert-gagal mt-4" role="alert">{{ page.props.flash.error }}</p>
                </section>

                <!-- Data Pribadi -->
                <section class="kartu p-6">
                    <h2 class="judul-bagian">Data Pribadi</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="f in pribadi" :key="f.key" class="space-y-1">
                            <dt class="teks-bantu">{{ f.label }}</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                        </div>
                    </dl>
                </section>

                <template v-if="isMahasiswa">
                    <!-- Data Akademik -->
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Akademik</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in akademik" :key="f.key" class="space-y-1">
                                <dt class="teks-bantu">{{ f.label }}</dt>
                                <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="props.kunciKrs" class="kartu p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="judul-bagian">KRS {{ props.kunciKrs.tahun_akademik }}</h2>
                            <span
                                v-if="props.kunciKrs.verifikasi && props.kunciKrs.status"
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="STATUS_KRS[props.kunciKrs.status].kelas"
                                >{{ STATUS_KRS[props.kunciKrs.status].label }}</span
                            >
                        </div>
                        <p class="mt-2 text-sm">
                            <template v-if="!props.kunciKrs.status">KRS belum disimpan dan periode KRS sudah berakhir.</template>
                            <template v-else-if="!props.kunciKrs.terkunci">
                                Kunci KRS sedang dibuka sampai {{ formatTanggal(props.kunciKrs.batas_revisi, false) }}.
                            </template>
                            <template v-else>
                                KRS terkunci<template v-if="props.kunciKrs.disimpan_pada">
                                    (disimpan {{ formatTanggal(props.kunciKrs.disimpan_pada, false) }})</template
                                >.
                            </template>
                            <template v-if="props.kunciKrs.terkunci">Buka kuncinya bila mahasiswa perlu memperbaiki pilihan kelas sendiri.</template>
                        </p>
                        <p v-if="props.kunciKrs.status === 'perlu_revisi' && props.kunciKrs.catatan_revisi" class="teks-bantu mt-1">
                            Catatan: {{ props.kunciKrs.catatan_revisi }}
                        </p>
                        <div v-if="props.bolehKelola && props.kunciKrs.terkunci" class="mt-4">
                            <Button variant="outline" size="sm" :disabled="memproses" @click="bukaKunciOpen = true">Buka Kunci KRS</Button>
                        </div>
                        <DialogBukaKunciKrs
                            v-model:open="bukaKunciOpen"
                            judul="Buka Kunci KRS"
                            :nama="props.user.name"
                            :url="route('admin.users.mahasiswa.buka-kunci-krs', props.user.id)"
                            metode="delete"
                            :masa-revisi-berjalan="props.kunciKrs.masa_revisi_berjalan"
                            :batas-revisi="props.kunciKrs.batas_revisi"
                        />
                    </section>

                    <!-- Ayah & Ibu — 2-up on desktop -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <section class="kartu p-6">
                            <h2 class="judul-bagian">Data Ayah</h2>
                            <dl class="mt-4 grid gap-4">
                                <div v-for="f in ayah" :key="f.key" class="space-y-1">
                                    <dt class="teks-bantu">{{ f.label }}</dt>
                                    <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                                </div>
                            </dl>
                        </section>
                        <section class="kartu p-6">
                            <h2 class="judul-bagian">Data Ibu</h2>
                            <dl class="mt-4 grid gap-4">
                                <div v-for="f in ibu" :key="f.key" class="space-y-1">
                                    <dt class="teks-bantu">{{ f.label }}</dt>
                                    <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </template>

                <template v-else-if="isDosen">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Akademik Dosen</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in akademikDosen" :key="f.key" class="space-y-1">
                                <dt class="teks-bantu">{{ f.label }}</dt>
                                <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                            </div>
                        </dl>
                    </section>
                </template>

                <template v-else>
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Identitas Karyawan</h2>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div v-for="f in identitasKaryawan" :key="f.key" class="space-y-1">
                                <dt class="teks-bantu">{{ f.label }}</dt>
                                <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.user[f.key]) }}</dd>
                            </div>
                        </dl>
                    </section>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
