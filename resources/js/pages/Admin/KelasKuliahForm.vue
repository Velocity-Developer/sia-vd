<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    kelasKuliah: Record<string, any> | null;
    jumlahPertemuanBawaan?: number;
    dosens: { id: number; name: string }[];
    matkulGroups: { label: string; options: { id: number; name: string }[] }[];
    tahunAkademiks: { id: number; tahun: string; semester: string }[];
}>();

const title = `${props.kelasKuliah ? 'Edit' : 'Tambah'} Kelas Kuliah`;

const form = useForm({
    kode_kelas: props.kelasKuliah?.kode_kelas ?? '',
    tahun_ajaran: props.kelasKuliah?.tahun_ajaran ?? '',
    kapasitas: props.kelasKuliah?.kapasitas ?? '',
    jumlah_pertemuan: props.kelasKuliah?.jumlah_pertemuan ?? props.jumlahPertemuanBawaan ?? 16,
    dosen_id: props.kelasKuliah?.dosen_id ?? '',
    matkul_id: props.kelasKuliah?.matkul_id ?? '',
    tahun_akademik_id: props.kelasKuliah?.tahun_akademik_id ?? '',
});

const submit = () =>
    props.kelasKuliah ? form.put(route('admin.kelas-kuliah.update', props.kelasKuliah.id)) : form.post(route('admin.kelas-kuliah.store'));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi kode kelas, tahun ajaran, kapasitas, dosen pengampu, dan mata kuliah.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.kelas-kuliah.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <form class="kartu p-6" @submit.prevent="submit">
                    <h2 class="judul-bagian">Data Kelas Kuliah</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="kode_kelas" class="label-isian">Kode Kelas</Label>
                            <Input id="kode_kelas" v-model="form.kode_kelas" type="text" placeholder="IF101-A" required />
                            <InputError :message="form.errors.kode_kelas" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tahun_akademik_id" class="label-isian">Tahun Akademik</Label>
                            <select id="tahun_akademik_id" v-model="form.tahun_akademik_id" class="isian isian-pilih" required>
                                <option value="">Pilih tahun akademik</option>
                                <option v-for="ta in props.tahunAkademiks" :key="ta.id" :value="ta.id">{{ ta.tahun }} {{ ta.semester }}</option>
                            </select>
                            <InputError :message="form.errors.tahun_akademik_id" />
                        </div>
                    </div>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="kapasitas" class="label-isian">Kapasitas</Label>
                            <Input id="kapasitas" v-model="form.kapasitas" type="number" min="1" max="500" required />
                            <InputError :message="form.errors.kapasitas" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="jumlah_pertemuan" class="label-isian">Jumlah Pertemuan</Label>
                            <Input id="jumlah_pertemuan" v-model="form.jumlah_pertemuan" type="number" min="1" max="32" required />
                            <p class="teks-bantu">Termasuk UTS dan UAS. Bawaan dari Pengaturan Akademik.</p>
                            <InputError :message="form.errors.jumlah_pertemuan" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="dosen_id" class="label-isian">Dosen Pengampu</Label>
                        <SearchSelect
                            id="dosen_id"
                            v-model="form.dosen_id"
                            :options="props.dosens"
                            placeholder="Pilih dosen"
                            search-placeholder="Cari dosen (nama / NIDN)"
                            required
                        />
                        <InputError :message="form.errors.dosen_id" />
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="matkul_id" class="label-isian">Mata Kuliah</Label>
                        <SearchSelect
                            id="matkul_id"
                            v-model="form.matkul_id"
                            :groups="props.matkulGroups"
                            placeholder="Pilih mata kuliah"
                            search-placeholder="Cari mata kuliah"
                            required
                        />
                        <InputError :message="form.errors.matkul_id" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
