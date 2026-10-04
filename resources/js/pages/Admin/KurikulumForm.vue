<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Kurikulum = { id: number; prodi_id: number; nama: string; tahun_akademik_id: number | null; aktif: boolean; keterangan: string | null };

const props = defineProps<{
    kurikulum: Kurikulum | null;
    prodiOptions: { id: number; name: string }[];
    tahunAkademikOptions: { id: number; name: string }[];
}>();

const title = `${props.kurikulum ? 'Edit' : 'Tambah'} Kurikulum`;

const form = useForm({
    prodi_id: (props.kurikulum?.prodi_id ?? (props.prodiOptions.length === 1 ? props.prodiOptions[0].id : '')) as number | string,
    nama: props.kurikulum?.nama ?? '',
    tahun_akademik_id: (props.kurikulum?.tahun_akademik_id ?? '') as number | string,
    aktif: props.kurikulum?.aktif ?? true,
    keterangan: props.kurikulum?.keterangan ?? '',
});

const submit = () => {
    const kirim = form.transform((data) => ({ ...data, tahun_akademik_id: data.tahun_akademik_id || null }));
    if (props.kurikulum) kirim.put(route('admin.kurikulum.update', props.kurikulum.id));
    else kirim.post(route('admin.kurikulum.store'));
};
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kurikulum', href: route('admin.kurikulum.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Setelah disimpan, tambahkan mata kuliah kurikulum ini di halaman detailnya.</p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="props.kurikulum ? route('admin.kurikulum.show', props.kurikulum.id) : route('admin.kurikulum.index')"
                            >Kembali</Link
                        >
                    </Button>
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Kurikulum</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="prodi_id" class="label-isian">Program Studi</Label>
                                <select id="prodi_id" v-model="form.prodi_id" class="isian isian-pilih" required>
                                    <option value="" disabled>Pilih program studi</option>
                                    <option v-for="prodi in props.prodiOptions" :key="prodi.id" :value="prodi.id">{{ prodi.name }}</option>
                                </select>
                                <InputError :message="form.errors.prodi_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama" class="label-isian">Nama Kurikulum</Label>
                                <Input id="nama" v-model="form.nama" type="text" maxlength="100" placeholder="Contoh: Kurikulum 2024" required />
                                <InputError :message="form.errors.nama" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tahun_akademik_id" class="label-isian">Mulai Berlaku</Label>
                                <select id="tahun_akademik_id" v-model="form.tahun_akademik_id" class="isian isian-pilih">
                                    <option value="">Tidak diisi</option>
                                    <option v-for="ta in props.tahunAkademikOptions" :key="ta.id" :value="ta.id">{{ ta.name }}</option>
                                </select>
                                <InputError :message="form.errors.tahun_akademik_id" />
                            </div>
                            <div class="grid gap-2 sm:pt-7">
                                <Label for="aktif" class="label-isian flex w-fit items-center gap-2.5 font-normal">
                                    <Checkbox id="aktif" v-model="form.aktif" />
                                    <span>Kurikulum aktif</span>
                                </Label>
                                <InputError :message="form.errors.aktif" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="keterangan" class="label-isian">Keterangan</Label>
                                <textarea id="keterangan" v-model="form.keterangan" rows="3" maxlength="2000" class="isian isian-area" />
                                <InputError :message="form.errors.keterangan" />
                            </div>
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
