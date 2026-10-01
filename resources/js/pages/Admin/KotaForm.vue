<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ kota: Record<string, any> | null; provinsis: { id: number; kode: string; nama: string }[] }>();

const title = `${props.kota ? 'Edit' : 'Tambah'} Kota/Kabupaten`;

const form = useForm({
    provinsi_id: props.kota?.provinsi_id ?? '',
    kode: props.kota?.kode ?? '',
    nama: props.kota?.nama ?? '',
});

const submit = () => (props.kota ? form.put(route('admin.kota.update', props.kota.id)) : form.post(route('admin.kota.store')));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kota/Kabupaten', href: route('admin.kota.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Pilih provinsi, lalu lengkapi kode dan nama kota/kabupaten.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.kota.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Kota/Kabupaten</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="provinsi_id" class="label-isian">Provinsi</Label>
                            <select id="provinsi_id" v-model="form.provinsi_id" class="isian isian-pilih" required>
                                <option value="">Pilih provinsi</option>
                                <option v-for="provinsi in props.provinsis" :key="provinsi.id" :value="provinsi.id">{{ provinsi.nama }}</option>
                            </select>
                            <InputError :message="form.errors.provinsi_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-[200px_1fr]">
                            <div class="grid gap-2">
                                <Label for="kode" class="label-isian">Kode</Label>
                                <Input id="kode" v-model="form.kode" type="text" maxlength="20" required />
                                <InputError :message="form.errors.kode" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama" class="label-isian">Nama Kota/Kabupaten</Label>
                                <Input id="nama" v-model="form.nama" type="text" required />
                                <InputError :message="form.errors.nama" />
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
