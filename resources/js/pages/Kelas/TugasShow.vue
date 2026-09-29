<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { ref } from 'vue';

type Submission = {
    id: number;
    nilai?: string | number | null;
    submitted_at?: string | null;
    file_jawaban?: string[] | null;
    mahasiswa?: {
        nim: string;
        user?: { name: string } | null;
    } | null;
};

type Tugas = {
    id: number;
    judul_tugas: string;
    tenggat_waktu?: string | null;
    catatan?: string | null;
    file?: string[] | null;
    uploader?: { name: string } | null;
    pengumpulan_tugas?: Submission[];
};

type KelasKuliah = {
    id: number;
    kode_kelas: string;
    mataKuliah?: { nama_matkul: string } | null;
    mata_kuliah?: { nama_matkul: string } | null;
};

const props = defineProps<{ peran: Peran; kelasKuliah: KelasKuliah; tugas: Tugas; nilaiTerkunci: string | null }>();
const rute = rutePeran(props.peran);
const page = usePage<{ flash?: { success?: string; error?: string } }>();
const editing = ref<number | null>(null);
const grade = ref<string | number>('');

const formatDate = (value: string | null | undefined): string => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};

const files = (value: string[] | null | undefined): string[] => value ?? [];
const fileName = (path: string): string => path.split('/').pop() ?? path;

const editGrade = (submission: Submission) => {
    editing.value = submission.id;
    grade.value = submission.nilai ?? '';
};

const saveGrade = (submission: Submission) => {
    router.put(
        rute('kelas-kuliah.tugas.pengumpulan.nilai', [props.kelasKuliah.id, props.tugas.id, submission.id]),
        { nilai: grade.value },
        {
            onSuccess: () => {
                editing.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Detail Tugas ${props.tugas.judul_tugas}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') },
            { title: props.kelasKuliah.kode_kelas, href: rute('kelas-kuliah.show', props.kelasKuliah.id) },
            { title: 'Detail Tugas', href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Tugas</h1>
                        <p class="deskripsi-halaman">Informasi tugas dan daftar mahasiswa yang sudah mengumpulkan jawaban.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Tugas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="teks-bantu">Judul</dt>
                            <dd class="text-sm font-medium text-black">{{ props.tugas.judul_tugas }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Kelas</dt>
                            <dd class="text-sm font-medium text-black">
                                {{ props.kelasKuliah.kode_kelas }} ·
                                {{ (props.kelasKuliah.mataKuliah ?? props.kelasKuliah.mata_kuliah)?.nama_matkul ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Tenggat Waktu</dt>
                            <dd class="text-sm text-[#31302e]">{{ formatDate(props.tugas.tenggat_waktu) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Diunggah Oleh</dt>
                            <dd class="text-sm text-[#31302e]">{{ props.tugas.uploader?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                    <div v-if="props.tugas.catatan" class="mt-4">
                        <p class="teks-bantu">Catatan</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-[#31302e]">{{ props.tugas.catatan }}</p>
                    </div>
                    <ul v-if="files(props.tugas.file).length" class="mt-4 space-y-1 text-sm">
                        <li v-for="(path, fileIndex) in files(props.tugas.file)" :key="path">
                            <a
                                :href="route('berkas.tugas', [props.tugas.id, fileIndex])"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 text-[#0075de] hover:underline"
                                ><Download class="size-4" />{{ fileName(path) }}</a
                            >
                        </li>
                    </ul>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Pengumpulan Jawaban</h2>
                    <div class="tabel-wadah mt-4">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[760px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <th>Dikumpulkan</th>
                                        <th>Jawaban</th>
                                        <th>Nilai</th>
                                        <th class="kolom-aksi">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(submission, index) in props.tugas.pengumpulan_tugas ?? []" :key="submission.id">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <span class="font-medium text-black">{{ submission.mahasiswa?.user?.name ?? '-' }}</span
                                            ><span class="block text-xs text-[#a39e98]">{{ submission.mahasiswa?.nim ?? '-' }}</span>
                                        </td>
                                        <td>{{ formatDate(submission.submitted_at) }}</td>
                                        <td>
                                            <ul class="space-y-1">
                                                <li v-for="(path, fileIndex) in files(submission.file_jawaban)" :key="path">
                                                    <a
                                                        :href="route('berkas.pengumpulan', [submission.id, fileIndex])"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-[#0075de] hover:underline"
                                                        >{{ fileName(path) }}</a
                                                    >
                                                </li>
                                            </ul>
                                        </td>
                                        <td>
                                            <input
                                                v-if="editing === submission.id"
                                                v-model="grade"
                                                type="number"
                                                min="0"
                                                max="100"
                                                aria-label="Nilai"
                                                class="isian w-24"
                                            /><span v-else class="font-semibold tabular-nums text-black">{{ submission.nilai ?? '-' }}</span>
                                        </td>
                                        <td class="kolom-aksi">
                                            <div class="aksi-tabel">
                                                <template v-if="editing === submission.id">
                                                    <Button variant="outline" size="sm" @click="editing = null">Batal</Button>
                                                    <Button size="sm" @click="saveGrade(submission)">Simpan</Button>
                                                </template>
                                                <span v-else-if="props.nilaiTerkunci" class="teks-bantu">Nilai terkunci</span>
                                                <Button v-else variant="outline" size="sm" @click="editGrade(submission)">Ubah Nilai</Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!(props.tugas.pengumpulan_tugas ?? []).length" class="baris-kosong">
                                        <td colspan="6" class="tabel-kosong">Belum ada mahasiswa yang mengumpulkan jawaban.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
