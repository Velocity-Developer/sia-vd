<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';

type Informasi = {
    judul: string;
    pengantar: string | null;
    syarat: string | null;
    jadwal_tes: string | null;
    biaya: string | null;
    kontak: string | null;
};

const props = defineProps<{ informasi: Informasi; bagian: Record<string, string> }>();

const page = usePage<{ flash: { success?: string; error?: string } }>();

const form = useForm<Record<string, string>>({
    judul: props.informasi.judul,
    pengantar: props.informasi.pengantar ?? '',
    syarat: props.informasi.syarat ?? '',
    jadwal_tes: props.informasi.jadwal_tes ?? '',
    biaya: props.informasi.biaya ?? '',
    kontak: props.informasi.kontak ?? '',
});

const simpan = () => form.put(route('admin.informasi-pmb.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Informasi PMB" />
    <AppLayout :breadcrumbs="[{ title: 'Informasi PMB', href: route('admin.informasi-pmb.edit') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Informasi PMB</h1>
                        <p class="deskripsi-halaman">
                            Isi halaman publik Informasi PMB yang dibuka calon mahasiswa tanpa login. Jadwal pendaftaran, USM, dan biaya pendaftaran
                            diambil otomatis dari periode PMB yang dibuka.
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="route('pmb.informasi')" target="_blank" rel="noopener"><ExternalLink /> Lihat Halaman</a>
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>

                <form class="flex flex-col gap-6" @submit.prevent="simpan">
                    <section class="kartu p-6">
                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label for="judul" class="label-isian">Judul</Label>
                                <Input id="judul" v-model="form.judul" type="text" maxlength="150" required />
                                <InputError :message="form.errors.judul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pengantar" class="label-isian">Pengantar</Label>
                                <textarea id="pengantar" v-model="form.pengantar" rows="3" maxlength="5000" class="isian isian-area" />
                                <InputError :message="form.errors.pengantar" />
                            </div>
                            <div v-for="(judul, kolom) in props.bagian" :key="kolom" class="grid gap-2">
                                <Label :for="kolom" class="label-isian">{{ judul }}</Label>
                                <textarea :id="kolom" v-model="form[kolom]" rows="5" maxlength="5000" class="isian isian-area" />
                                <InputError :message="form.errors[kolom]" />
                            </div>
                        </div>
                        <p class="teks-bantu mt-3">Tulis sebagai teks biasa; baris baru tetap tampil. Bagian yang dikosongkan tidak ditampilkan.</p>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
