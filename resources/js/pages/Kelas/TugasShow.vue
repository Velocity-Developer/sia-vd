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
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-bold text-black">Detail Tugas</h1>
                        <p class="text-sm text-[#615d59]">Informasi tugas dan daftar mahasiswa yang sudah mengumpulkan jawaban.</p>
                    </div>
                    <Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)"
                        ><Button variant="outline" class="rounded-lg bg-white">Kembali</Button></Link
                    >
                </div>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Tugas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase text-[#a39e98]">Judul</dt>
                            <dd class="text-[15px] font-medium text-black">{{ props.tugas.judul_tugas }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-[#a39e98]">Kelas</dt>
                            <dd class="text-[15px] font-medium text-black">
                                {{ props.kelasKuliah.kode_kelas }} ·
                                {{ (props.kelasKuliah.mataKuliah ?? props.kelasKuliah.mata_kuliah)?.nama_matkul ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-[#a39e98]">Tenggat Waktu</dt>
                            <dd class="text-[15px] text-[#31302e]">{{ formatDate(props.tugas.tenggat_waktu) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-[#a39e98]">Diunggah Oleh</dt>
                            <dd class="text-[15px] text-[#31302e]">{{ props.tugas.uploader?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                    <div v-if="props.tugas.catatan" class="mt-4">
                        <dt class="text-xs uppercase text-[#a39e98]">Catatan</dt>
                        <p class="mt-1 whitespace-pre-line text-sm text-[#31302e]">{{ props.tugas.catatan }}</p>
                    </div>
                    <ul v-if="files(props.tugas.file).length" class="mt-4 space-y-1">
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

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pengumpulan Jawaban</h2>
                    <div v-if="page.props.flash?.success" class="mt-4 rounded-xl border border-[#e6e6e6] px-4 py-3 text-sm text-[#1aae39]">
                        {{ page.props.flash.success }}
                    </div>
                    <div v-if="page.props.flash?.error" class="mt-4 rounded-xl border border-[#e6e6e6] px-4 py-3 text-sm text-[#dd5b00]">
                        {{ page.props.flash.error }}
                    </div>
                    <div class="relative mt-4 overflow-hidden overflow-x-auto rounded-xl border border-[#e6e6e6]">
                        <table class="w-full min-w-[760px] text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Dikumpulkan</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Jawaban</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Nilai</th>
                                    <th class="px-4 py-3 text-right text-xs uppercase text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr
                                    v-for="(submission, index) in props.tugas.pengumpulan_tugas ?? []"
                                    :key="submission.id"
                                    class="hover:bg-[#f6f5f4]/60"
                                >
                                    <td class="px-4 py-3 text-sm text-[#615d59]">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="font-medium text-black">{{ submission.mahasiswa?.user?.name ?? '-' }}</span
                                        ><span class="block text-[#615d59]">{{ submission.mahasiswa?.nim ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ formatDate(submission.submitted_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
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
                                    <td class="px-4 py-3 text-sm">
                                        <input
                                            v-if="editing === submission.id"
                                            v-model="grade"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="h-9 w-24 rounded-lg border border-[#e6e6e6] px-3"
                                        /><span v-else class="font-semibold">{{ submission.nilai ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <template v-if="editing === submission.id"
                                            ><Button
                                                size="sm"
                                                class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                                                @click="saveGrade(submission)"
                                                >Simpan</Button
                                            ><Button size="sm" variant="outline" class="ml-2 rounded-full" @click="editing = null"
                                                >Batal</Button
                                            ></template
                                        ><span v-else-if="props.nilaiTerkunci" class="text-xs text-[#a39e98]">Nilai terkunci</span
                                        ><Button v-else size="sm" variant="outline" class="rounded-full" @click="editGrade(submission)"
                                            >Ubah Nilai</Button
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!(props.tugas.pengumpulan_tugas ?? []).length">
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-[#615d59]">
                                        Belum ada mahasiswa yang mengumpulkan jawaban.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
