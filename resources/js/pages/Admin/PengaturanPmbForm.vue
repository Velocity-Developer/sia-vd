<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { rupiah } from '@/lib/tagihanRemidi';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ pengaturanPmb: Record<string, any> | null }>();

const title = `${props.pengaturanPmb ? 'Edit' : 'Tambah'} Periode PMB`;

const form = useForm({
    kode: props.pengaturanPmb?.kode ?? '',
    tahun_angkatan: props.pengaturanPmb?.tahun_angkatan ?? new Date().getFullYear(),
    tanggal_buka: props.pengaturanPmb?.tanggal_buka?.slice(0, 10) ?? '',
    tanggal_tutup: props.pengaturanPmb?.tanggal_tutup?.slice(0, 10) ?? '',
    tanggal_usm_mulai: props.pengaturanPmb?.tanggal_usm_mulai?.slice(0, 10) ?? '',
    tanggal_usm_selesai: props.pengaturanPmb?.tanggal_usm_selesai?.slice(0, 10) ?? '',
    tanggal_her: props.pengaturanPmb?.tanggal_her?.slice(0, 10) ?? '',
    tanggal_pembayaran_mulai: props.pengaturanPmb?.tanggal_pembayaran_mulai?.slice(0, 10) ?? '',
    tanggal_pembayaran_selesai: props.pengaturanPmb?.tanggal_pembayaran_selesai?.slice(0, 10) ?? '',
    nilai_minimal: props.pengaturanPmb?.nilai_minimal ?? 0,
    kapasitas: props.pengaturanPmb?.kapasitas ?? '',
    biaya_pendaftaran: props.pengaturanPmb?.biaya_pendaftaran ?? 0,
    is_open: props.pengaturanPmb?.is_open ?? false,
});

const submit = () =>
    props.pengaturanPmb ? form.put(route('admin.periode-pmb.update', props.pengaturanPmb.id)) : form.post(route('admin.periode-pmb.store'));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Atur Periode PMB', href: route('admin.periode-pmb.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Atur jadwal pendaftaran, USM, her-registrasi, dan pembayaran calon mahasiswa baru.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.periode-pmb.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Periode</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode" class="label-isian">Kode</Label>
                                <Input id="kode" v-model="form.kode" type="text" maxlength="20" required />
                                <InputError :message="form.errors.kode" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tahun_angkatan" class="label-isian">Tahun Angkatan</Label>
                                <Input id="tahun_angkatan" v-model="form.tahun_angkatan" type="number" min="2000" max="2100" required />
                                <InputError :message="form.errors.tahun_angkatan" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="kapasitas" class="label-isian">Kapasitas</Label>
                                <Input id="kapasitas" v-model="form.kapasitas" type="number" min="1" required />
                                <InputError :message="form.errors.kapasitas" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nilai_minimal" class="label-isian">Nilai Minimal</Label>
                                <Input id="nilai_minimal" v-model="form.nilai_minimal" type="number" min="0" max="100" step="0.01" required />
                                <span class="teks-bantu">Skala 0–100.</span>
                                <InputError :message="form.errors.nilai_minimal" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <label class="flex items-center gap-2 text-sm text-black"
                                    ><input v-model="form.is_open" type="checkbox" class="size-4 accent-[#0075de]" /> Pendaftaran dibuka</label
                                >
                                <InputError :message="form.errors.is_open" />
                            </div>
                        </div>
                    </section>
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Jadwal</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_buka" class="label-isian">Tanggal Buka</Label>
                                <DatePicker id="tanggal_buka" v-model="form.tanggal_buka" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_buka" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_tutup" class="label-isian">Tanggal Tutup</Label>
                                <DatePicker id="tanggal_tutup" v-model="form.tanggal_tutup" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_tutup" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_usm_mulai" class="label-isian">USM Mulai</Label>
                                <DatePicker id="tanggal_usm_mulai" v-model="form.tanggal_usm_mulai" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_usm_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_usm_selesai" class="label-isian">USM Selesai</Label>
                                <DatePicker id="tanggal_usm_selesai" v-model="form.tanggal_usm_selesai" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_usm_selesai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_her" class="label-isian">Tanggal Her-registrasi</Label>
                                <DatePicker id="tanggal_her" v-model="form.tanggal_her" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_her" />
                            </div>
                        </div>
                    </section>
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Pembayaran Pendaftaran</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="biaya_pendaftaran" class="label-isian">Biaya Pendaftaran</Label>
                                <Input id="biaya_pendaftaran" v-model="form.biaya_pendaftaran" type="number" min="0" required />
                                <span class="teks-bantu">{{ rupiah(Number(form.biaya_pendaftaran)) }}</span>
                                <InputError :message="form.errors.biaya_pendaftaran" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_pembayaran_mulai" class="label-isian">Pembayaran Mulai</Label>
                                <DatePicker id="tanggal_pembayaran_mulai" v-model="form.tanggal_pembayaran_mulai" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_pembayaran_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_pembayaran_selesai" class="label-isian">Pembayaran Selesai</Label>
                                <DatePicker id="tanggal_pembayaran_selesai" v-model="form.tanggal_pembayaran_selesai" placeholder="Pilih tanggal" />
                                <InputError :message="form.errors.tanggal_pembayaran_selesai" />
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
