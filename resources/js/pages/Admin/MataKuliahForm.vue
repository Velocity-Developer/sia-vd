<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    mataKuliah: Record<string, any> | null;
    programStudis: { id: number; nama_prodi: string; jenjang: string; nama_fakultas: string }[];
    pilihanPrasyarat: { id: number; kode_matkul: string; nama_matkul: string; semester: number; prodi_id: number }[];
}>();

const groupedProdi = computed(() => {
    const groups: Record<string, { id: number; name: string }[]> = {};
    for (const item of props.programStudis) {
        const key = item.nama_fakultas ?? 'Fakultas Lainnya';
        (groups[key] ??= []).push({ id: item.id, name: `${item.jenjang} - ${item.nama_prodi}` });
    }
    return Object.entries(groups).map(([label, options]) => ({ label, options }));
});

const title = `${props.mataKuliah ? 'Edit' : 'Tambah'} Mata Kuliah`;

const form = useForm({
    kode_matkul: props.mataKuliah?.kode_matkul ?? '',
    nama_matkul: props.mataKuliah?.nama_matkul ?? '',
    sks: props.mataKuliah?.sks ?? '',
    semester: props.mataKuliah?.semester ?? '',
    jenis: props.mataKuliah?.jenis ?? '',
    tugas_akhir: Boolean(props.mataKuliah?.tugas_akhir),
    prodi_id: props.mataKuliah?.prodi_id ?? '',
    prasyarat_ids: (props.mataKuliah?.prasyarat_ids ?? []) as number[],
});

// Prasyarat hanya dari prodi yang sama dan semester lebih kecil (sama dengan aturan di server).
const cariPrasyarat = ref('');
const kandidatPrasyarat = computed(() =>
    props.pilihanPrasyarat.filter(
        (item) =>
            item.prodi_id === Number(form.prodi_id) && item.id !== props.mataKuliah?.id && (!form.semester || item.semester < Number(form.semester)),
    ),
);
const prasyaratTampil = computed(() => {
    const kata = cariPrasyarat.value.trim().toLowerCase();
    return kata
        ? kandidatPrasyarat.value.filter((item) => `${item.kode_matkul} ${item.nama_matkul}`.toLowerCase().includes(kata))
        : kandidatPrasyarat.value;
});
watch(kandidatPrasyarat, (kandidat) => {
    const boleh = new Set(kandidat.map((item) => item.id));
    form.prasyarat_ids = form.prasyarat_ids.filter((id) => boleh.has(id));
});

const submit = () =>
    props.mataKuliah ? form.put(route('admin.mata-kuliah.update', props.mataKuliah.id)) : form.post(route('admin.mata-kuliah.store'));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Mata Kuliah', href: route('admin.mata-kuliah.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi kode, nama, SKS, semester, jenis, dan program studi.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.mata-kuliah.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Mata Kuliah</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="prodi_id" class="label-isian">Program Studi</Label>
                            <SearchSelect
                                id="prodi_id"
                                v-model="form.prodi_id"
                                :groups="groupedProdi"
                                placeholder="Pilih program studi"
                                search-placeholder="Cari program studi"
                                required
                            />
                            <InputError :message="form.errors.prodi_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_matkul" class="label-isian">Kode Mata Kuliah</Label>
                                <Input id="kode_matkul" v-model="form.kode_matkul" type="text" required />
                                <InputError :message="form.errors.kode_matkul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_matkul" class="label-isian">Nama Mata Kuliah</Label>
                                <Input id="nama_matkul" v-model="form.nama_matkul" type="text" required />
                                <InputError :message="form.errors.nama_matkul" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="sks" class="label-isian">SKS</Label>
                                <Input id="sks" v-model="form.sks" type="number" min="1" max="6" required />
                                <InputError :message="form.errors.sks" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="semester" class="label-isian">Semester</Label>
                                <Input id="semester" v-model="form.semester" type="number" min="1" max="14" required />
                                <InputError :message="form.errors.semester" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jenis" class="label-isian">Jenis</Label>
                                <select id="jenis" v-model="form.jenis" class="isian isian-pilih" required>
                                    <option value="">Pilih jenis</option>
                                    <option value="Wajib">Wajib</option>
                                    <option value="Pilihan">Pilihan</option>
                                </select>
                                <InputError :message="form.errors.jenis" />
                            </div>
                        </div>
                        <label class="mt-4 flex items-start gap-3 rounded-lg border border-[#e6e6e6] px-4 py-3 dark:border-border">
                            <input v-model="form.tugas_akhir" type="checkbox" class="mt-0.5 size-4 accent-[#0075de]" />
                            <span class="grid gap-0.5">
                                <span class="label-isian">Mata kuliah TA/Skripsi</span>
                                <span class="teks-bantu"
                                    >Mahasiswa yang mengambil mata kuliah ini di semester aktif boleh mengajukan tugas akhir dan mendaftar
                                    pendadaran.</span
                                >
                            </span>
                        </label>
                        <InputError :message="form.errors.tugas_akhir" />
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Prasyarat</h2>
                        <p class="teks-bantu mt-1">
                            Mata kuliah yang harus lulus sebelum mahasiswa bisa mengambil mata kuliah ini. Pilihan berasal dari program studi yang
                            sama dengan semester lebih kecil.
                        </p>
                        <p v-if="!form.prodi_id || !form.semester" class="mt-4 text-sm text-[#615d59] dark:text-muted-foreground">
                            Pilih program studi dan isi semester terlebih dahulu.
                        </p>
                        <p v-else-if="!kandidatPrasyarat.length" class="mt-4 text-sm text-[#615d59] dark:text-muted-foreground">
                            Belum ada mata kuliah dari semester sebelumnya di program studi ini.
                        </p>
                        <template v-else>
                            <Input v-model="cariPrasyarat" type="search" placeholder="Cari kode atau nama mata kuliah" class="mt-4" />
                            <div
                                class="mt-3 grid max-h-72 gap-1 overflow-y-auto rounded-lg border border-[#e6e6e6] p-2 dark:border-border sm:grid-cols-2"
                            >
                                <label
                                    v-for="item in prasyaratTampil"
                                    :key="item.id"
                                    class="flex items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-[#f6f5f4] dark:hover:bg-accent"
                                >
                                    <input v-model="form.prasyarat_ids" type="checkbox" :value="item.id" class="mt-0.5 size-4 accent-[#0075de]" />
                                    <span
                                        >{{ item.kode_matkul }} — {{ item.nama_matkul }}
                                        <span class="text-[#a39e98]">(smt {{ item.semester }})</span></span
                                    >
                                </label>
                                <p v-if="!prasyaratTampil.length" class="px-2 py-1.5 text-sm text-[#a39e98]">Mata kuliah tidak ditemukan.</p>
                            </div>
                            <p class="teks-bantu mt-2">{{ form.prasyarat_ids.length }} prasyarat dipilih.</p>
                        </template>
                        <InputError :message="form.errors.prasyarat_ids" />
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
