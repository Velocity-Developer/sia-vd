<script setup lang="ts">
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentDescription,
    AttachmentMedia,
    AttachmentTitle,
} from '@/components/ui/attachment';
import { FileText, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ peran: Peran;
    kelasKuliah: Record<string, any>;
    materi: Record<string, any> | null;
}>();
const rute = rutePeran(props.peran);

const title = `${props.materi ? 'Edit' : 'Tambah'} Materi`;

const existingFiles = computed<string[]>(() => {
    const f = props.materi?.file;
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
    judul_materi: props.materi?.judul_materi ?? '',
    pertemuan_ke: props.materi?.pertemuan_ke ?? '',
    jenis: props.materi?.jenis ?? 'Materi',
    file: [] as File[],
    kept_files: existingFiles.value,
    catatan: props.materi?.catatan ?? '',
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
    if (props.materi) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(
            rute('kelas-kuliah.materi.update', [props.kelasKuliah.id, props.materi.id]),
            { forceFormData: true },
        );
    } else {
        form.post(rute('kelas-kuliah.materi.store', props.kelasKuliah.id), { forceFormData: true });
    }
};

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const area =
    'min-h-24 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Kelas {{ props.kelasKuliah?.kode_kelas }} — lengkapi judul, pertemuan, berkas, dan catatan.
                        </p>
                    </div>
                    <Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)"
                        ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                    >
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01)]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Materi</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="judul_materi" class="text-sm font-medium text-black">Judul Materi</Label>
                                <Input id="judul_materi" v-model="form.judul_materi" type="text" placeholder="cth. Pengantar Basis Data" :class="inp" required />
                                <InputError :message="form.errors.judul_materi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pertemuan_ke" class="text-sm font-medium text-black">Pertemuan Ke</Label>
                                <Input id="pertemuan_ke" v-model="form.pertemuan_ke" type="number" min="1" max="32" placeholder="cth. 1" :class="inp" required />
                                <InputError :message="form.errors.pertemuan_ke" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jenis" class="text-sm font-medium text-black">Jenis</Label>
                                <select id="jenis" v-model="form.jenis" :class="sel" required>
                                    <option value="Materi">Materi</option>
                                    <option value="Pengumuman">Pengumuman</option>
                                </select>
                                <InputError :message="form.errors.jenis" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="file" class="text-sm font-medium text-black"
                                >File </Label
                            >
                            <input id="file" ref="fileInput" type="file" multiple class="hidden" @change="onFiles" />
                            <Attachment
                                state="idle"
                                class="w-full h-16 cursor-pointer border-2 rounded-md border-dashed border-[#dddddd] bg-[#fafafa] transition-colors hover:border-[#b8cde3] hover:bg-white"
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
                            <p v-else-if="props.materi" class="text-xs text-[#615d59]">Belum ada berkas tersimpan.</p>
                            <InputError :message="form.errors.file" />
                            <InputError v-for="(msg, key) in form.errors" :key="key" :message="String(key).startsWith('file.') ? String(msg) : ''" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="catatan" class="text-sm font-medium text-black">Catatan </Label>
                            <textarea id="catatan" v-model="form.catatan" placeholder="Catatan tambahan untuk materi ini" :class="area" />
                            <InputError :message="form.errors.catatan" />
                        </div>
                    </section>

                    <div class="flex justify-end pt-2">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
