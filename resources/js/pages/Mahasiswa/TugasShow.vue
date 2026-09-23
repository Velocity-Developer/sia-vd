<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentMedia,
    AttachmentTitle,
} from '@/components/ui/attachment';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type MataKuliah = {
    nama_matkul: string;
};

type Kelas = {
    id: number;
    kode_kelas: string;
    mataKuliah?: MataKuliah | null;
    mata_kuliah?: MataKuliah | null;
};

type Tugas = {
    id: number;
    judul_tugas: string;
    file?: string[] | string | null;
    tenggat_waktu?: string | null;
    catatan?: string | null;
    uploader?: { name: string } | null;
    kelasKuliah?: Kelas | null;
    kelas_kuliah?: Kelas | null;
};

type Submission = {
    id: number;
    file_jawaban: string[] | string | null;
    nilai?: string | null;
} | null;

const props = defineProps<{ tugas: Tugas; submission: Submission }>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const kelas = (): Kelas | null => props.tugas.kelasKuliah ?? props.tugas.kelas_kuliah ?? null;

const kembaliHref = (): string => (kelas() ? route('mahasiswa.jadwal-kuliah.show', kelas()!.id) : route('mahasiswa.jadwal-kuliah'));

const files = (value?: string[] | string | null): string[] => {
    if (Array.isArray(value)) {
        return value.filter((file): file is string => typeof file === 'string' && file !== '');
    }

    if (typeof value === 'string' && value !== '') {
        try {
            const decoded = JSON.parse(value);

            if (Array.isArray(decoded)) {
                return decoded.filter((file): file is string => typeof file === 'string' && file !== '');
            }
        } catch {
            return [value];
        }

        return [value];
    }

    return [];
};

const fileName = (path: string) => path.split('/').pop() ?? path;

const formatTenggat = (value: string | null | undefined): string => {
    if (!value) return '-';

    const [date, time] = value.replace('T', ' ').split(' ');

    if (!date) return String(value);

    const [year, month, day] = date.split('-');
    const [hour = '00', minute = '00'] = (time ?? '').split(':');

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return `${day} ${months[Number(month) - 1]} ${year}, ${hour}:${minute}`;
};

const lewatTenggat = computed<boolean>(() => {
    if (!props.tugas.tenggat_waktu) return false;

    return new Date(props.tugas.tenggat_waktu).getTime() < Date.now();
});

const sudahDinilai = computed(() => props.submission?.nilai !== null && props.submission?.nilai !== undefined);
const form = useForm<{ file_jawaban: File[] }>({ file_jawaban: [] });

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const tambahFile = (picked: File[]) => {
    const seen = new Set(form.file_jawaban.map((file) => `${file.name}-${file.size}-${file.lastModified}`));

    for (const file of picked) {
        const key = `${file.name}-${file.size}-${file.lastModified}`;

        if (!seen.has(key)) {
            seen.add(key);
            form.file_jawaban.push(file);
        }
    }
};

const onFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    tambahFile(Array.from(input.files ?? []));
    input.value = '';
};

const pickFiles = () => {
    if (lewatTenggat.value) return;

    fileInput.value?.click();
};

const onDrop = (event: DragEvent) => {
    isDragging.value = false;

    if (lewatTenggat.value) return;

    tambahFile(Array.from(event.dataTransfer?.files ?? []));
};

const removeFile = (index: number) => {
    form.file_jawaban = form.file_jawaban.filter((_, i) => i !== index);
};

const formatSize = (bytes: number) => {
    if (!bytes) return '0 KB';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const submit = () => {
    if (lewatTenggat.value) return;

    form.post(route('mahasiswa.tugas.pengumpulan.store', props.tugas.id), { forceFormData: true });
};
</script>

<template>
    <Head :title="props.tugas.judul_tugas" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Detail Kelas', href: kembaliHref() },
            { title: props.tugas.judul_tugas, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-bold text-black">{{ props.tugas.judul_tugas }}</h1>
                        <p class="text-sm text-[#615d59]">Detail tugas perkuliahan.</p>
                    </div>
                    <Link :href="kembaliHref()" class="rounded-lg border border-[#e6e6e6] bg-white px-4 py-2 text-sm font-medium text-black">
                        Kembali
                    </Link>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-sm"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00] shadow-sm"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-[#a39e98]">Kelas</dt>
                            <dd class="font-medium">
                                {{ kelas()?.kode_kelas ?? '-' }} —
                                {{ kelas()?.mataKuliah?.nama_matkul ?? kelas()?.mata_kuliah?.nama_matkul ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Tenggat</dt>
                            <dd class="font-medium">{{ formatTenggat(props.tugas.tenggat_waktu) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Diunggah oleh</dt>
                            <dd class="font-medium">{{ props.tugas.uploader?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Catatan</h2>
                        <p class="mt-2 whitespace-pre-line text-[15px] text-[#31302e]">
                            {{ props.tugas.catatan || '-' }}
                        </p>
                    </div>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">File Tugas</h2>
                        <div v-if="files(props.tugas.file).length" class="mt-3 space-y-2">
                            <a
                                v-for="(path, fileIndex) in files(props.tugas.file)"
                                :key="path"
                                :href="route('berkas.tugas', [props.tugas.id, fileIndex])"
                                target="_blank"
                                class="flex items-center justify-between rounded-lg border border-[#e6e6e6] px-3 py-2 text-sm text-[#0075de] transition hover:border-[#0075de] hover:bg-[#f8fbff]"
                            >
                                {{ fileName(path) }}
                            </a>
                        </div>
                        <p v-else class="mt-2 text-sm text-[#615d59]">Tidak ada file.</p>
                    </div>
                </section>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pengumpulan Jawaban</h2>

                    <div v-if="props.submission" class="mt-3 space-y-1 text-sm text-[#615d59]">
                        <p>
                            Jawaban tersimpan:
                            <a
                                v-for="(file, fileIndex) in files(props.submission.file_jawaban)"
                                :key="file"
                                :href="route('berkas.pengumpulan', [props.submission.id, fileIndex])"
                                target="_blank"
                                rel="noopener"
                                class="mr-2 text-[#0075de] hover:underline"
                                >{{ fileName(file) }}</a
                            >
                        </p>
                        <p v-if="props.submission.nilai">Nilai {{ props.submission.nilai }}</p>
                    </div>

                    <p v-if="lewatTenggat" class="mt-4 rounded-lg border border-[#e6e6e6] bg-[#fafafa] px-4 py-3 text-sm text-[#dd5b00]">
                        Tenggat waktu telah berakhir. Jawaban tidak dapat diunggah lagi.
                    </p>
                    <p v-else-if="sudahDinilai" class="mt-4 rounded-lg border border-[#e6e6e6] bg-[#fafafa] px-4 py-3 text-sm text-[#615d59]">
                        Jawaban sudah dinilai dosen dan tidak dapat diganti lagi.
                    </p>

                    <form v-else class="mt-4 space-y-3" @submit.prevent="submit">
                        <input ref="fileInput" type="file" multiple class="hidden" @change="onFiles" />
                        <Attachment
                            state="idle"
                            class="h-16 w-full cursor-pointer rounded-md border-2 border-dashed border-[#dddddd] bg-[#fafafa] transition-colors hover:border-[#b8cde3] hover:bg-white"
                            :class="isDragging ? 'border-[#0075de] bg-white' : ''"
                            @click="pickFiles"
                            @dragover.prevent="isDragging = true"
                            @dragleave="isDragging = false"
                            @drop.prevent="onDrop"
                        >
                            <AttachmentMedia class="bg-white text-[#0075de]">
                                <Upload class="size-4" />
                            </AttachmentMedia>
                            <AttachmentContent>
                                <AttachmentTitle>{{ isDragging ? 'Lepaskan file di sini' : 'Klik atau seret file jawaban ke sini' }}</AttachmentTitle>
                                <AttachmentDescription>
                                    bisa pilih lebih dari 1 file, maks. 10 MB per file
                                    <span v-if="form.file_jawaban.length"> — {{ form.file_jawaban.length }} file dipilih</span>
                                </AttachmentDescription>
                            </AttachmentContent>
                        </Attachment>

                        <div v-if="form.file_jawaban.length" class="grid w-full grid-cols-1 gap-2 py-1 md:grid-cols-2">
                            <Attachment
                                v-for="(file, index) in form.file_jawaban"
                                :key="`new-${file.name}-${file.size}-${index}`"
                                state="done"
                                class="w-full"
                            >
                                <AttachmentMedia>
                                    <FileText />
                                </AttachmentMedia>
                                <AttachmentContent>
                                    <AttachmentTitle>{{ file.name }}</AttachmentTitle>
                                    <AttachmentDescription>{{ formatSize(file.size) }} — baru</AttachmentDescription>
                                </AttachmentContent>
                                <AttachmentActions>
                                    <AttachmentAction aria-label="Hapus berkas" @click="removeFile(index)">
                                        <X />
                                    </AttachmentAction>
                                </AttachmentActions>
                            </Attachment>
                        </div>

                        <InputError :message="form.errors.file_jawaban" />

                        <Button
                            type="submit"
                            class="rounded-lg bg-[#0075de] px-4 py-2 text-sm font-medium text-white hover:bg-[#005bab]"
                            :disabled="form.processing"
                        >
                            {{ props.submission ? 'Ganti Jawaban' : 'Kirim Jawaban' }}
                        </Button>
                    </form>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
