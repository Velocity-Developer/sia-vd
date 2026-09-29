<script setup lang="ts">
import DateTimePicker from '@/components/DateTimePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ peran: Peran; kelasKuliah: Record<string, any>; quiz: Record<string, any> | null }>();
const rute = rutePeran(props.peran);

const title = `${props.quiz ? 'Edit' : 'Tambah'} Quiz`;
const now = new Date();
const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

const toDatetimeLocal = (value: unknown): string => {
    if (typeof value !== 'string' || value === '') return '';
    return value.replace(' ', 'T').slice(0, 16);
};

const form = useForm({
    nama_quiz: props.quiz?.nama_quiz ?? '',
    waktu_pengerjaan: props.quiz?.waktu_pengerjaan ?? '',
    tenggat_waktu: toDatetimeLocal(props.quiz?.tenggat_waktu),
    catatan: props.quiz?.catatan ?? '',
});

const submit = () => {
    if (props.quiz) {
        form.put(rute('kelas-kuliah.quiz.update', [props.kelasKuliah.id, props.quiz.id]));
    } else {
        form.post(rute('kelas-kuliah.quiz.store', props.kelasKuliah.id));
    }
};
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">
                            Kelas {{ props.kelasKuliah?.kode_kelas }} — lengkapi nama quiz, durasi, tenggat waktu, dan catatan.
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <form class="kartu p-6" @submit.prevent="submit">
                    <h2 class="judul-bagian">Data Quiz</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="nama_quiz" class="label-isian">Nama Quiz</Label>
                            <Input id="nama_quiz" v-model="form.nama_quiz" type="text" placeholder="cth. Quiz 1 Basis Data" required />
                            <InputError :message="form.errors.nama_quiz" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="waktu_pengerjaan" class="label-isian">Waktu Pengerjaan (menit)</Label>
                            <Input id="waktu_pengerjaan" v-model="form.waktu_pengerjaan" type="number" min="1" max="1440" placeholder="cth. 60" />
                            <InputError :message="form.errors.waktu_pengerjaan" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="tenggat_waktu" class="label-isian">Tenggat Waktu</Label>
                        <DateTimePicker v-model="form.tenggat_waktu" :min-date="today" placeholder="Pilih tenggat waktu" />
                        <InputError :message="form.errors.tenggat_waktu" />
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="catatan" class="label-isian">Catatan</Label>
                        <textarea id="catatan" v-model="form.catatan" placeholder="Catatan tambahan untuk quiz ini" class="isian isian-area" />
                        <InputError :message="form.errors.catatan" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
