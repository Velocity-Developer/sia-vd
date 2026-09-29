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
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();
const props = defineProps<{ infoKuliah: { id: number; information: string; file: string } | null }>();
const form = useForm<{ information: string; file: File | null }>({ information: props.infoKuliah?.information ?? '', file: null });
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const pickFile = () => fileInput.value?.click();
const setFile = (file: File | null) => {
    form.file = file;
};
const onFile = (event: Event) => {
    setFile((event.target as HTMLInputElement).files?.[0] ?? null);
};
const onDrop = (event: DragEvent) => {
    isDragging.value = false;
    setFile(event.dataTransfer?.files?.[0] ?? null);
};
const removeFile = () => {
    form.file = null;
    if (fileInput.value) fileInput.value.value = '';
};
const submit = () => {
    const options = { forceFormData: true };
    if (props.infoKuliah) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('admin.info-kuliah.update', props.infoKuliah.id), options);
    } else {
        form.post(route('admin.info-kuliah.store'), options);
    }
};
</script>

<template>
    <Head :title="`${props.infoKuliah ? 'Edit' : 'Tambah'} Info Kuliah`" />
    <AppLayout :breadcrumbs="[{ title: 'Info Kuliah', href: route('admin.info-kuliah.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.infoKuliah ? 'Edit' : 'Tambah' }} Info Kuliah</h1>
                        <p class="deskripsi-halaman">Lengkapi informasi dan berkas perkuliahan.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.info-kuliah.index')">Kembali</Link></Button>
                </div>
                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <form class="flex flex-col gap-6" enctype="multipart/form-data" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Info Kuliah</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="information" class="label-isian">Informasi</Label>
                            <textarea
                                id="information"
                                v-model="form.information"
                                placeholder="Tulis informasi perkuliahan"
                                class="isian isian-area"
                                required
                            />
                            <InputError :message="form.errors.information" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="file" class="label-isian">File</Label>
                            <input
                                id="file"
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                :required="!props.infoKuliah && !form.file"
                                @change="onFile"
                            />
                            <Attachment
                                state="idle"
                                class="h-16 w-full cursor-pointer rounded-lg border-2 border-dashed border-[#d8d5d2] bg-[#f6f5f4] transition-colors hover:border-[#0075de] hover:bg-white dark:border-border dark:bg-background dark:hover:bg-accent"
                                :class="isDragging ? 'border-[#0075de] bg-white dark:bg-accent' : ''"
                                @click="pickFile"
                                @dragover.prevent="isDragging = true"
                                @dragleave="isDragging = false"
                                @drop.prevent="onDrop"
                            >
                                <AttachmentMedia class="bg-white text-[#0075de] dark:bg-card"><Upload class="size-4" /></AttachmentMedia>
                                <AttachmentContent
                                    ><AttachmentTitle>{{ isDragging ? 'Lepaskan file di sini' : 'Klik atau seret file ke sini' }}</AttachmentTitle
                                    ><AttachmentDescription
                                        >Pilih satu file<span v-if="props.infoKuliah">
                                            — file baru menggantikan file lama</span
                                        ></AttachmentDescription
                                    ></AttachmentContent
                                >
                            </Attachment>
                            <Attachment v-if="form.file" state="done" class="w-full"
                                ><AttachmentMedia><FileText /></AttachmentMedia
                                ><AttachmentContent
                                    ><AttachmentTitle>{{ form.file.name }}</AttachmentTitle
                                    ><AttachmentDescription>File baru</AttachmentDescription></AttachmentContent
                                ><AttachmentActions
                                    ><AttachmentAction aria-label="Hapus berkas" @click.stop="removeFile"><X /></AttachmentAction></AttachmentActions
                            ></Attachment>
                            <p v-else-if="props.infoKuliah" class="teks-bantu">File saat ini: {{ props.infoKuliah.file }}</p>
                            <InputError :message="form.errors.file" />
                        </div>
                    </section>
                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
