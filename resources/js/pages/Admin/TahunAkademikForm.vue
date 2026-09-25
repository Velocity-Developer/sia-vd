<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps<{ tahunAkademik: Record<string, any> | null }>();
const title = `${props.tahunAkademik ? 'Edit' : 'Tambah'} Tahun Akademik`;
const form = useForm({
    tahun: props.tahunAkademik?.tahun ?? '',
    semester: props.tahunAkademik?.semester ?? 'Ganjil',
    tanggal_mulai: props.tahunAkademik?.tanggal_mulai?.slice(0, 10) ?? '',
    tanggal_akhir: props.tahunAkademik?.tanggal_akhir?.slice(0, 10) ?? '',
    tanggal_krs_awal: props.tahunAkademik?.tanggal_krs_awal?.slice(0, 10) ?? '',
    tanggal_krs_akhir: props.tahunAkademik?.tanggal_krs_akhir?.slice(0, 10) ?? '',
    batas_input_nilai: props.tahunAkademik?.batas_input_nilai?.slice(0, 10) ?? '',
    status: props.tahunAkademik?.status ?? false,
});
const submit = () =>
    props.tahunAkademik ? form.put(route('admin.tahun-akademik.update', props.tahunAkademik.id)) : form.post(route('admin.tahun-akademik.store'));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>
<template>
    <Head :title="title" /><AppLayout :breadcrumbs="[{ title: 'Tahun Akademik', href: route('admin.tahun-akademik.index') }]"
        ><div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto max-w-[1000px] px-4 py-6">
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-[26px] font-bold">{{ title }}</h1>
                    <Link :href="route('admin.tahun-akademik.index')"><Button variant="outline">Kembali</Button></Link>
                </div>
                <form class="space-y-4" @submit.prevent="submit">
                    <section class="grid gap-4 rounded-xl border bg-white p-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tahun">Tahun</Label>
                            <Input id="tahun" v-model="form.tahun" placeholder="2025/2026" :class="inp" required /><InputError
                                :message="form.errors.tahun"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="semester">Semester</Label>
                            <select id="semester" v-model="form.semester" :class="sel" required>
                                <option>Ganjil</option>
                                <option>Genap</option></select
                            ><InputError :message="form.errors.semester" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_mulai">Tanggal Mulai</Label>
                            <DatePicker id="tanggal_mulai" v-model="form.tanggal_mulai" placeholder="Pilih tanggal mulai" /><InputError
                                :message="form.errors.tanggal_mulai"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_akhir">Tanggal Akhir</Label>
                            <DatePicker id="tanggal_akhir" v-model="form.tanggal_akhir" placeholder="Pilih tanggal akhir" /><InputError
                                :message="form.errors.tanggal_akhir"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_krs_awal">Tanggal KRS Awal</Label>
                            <DatePicker id="tanggal_krs_awal" v-model="form.tanggal_krs_awal" placeholder="Pilih tanggal KRS awal" />
                            <InputError :message="form.errors.tanggal_krs_awal" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_krs_akhir">Tanggal KRS Akhir</Label>
                            <DatePicker id="tanggal_krs_akhir" v-model="form.tanggal_krs_akhir" placeholder="Pilih tanggal KRS akhir" />
                            <InputError :message="form.errors.tanggal_krs_akhir" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="batas_input_nilai">Batas Input Nilai</Label>
                            <DatePicker id="batas_input_nilai" v-model="form.batas_input_nilai" placeholder="Pilih batas input nilai" />
                            <p class="text-xs text-[#a39e98]">
                                Lewat tanggal ini nilai semua kelas terkunci untuk dosen. Kosongkan bila tanpa batas.
                            </p>
                            <InputError :message="form.errors.batas_input_nilai" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <label class="flex items-center gap-2"><input v-model="form.status" type="checkbox" /> Aktif</label>
                            <InputError :message="form.errors.status" />
                        </div>
                    </section>
                    <div class="flex justify-end">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] text-white">Simpan</Button>
                    </div>
                </form>
            </div>
        </div></AppLayout
    >
</template>
