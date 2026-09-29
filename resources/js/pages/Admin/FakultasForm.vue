<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ fakultas: Record<string, any> | null; dosen: { id: number; name: string }[] }>();

const title = `${props.fakultas ? 'Edit' : 'Tambah'} Fakultas`;

const form = useForm({
    kode_fakultas: props.fakultas?.kode_fakultas ?? '',
    nama_fakultas: props.fakultas?.nama_fakultas ?? '',
    dekan_id: props.fakultas?.dekan_id ?? '',
    tanggal_berdiri: props.fakultas?.tanggal_berdiri?.slice(0, 10) ?? '',
    no_telp: props.fakultas?.no_telp ?? '',
    email: props.fakultas?.email ?? '',
});

const submit = () => (props.fakultas ? form.put(route('admin.fakultas.update', props.fakultas.id)) : form.post(route('admin.fakultas.store')));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Fakultas', href: route('admin.fakultas.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi data fakultas. Semua field wajib diisi.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.fakultas.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="kartu p-6" @submit.prevent="submit">
                    <h2 class="judul-bagian">Data Fakultas</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="kode_fakultas" class="label-isian">Kode Fakultas</Label>
                            <Input id="kode_fakultas" v-model="form.kode_fakultas" type="text" required />
                            <InputError :message="form.errors.kode_fakultas" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nama_fakultas" class="label-isian">Nama Fakultas</Label>
                            <Input id="nama_fakultas" v-model="form.nama_fakultas" type="text" required />
                            <InputError :message="form.errors.nama_fakultas" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="dekan_id" class="label-isian">Dekan</Label>
                        <SearchSelect
                            id="dekan_id"
                            v-model="form.dekan_id"
                            :options="props.dosen"
                            placeholder="Pilih dekan"
                            search-placeholder="Cari dekan"
                            required
                        />
                        <InputError :message="form.errors.dekan_id" />
                    </div>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tanggal_berdiri" class="label-isian">Tanggal Berdiri</Label>
                            <DatePicker id="tanggal_berdiri" v-model="form.tanggal_berdiri" placeholder="Pilih tanggal berdiri" />
                            <InputError :message="form.errors.tanggal_berdiri" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="no_telp" class="label-isian">Nomor Telepon</Label>
                            <Input id="no_telp" v-model="form.no_telp" type="text" required />
                            <InputError :message="form.errors.no_telp" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="email" class="label-isian">Email</Label>
                        <Input id="email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
