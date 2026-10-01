<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ agama: Record<string, any> | null }>();

const title = `${props.agama ? 'Edit' : 'Tambah'} Agama`;

const form = useForm({
    kode: props.agama?.kode ?? '',
    nama: props.agama?.nama ?? '',
});

const submit = () => (props.agama ? form.put(route('admin.agama.update', props.agama.id)) : form.post(route('admin.agama.store')));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Agama', href: route('admin.agama.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi kode dan nama agama.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.agama.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Agama</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-[200px_1fr]">
                            <div class="grid gap-2">
                                <Label for="kode" class="label-isian">Kode</Label>
                                <Input id="kode" v-model="form.kode" type="text" maxlength="20" required />
                                <InputError :message="form.errors.kode" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama" class="label-isian">Nama Agama</Label>
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
