<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    programStudi: Record<string, any> | null;
    fakultas: { id: number; nama_fakultas: string }[];
    dosen: { id: number; name: string }[];
}>();

const title = `${props.programStudi ? 'Edit' : 'Tambah'} Program Studi`;

const jenjang = ['D3', 'D4', 'S1', 'S2', 'S3', 'Sp-1', 'Sp-2', 'Profesi'];

const form = useForm({
    fakultas_id: props.programStudi?.fakultas_id ?? '',
    kode_prodi: props.programStudi?.kode_prodi ?? '',
    nama_prodi: props.programStudi?.nama_prodi ?? '',
    jenjang: props.programStudi?.jenjang ?? '',
    status_akreditasi: props.programStudi?.status_akreditasi ?? '',
    no_sk_akreditasi: props.programStudi?.no_sk_akreditasi ?? '',
    tanggal_akreditasi_mulai: props.programStudi?.tanggal_akreditasi_mulai?.slice(0, 10) ?? '',
    tanggal_akreditasi_akhir: props.programStudi?.tanggal_akreditasi_akhir?.slice(0, 10) ?? '',
    kaprodi: props.programStudi?.kaprodi ?? '',
    tahun_berdiri: props.programStudi?.tahun_berdiri ?? '',
});

const submit = () =>
    props.programStudi ? form.put(route('admin.program-studi.update', props.programStudi.id)) : form.post(route('admin.program-studi.store'));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Program Studi', href: route('admin.program-studi.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi data program studi, akreditasi, dan kaprodi.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.program-studi.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Program Studi</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="fakultas_id" class="label-isian">Fakultas</Label>
                            <SearchSelect
                                id="fakultas_id"
                                v-model="form.fakultas_id"
                                :options="props.fakultas.map((item) => ({ id: item.id, name: item.nama_fakultas }))"
                                placeholder="Pilih fakultas"
                                search-placeholder="Cari fakultas"
                                required
                            />
                            <InputError :message="form.errors.fakultas_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_prodi" class="label-isian">Kode Program Studi</Label>
                                <Input id="kode_prodi" v-model="form.kode_prodi" type="text" required />
                                <InputError :message="form.errors.kode_prodi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_prodi" class="label-isian">Nama Program Studi</Label>
                                <Input id="nama_prodi" v-model="form.nama_prodi" type="text" required />
                                <InputError :message="form.errors.nama_prodi" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="jenjang" class="label-isian">Jenjang</Label>
                                <select id="jenjang" v-model="form.jenjang" class="isian isian-pilih" required>
                                    <option value="">Pilih jenjang</option>
                                    <option v-for="item in jenjang" :key="item" :value="item">{{ item }}</option>
                                </select>
                                <InputError :message="form.errors.jenjang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tahun_berdiri" class="label-isian">Tahun Berdiri</Label>
                                <Input id="tahun_berdiri" v-model="form.tahun_berdiri" type="number" required />
                                <InputError :message="form.errors.tahun_berdiri" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="kaprodi" class="label-isian">Kaprodi</Label>
                            <SearchSelect
                                id="kaprodi"
                                v-model="form.kaprodi"
                                :options="props.dosen"
                                placeholder="Pilih kaprodi"
                                search-placeholder="Cari kaprodi"
                                required
                            />
                            <InputError :message="form.errors.kaprodi" />
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Akreditasi</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="status_akreditasi" class="label-isian">Status Akreditasi</Label>
                                <Input id="status_akreditasi" v-model="form.status_akreditasi" type="text" required />
                                <InputError :message="form.errors.status_akreditasi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="no_sk_akreditasi" class="label-isian">Nomor SK Akreditasi</Label>
                                <Input id="no_sk_akreditasi" v-model="form.no_sk_akreditasi" type="text" />
                                <InputError :message="form.errors.no_sk_akreditasi" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_akreditasi_mulai" class="label-isian">Tanggal Akreditasi Mulai</Label>
                                <DatePicker
                                    id="tanggal_akreditasi_mulai"
                                    v-model="form.tanggal_akreditasi_mulai"
                                    placeholder="Pilih tanggal mulai"
                                    :required="false"
                                />
                                <InputError :message="form.errors.tanggal_akreditasi_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_akreditasi_akhir" class="label-isian">Tanggal Akreditasi Akhir</Label>
                                <DatePicker
                                    id="tanggal_akreditasi_akhir"
                                    v-model="form.tanggal_akreditasi_akhir"
                                    placeholder="Pilih tanggal akhir"
                                    :required="false"
                                />
                                <InputError :message="form.errors.tanggal_akreditasi_akhir" />
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
