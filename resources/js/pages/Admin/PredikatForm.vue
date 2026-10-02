<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ predikat: { id: number; nama: string; bobot_minimal: number; bobot_maksimal: number } | null }>();

const title = `${props.predikat ? 'Edit' : 'Tambah'} Predikat`;

const form = useForm({
    nama: props.predikat?.nama ?? '',
    bobot_minimal: (props.predikat?.bobot_minimal ?? '') as number | string,
    bobot_maksimal: (props.predikat?.bobot_maksimal ?? '') as number | string,
});

const submit = () => (props.predikat ? form.put(route('admin.predikat.update', props.predikat.id)) : form.post(route('admin.predikat.store')));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Predikat', href: route('admin.predikat.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">IPK lulusan yang berada di antara bobot minimal dan maksimal mendapat predikat ini.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.predikat.index')">Kembali</Link></Button>
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Predikat</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2 sm:col-span-3">
                                <Label for="nama" class="label-isian">Nama Predikat</Label>
                                <Input id="nama" v-model="form.nama" type="text" maxlength="60" required />
                                <InputError :message="form.errors.nama" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bobot_minimal" class="label-isian">Bobot Minimal</Label>
                                <Input id="bobot_minimal" v-model="form.bobot_minimal" type="number" min="0" max="4" step="0.01" required />
                                <InputError :message="form.errors.bobot_minimal" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bobot_maksimal" class="label-isian">Bobot Maksimal</Label>
                                <Input id="bobot_maksimal" v-model="form.bobot_maksimal" type="number" min="0" max="4" step="0.01" required />
                                <InputError :message="form.errors.bobot_maksimal" />
                            </div>
                        </div>
                        <p class="teks-bantu mt-3">Bobot berupa IPK 0,00–4,00 (dua desimal). Rentang tidak boleh beririsan dengan predikat lain.</p>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
