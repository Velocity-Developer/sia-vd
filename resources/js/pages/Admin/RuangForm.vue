<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ ruang: Record<string, any> | null }>();

const title = `${props.ruang ? 'Edit' : 'Tambah'} Ruang`;

const form = useForm({
    kode_ruang: props.ruang?.kode_ruang ?? '',
    nama_ruang: props.ruang?.nama_ruang ?? '',
    kapasitas: props.ruang?.kapasitas ?? '',
    detail: props.ruang?.detail ?? '',
});

const submit = () => (props.ruang ? form.put(route('admin.ruang.update', props.ruang.id)) : form.post(route('admin.ruang.store')));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Ruang', href: route('admin.ruang.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi kode, nama, kapasitas, dan detail fasilitas ruang.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.ruang.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Ruang</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_ruang" class="label-isian">Kode Ruang</Label>
                                <Input id="kode_ruang" v-model="form.kode_ruang" type="text" required />
                                <InputError :message="form.errors.kode_ruang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_ruang" class="label-isian">Nama Ruang</Label>
                                <Input id="nama_ruang" v-model="form.nama_ruang" type="text" required />
                                <InputError :message="form.errors.nama_ruang" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2 sm:max-w-[240px]">
                            <Label for="kapasitas" class="label-isian">Kapasitas</Label>
                            <Input id="kapasitas" v-model="form.kapasitas" type="number" min="1" max="1000" required />
                            <InputError :message="form.errors.kapasitas" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="detail" class="label-isian">Detail</Label>
                            <textarea
                                id="detail"
                                v-model="form.detail"
                                rows="4"
                                placeholder="Gedung, lantai, fasilitas (AC, proyektor, dll)"
                                class="isian isian-area"
                            />
                            <InputError :message="form.errors.detail" />
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
