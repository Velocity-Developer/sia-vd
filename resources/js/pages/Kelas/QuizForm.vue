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

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const area =
    'min-h-24 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Kelas {{ props.kelasKuliah?.kode_kelas }} — lengkapi nama quiz, durasi, tenggat waktu, dan catatan.
                        </p>
                    </div>
                    <Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)"
                        ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                    >
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01)]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Quiz</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="nama_quiz" class="text-sm font-medium text-black">Nama Quiz</Label>
                                <Input
                                    id="nama_quiz"
                                    v-model="form.nama_quiz"
                                    type="text"
                                    placeholder="cth. Quiz 1 Basis Data"
                                    :class="inp"
                                    required
                                />
                                <InputError :message="form.errors.nama_quiz" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="waktu_pengerjaan" class="text-sm font-medium text-black">Waktu Pengerjaan (menit)</Label>
                                <Input
                                    id="waktu_pengerjaan"
                                    v-model="form.waktu_pengerjaan"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    placeholder="cth. 60"
                                    :class="inp"
                                />
                                <InputError :message="form.errors.waktu_pengerjaan" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="tenggat_waktu" class="text-sm font-medium text-black">Tenggat Waktu</Label>
                            <DateTimePicker v-model="form.tenggat_waktu" :min-date="today" placeholder="Pilih tenggat waktu" />
                            <InputError :message="form.errors.tenggat_waktu" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="catatan" class="text-sm font-medium text-black">Catatan</Label>
                            <textarea id="catatan" v-model="form.catatan" placeholder="Catatan tambahan untuk quiz ini" :class="area" />
                            <InputError :message="form.errors.catatan" />
                        </div>
                    </section>

                    <div class="flex justify-end pt-2">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
