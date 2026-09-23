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

const area =
    'min-h-32 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] dark:border-gray-700 dark:bg-gray-950 dark:text-white';
</script>

<template>
    <Head :title="`${props.infoKuliah ? 'Edit' : 'Tambah'} Info Kuliah`" />
    <AppLayout :breadcrumbs="[{ title: 'Info Kuliah', href: route('admin.info-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4] dark:bg-gray-950">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black dark:text-white">
                            {{ props.infoKuliah ? 'Edit' : 'Tambah' }} Info Kuliah
                        </h1>
                        <p class="text-sm leading-5 text-[#615d59] dark:text-gray-400">Lengkapi informasi dan berkas perkuliahan.</p>
                    </div>
                    <Link :href="route('admin.info-kuliah.index')"
                        ><Button
                            variant="outline"
                            class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800"
                            >Kembali</Button
                        ></Link
                    >
                </div>
                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00] shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>
                <form class="space-y-4" enctype="multipart/form-data" @submit.prevent="submit">
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)] dark:border-gray-800 dark:bg-gray-900"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Info Kuliah</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="information" class="text-sm font-medium text-black dark:text-white">Informasi</Label>
                            <textarea id="information" v-model="form.information" placeholder="Tulis informasi perkuliahan" :class="area" required />
                            <InputError :message="form.errors.information" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="file" class="text-sm font-medium text-black dark:text-white">File</Label>
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
                                class="h-16 w-full cursor-pointer rounded-md border-2 border-dashed border-[#dddddd] bg-[#fafafa] transition-colors hover:border-[#b8cde3] hover:bg-white dark:border-gray-700 dark:bg-gray-950 dark:hover:border-blue-500 dark:hover:bg-gray-800"
                                :class="isDragging ? 'border-[#0075de] bg-white dark:bg-gray-800' : ''"
                                @click="pickFile"
                                @dragover.prevent="isDragging = true"
                                @dragleave="isDragging = false"
                                @drop.prevent="onDrop"
                            >
                                <AttachmentMedia class="bg-white text-[#0075de] dark:bg-gray-800"><Upload class="size-4" /></AttachmentMedia>
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
                            <p v-else-if="props.infoKuliah" class="text-xs text-[#615d59] dark:text-gray-400">
                                File saat ini: {{ props.infoKuliah.file }}
                            </p>
                            <InputError :message="form.errors.file" />
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
