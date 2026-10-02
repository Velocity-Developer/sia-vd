<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Matkul = { id: number; kode_matkul: string; nama_matkul: string; semester: number; prodi_id: number; prodi: string };

const props = defineProps<{
    prasyarat: { id: number; mata_kuliah_id: number; prasyarat_id: number } | null;
    mataKuliahs: Matkul[];
}>();

const title = `${props.prasyarat ? 'Edit' : 'Tambah'} Prasyarat`;

const form = useForm({
    mata_kuliah_id: (props.prasyarat?.mata_kuliah_id ?? null) as number | null,
    prasyarat_id: (props.prasyarat?.prasyarat_id ?? null) as number | null,
});

const opsi = (mk: Matkul) => ({ id: mk.id, name: `${mk.kode_matkul} — ${mk.nama_matkul} (smt ${mk.semester})` });
const kelompok = (daftar: Matkul[]) =>
    [...new Set(daftar.map((mk) => mk.prodi))].map((prodi) => ({
        label: prodi || 'Tanpa Program Studi',
        options: daftar.filter((mk) => mk.prodi === prodi).map(opsi),
    }));

const mataKuliah = computed(() => props.mataKuliahs.find((mk) => mk.id === form.mata_kuliah_id) ?? null);
const grupMataKuliah = computed(() => kelompok(props.mataKuliahs));
// Prasyarat: prodi yang sama dan semester sebelumnya (aturan yang sama dicek di server).
const grupPrasyarat = computed(() =>
    mataKuliah.value
        ? kelompok(props.mataKuliahs.filter((mk) => mk.prodi_id === mataKuliah.value!.prodi_id && mk.semester < mataKuliah.value!.semester))
        : [],
);

watch(
    () => form.mata_kuliah_id,
    () => {
        if (!grupPrasyarat.value.some((grup) => grup.options.some((option) => option.id === form.prasyarat_id))) form.prasyarat_id = null;
    },
);

const submit = () => (props.prasyarat ? form.put(route('admin.prasyarat.update', props.prasyarat.id)) : form.post(route('admin.prasyarat.store')));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Mata Kuliah Prasyarat', href: route('admin.prasyarat.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Pilih mata kuliah dan mata kuliah yang harus lulus lebih dulu.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.prasyarat.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Prasyarat</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="mata_kuliah_id" class="label-isian">Mata Kuliah</Label>
                                <SearchSelect
                                    id="mata_kuliah_id"
                                    v-model="form.mata_kuliah_id"
                                    :groups="grupMataKuliah"
                                    placeholder="Pilih mata kuliah"
                                    search-placeholder="Cari mata kuliah"
                                    required
                                />
                                <InputError :message="form.errors.mata_kuliah_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="prasyarat_id" class="label-isian">Mata Kuliah Prasyarat</Label>
                                <SearchSelect
                                    id="prasyarat_id"
                                    v-model="form.prasyarat_id"
                                    :groups="grupPrasyarat"
                                    :placeholder="mataKuliah ? 'Pilih mata kuliah prasyarat' : 'Pilih mata kuliah dulu'"
                                    search-placeholder="Cari mata kuliah"
                                    required
                                />
                                <p class="teks-bantu">Hanya mata kuliah dari program studi yang sama dan semester sebelumnya.</p>
                                <InputError :message="form.errors.prasyarat_id" />
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing || !form.mata_kuliah_id || !form.prasyarat_id">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
