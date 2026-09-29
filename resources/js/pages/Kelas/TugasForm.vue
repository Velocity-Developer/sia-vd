<script setup lang="ts">
import DateTimePicker from '@/components/DateTimePicker.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ peran: Peran; kelasKuliah: Record<string, any>; tugas: Record<string, any> | null }>();
const rute = rutePeran(props.peran);

const title = `${props.tugas ? 'Edit' : 'Tambah'} Tugas`;
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

const toDatetimeLocal = (value: unknown): string => {
    if (typeof value !== 'string' || value === '') return '';
    return value.replace(' ', 'T').slice(0, 16);
};

const existingFiles = computed<string[]>(() => {
    const f = props.tugas?.file;
    if (Array.isArray(f)) return f.filter((v): v is string => typeof v === 'string' && v !== '');
    if (typeof f === 'string' && f !== '') {
        try {
            const d = JSON.parse(f);
            if (Array.isArray(d)) return d.filter((v): v is string => typeof v === 'string' && v !== '');
        } catch {
            return [f];
        }
        return [f];
    }
    return [];
});

const fileLabel = (path: string) => path.split('/').pop() ?? path;

const form = useForm({
    judul_tugas: props.tugas?.judul_tugas ?? '',
    tenggat_waktu: toDatetimeLocal(props.tugas?.tenggat_waktu),
    file: [] as File[],
    kept_files: existingFiles.value,
    catatan: props.tugas?.catatan ?? '',
});

const onFiles = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const picked = Array.from(input.files ?? []);
    const seen = new Set(form.file.map((f) => `${f.name}-${f.size}-${f.lastModified}`));
    for (const f of picked) {
        const key = `${f.name}-${f.size}-${f.lastModified}`;
        if (!seen.has(key)) {
            seen.add(key);
            form.file.push(f);
        }
    }
    input.value = '';
};

const removeNew = (index: number) => {
    form.file = form.file.filter((_, i) => i !== index);
};

const removeKept = (path: string) => {
    form.kept_files = form.kept_files.filter((v) => v !== path);
};

const formatSize = (bytes: number) => {
    if (!bytes) return '0 KB';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const fileInput = ref<HTMLInputElement | null>(null);

const pickFiles = () => fileInput.value?.click();

const isDragging = ref(false);

const onDrop = (e: DragEvent) => {
    isDragging.value = false;
    const dropped = Array.from(e.dataTransfer?.files ?? []);
    if (!dropped.length) return;
    const seen = new Set(form.file.map((f) => `${f.name}-${f.size}-${f.lastModified}`));
    for (const f of dropped) {
        const key = `${f.name}-${f.size}-${f.lastModified}`;
        if (!seen.has(key)) {
            seen.add(key);
            form.file.push(f);
        }
    }
};

const fileCount = computed(() => form.kept_files.length + form.file.length);

const submit = () => {
    if (props.tugas) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(rute('kelas-kuliah.tugas.update', [props.kelasKuliah.id, props.tugas.id]), {
            forceFormData: true,
        });
    } else {
        form.post(rute('kelas-kuliah.tugas.store', props.kelasKuliah.id), { forceFormData: true });
    }
};
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">
                            Kelas {{ props.kelasKuliah?.kode_kelas }} — lengkapi judul, tenggat waktu, berkas, dan catatan.
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <form class="kartu p-6" @submit.prevent="submit">
                    <h2 class="judul-bagian">Data Tugas</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="judul_tugas" class="label-isian">Judul Tugas</Label>
                            <Input id="judul_tugas" v-model="form.judul_tugas" type="text" placeholder="cth. Tugas 1 Basis Data" required />
                            <InputError :message="form.errors.judul_tugas" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tenggat_waktu" class="label-isian">Tenggat Waktu</Label>
                            <DateTimePicker v-model="form.tenggat_waktu" :min-date="today" placeholder="Pilih tenggat waktu" />
                            <InputError :message="form.errors.tenggat_waktu" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="file" class="label-isian">File </Label>
                        <input id="file" ref="fileInput" type="file" multiple class="hidden" @change="onFiles" />
                        <Attachment
                            state="idle"
                            class="h-16 w-full cursor-pointer rounded-lg border-2 border-dashed border-[#d8d5d2] bg-[#f6f5f4] transition-colors hover:border-[#0075de]/40 hover:bg-white dark:border-border dark:bg-background"
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
                                <AttachmentTitle>{{ isDragging ? 'Lepaskan file di sini' : 'Klik atau seret file ke sini' }}</AttachmentTitle>
                                <AttachmentDescription>
                                    bisa pilih lebih dari 1 file, maks. 10 MB per file
                                    <span v-if="fileCount"> — {{ fileCount }} file dipilih</span>
                                </AttachmentDescription>
                            </AttachmentContent>
                        </Attachment>
                        <div v-if="fileCount" class="grid w-full grid-cols-1 gap-2 py-1 md:grid-cols-2">
                            <Attachment v-for="path in form.kept_files" :key="`kept-${path}`" state="done" class="w-full">
                                <AttachmentMedia>
                                    <FileText />
                                </AttachmentMedia>
                                <AttachmentContent>
                                    <AttachmentTitle>{{ fileLabel(path) }}</AttachmentTitle>
                                    <AttachmentDescription>tersimpan</AttachmentDescription>
                                </AttachmentContent>
                                <AttachmentActions>
                                    <AttachmentAction aria-label="Hapus berkas" @click="removeKept(path)">
                                        <X />
                                    </AttachmentAction>
                                </AttachmentActions>
                            </Attachment>
                            <Attachment v-for="(f, i) in form.file" :key="`new-${f.name}-${f.size}-${i}`" state="done" class="w-full">
                                <AttachmentMedia>
                                    <FileText />
                                </AttachmentMedia>
                                <AttachmentContent>
                                    <AttachmentTitle>{{ f.name }}</AttachmentTitle>
                                    <AttachmentDescription>{{ formatSize(f.size) }} — baru</AttachmentDescription>
                                </AttachmentContent>
                                <AttachmentActions>
                                    <AttachmentAction aria-label="Hapus berkas" @click="removeNew(i)">
                                        <X />
                                    </AttachmentAction>
                                </AttachmentActions>
                            </Attachment>
                        </div>
                        <p v-else-if="props.tugas" class="teks-bantu">Belum ada berkas tersimpan.</p>
                        <InputError :message="form.errors.file" />
                        <InputError v-for="(msg, key) in form.errors" :key="key" :message="String(key).startsWith('file.') ? String(msg) : ''" />
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="catatan" class="label-isian">Catatan </Label>
                        <textarea id="catatan" v-model="form.catatan" placeholder="Catatan tambahan untuk tugas ini" class="isian isian-area" />
                        <InputError :message="form.errors.catatan" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
