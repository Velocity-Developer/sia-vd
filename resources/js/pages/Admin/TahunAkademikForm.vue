<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
const props = defineProps<{ tahunAkademik: Record<string, any> | null; kelasBerpertemuan?: number }>();
const title = `${props.tahunAkademik ? 'Edit' : 'Tambah'} Tahun Akademik`;
const form = useForm({
    tahun: props.tahunAkademik?.tahun ?? '',
    semester: props.tahunAkademik?.semester ?? 'Ganjil',
    tanggal_mulai: props.tahunAkademik?.tanggal_mulai?.slice(0, 10) ?? '',
    tanggal_akhir: props.tahunAkademik?.tanggal_akhir?.slice(0, 10) ?? '',
    tanggal_krs_awal: props.tahunAkademik?.tanggal_krs_awal?.slice(0, 10) ?? '',
    tanggal_krs_akhir: props.tahunAkademik?.tanggal_krs_akhir?.slice(0, 10) ?? '',
    tanggal_cuti_awal: props.tahunAkademik?.tanggal_cuti_awal?.slice(0, 10) ?? '',
    tanggal_cuti_akhir: props.tahunAkademik?.tanggal_cuti_akhir?.slice(0, 10) ?? '',
    batas_input_nilai: props.tahunAkademik?.batas_input_nilai?.slice(0, 10) ?? '',
    batas_bayar_remidi: props.tahunAkademik?.batas_bayar_remidi?.slice(0, 10) ?? '',
    batas_input_nilai_remidi: props.tahunAkademik?.batas_input_nilai_remidi?.slice(0, 10) ?? '',
    status: props.tahunAkademik?.status ?? false,
});
const submit = () =>
    props.tahunAkademik ? form.put(route('admin.tahun-akademik.update', props.tahunAkademik.id)) : form.post(route('admin.tahun-akademik.store'));
</script>
<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Tahun Akademik', href: route('admin.tahun-akademik.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Atur periode kuliah, KRS, cuti, dan batas input nilai.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.tahun-akademik.index')">Kembali</Link></Button>
                </div>
                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu grid items-start gap-4 p-6 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tahun" class="label-isian">Tahun</Label>
                            <Input id="tahun" v-model="form.tahun" placeholder="2025/2026" required /><InputError :message="form.errors.tahun" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="semester" class="label-isian">Semester</Label>
                            <select id="semester" v-model="form.semester" class="isian isian-pilih" required>
                                <option>Ganjil</option>
                                <option>Genap</option></select
                            ><InputError :message="form.errors.semester" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_mulai" class="label-isian">Tanggal Mulai</Label>
                            <DatePicker id="tanggal_mulai" v-model="form.tanggal_mulai" placeholder="Pilih tanggal mulai" /><InputError
                                :message="form.errors.tanggal_mulai"
                            />
                            <p v-if="props.kelasBerpertemuan" class="teks-bantu">
                                Terkunci: {{ props.kelasBerpertemuan }} kelas sudah punya pertemuan, jadi tanggal mulai tidak bisa diubah.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_akhir" class="label-isian">Tanggal Akhir</Label>
                            <DatePicker id="tanggal_akhir" v-model="form.tanggal_akhir" placeholder="Pilih tanggal akhir" /><InputError
                                :message="form.errors.tanggal_akhir"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_krs_awal" class="label-isian">Tanggal KRS Awal</Label>
                            <DatePicker id="tanggal_krs_awal" v-model="form.tanggal_krs_awal" placeholder="Pilih tanggal KRS awal" />
                            <InputError :message="form.errors.tanggal_krs_awal" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_krs_akhir" class="label-isian">Tanggal KRS Akhir</Label>
                            <DatePicker id="tanggal_krs_akhir" v-model="form.tanggal_krs_akhir" placeholder="Pilih tanggal KRS akhir" />
                            <InputError :message="form.errors.tanggal_krs_akhir" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="tanggal_cuti_awal" class="label-isian">Buka Pengajuan Cuti</Label>
                            <DatePicker id="tanggal_cuti_awal" v-model="form.tanggal_cuti_awal" placeholder="Pilih tanggal buka" />
                            <p class="teks-bantu">
                                Mahasiswa bisa mengajukan cuti untuk semester ini selama periode ini. Kosongkan bila tidak dibuka.
                            </p>
                            <InputError :message="form.errors.tanggal_cuti_awal" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="tanggal_cuti_akhir" class="label-isian">Tutup Pengajuan Cuti</Label>
                            <DatePicker id="tanggal_cuti_akhir" v-model="form.tanggal_cuti_akhir" placeholder="Pilih tanggal tutup" />
                            <InputError :message="form.errors.tanggal_cuti_akhir" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="batas_input_nilai" class="label-isian">Batas Input Nilai</Label>
                            <DatePicker id="batas_input_nilai" v-model="form.batas_input_nilai" placeholder="Pilih batas input nilai" />
                            <p class="teks-bantu">Lewat tanggal ini nilai semua kelas terkunci untuk dosen. Kosongkan bila tanpa batas.</p>
                            <InputError :message="form.errors.batas_input_nilai" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="batas_bayar_remidi" class="label-isian">Batas Bayar Remidi</Label>
                            <DatePicker id="batas_bayar_remidi" v-model="form.batas_bayar_remidi" placeholder="Pilih batas bayar remidi" />
                            <p class="teks-bantu">
                                Tagihan remidi yang belum lunas sampai tanggal ini gugur. Wajib diisi sebelum menerbitkan tagihan remidi.
                            </p>
                            <InputError :message="form.errors.batas_bayar_remidi" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="batas_input_nilai_remidi" class="label-isian">Batas Input Nilai Remidi</Label>
                            <DatePicker
                                id="batas_input_nilai_remidi"
                                v-model="form.batas_input_nilai_remidi"
                                placeholder="Pilih batas input nilai remidi"
                            />
                            <p class="teks-bantu">
                                Ujian remidi dijadwalkan sesudah batas bayar s.d. tanggal ini; lewat tanggal ini nilai remidi terkunci untuk dosen.
                            </p>
                            <InputError :message="form.errors.batas_input_nilai_remidi" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <label class="label-isian flex items-center gap-2"
                                ><input v-model="form.status" type="checkbox" class="size-4 accent-[#0075de]" /> Aktif</label
                            >
                            <InputError :message="form.errors.status" />
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
