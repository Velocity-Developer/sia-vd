<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
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

const props = defineProps<{
    peran: Peran;
    /** Kosong saat menambah dari menu Materi; kelas dipilih lewat isian Kelas Kuliah. */
    kelasKuliah: Record<string, any> | null;
    materi: Record<string, any> | null;
    kelasOptions?: { id: number; name: string; jumlah_pertemuan: number }[];
    dariMenu?: boolean;
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
    kelas_kuliah_id: '' as number | '',
    dari: props.dariMenu ? 'menu' : '',
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

const maksPertemuan = computed(() => {
    const kelas = props.kelasKuliah ?? props.kelasOptions?.find((opsi) => opsi.id === Number(form.kelas_kuliah_id));

    return Math.max(kelas?.jumlah_pertemuan ?? 32, props.materi?.pertemuan_ke ?? 0);
});

const fileCount = computed(() => form.kept_files.length + form.file.length);

const kembaliKe = props.dariMenu || !props.kelasKuliah ? rute('materi.index') : rute('kelas-kuliah.show', props.kelasKuliah.id);

const submit = () => {
    if (!props.kelasKuliah) {
        form.post(rute('materi.store'), { forceFormData: true });
    } else if (props.materi) {
        form.transform((data) => ({ ...data, _method: 'PUT' })).post(rute('kelas-kuliah.materi.update', [props.kelasKuliah.id, props.materi.id]), {
            forceFormData: true,
        });
    } else {
        form.post(rute('kelas-kuliah.materi.store', props.kelasKuliah.id), { forceFormData: true });
    }
};
</script>

<template>
    <Head :title="title" />
    <AppLayout
        :breadcrumbs="[
            props.dariMenu ? { title: 'Materi', href: rute('materi.index') } : { title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') },
        ]"
    >
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">
                            <template v-if="props.kelasKuliah">Kelas {{ props.kelasKuliah.kode_kelas }} — </template>lengkapi
                            <template v-if="!props.kelasKuliah">kelas kuliah, </template>judul, pertemuan, berkas, dan catatan.
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="kembaliKe">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <form class="kartu p-6" @submit.prevent="submit">
                    <h2 class="judul-bagian">Data Materi</h2>
                    <div v-if="!props.kelasKuliah" class="mt-4 grid gap-2">
                        <Label for="kelas_kuliah_id" class="label-isian">Kelas Kuliah</Label>
                        <SearchSelect
                            id="kelas_kuliah_id"
                            v-model="form.kelas_kuliah_id"
                            :options="props.kelasOptions ?? []"
                            placeholder="Pilih kelas kuliah"
                            search-placeholder="Cari kode kelas atau mata kuliah"
                            required
                        />
                        <p v-if="!(props.kelasOptions ?? []).length" class="teks-bantu">Belum ada kelas di tahun akademik aktif yang bisa dipilih.</p>
                        <InputError :message="form.errors.kelas_kuliah_id" />
                    </div>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="judul_materi" class="label-isian">Judul Materi</Label>
                            <Input id="judul_materi" v-model="form.judul_materi" type="text" placeholder="cth. Pengantar Basis Data" required />
                            <InputError :message="form.errors.judul_materi" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pertemuan_ke" class="label-isian">Pertemuan Ke</Label>
                            <Input
                                id="pertemuan_ke"
                                v-model="form.pertemuan_ke"
                                type="number"
                                min="1"
                                :max="maksPertemuan"
                                placeholder="cth. 1"
                                required
                            />
                            <InputError :message="form.errors.pertemuan_ke" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="jenis" class="label-isian">Jenis</Label>
                            <select id="jenis" v-model="form.jenis" class="isian isian-pilih" required>
                                <option value="Materi">Materi</option>
                                <option value="Pengumuman">Pengumuman</option>
                            </select>
                            <InputError :message="form.errors.jenis" />
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
                        <p v-else-if="props.materi" class="teks-bantu">Belum ada berkas tersimpan.</p>
                        <InputError :message="form.errors.file" />
                        <InputError v-for="(msg, key) in form.errors" :key="key" :message="String(key).startsWith('file.') ? String(msg) : ''" />
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="catatan" class="label-isian">Catatan </Label>
                        <textarea id="catatan" v-model="form.catatan" placeholder="Catatan tambahan untuk materi ini" class="isian isian-area" />
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
