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

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Program Studi', href: route('admin.program-studi.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Lengkapi data program studi, akreditasi, dan kaprodi.</p>
                    </div>
                    <Link :href="route('admin.program-studi.index')"
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
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Program Studi</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="fakultas_id" class="text-sm font-medium text-black">Fakultas</Label>
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
                                <Label for="kode_prodi" class="text-sm font-medium text-black">Kode Program Studi</Label>
                                <Input id="kode_prodi" v-model="form.kode_prodi" type="text" :class="inp" required />
                                <InputError :message="form.errors.kode_prodi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_prodi" class="text-sm font-medium text-black">Nama Program Studi</Label>
                                <Input id="nama_prodi" v-model="form.nama_prodi" type="text" :class="inp" required />
                                <InputError :message="form.errors.nama_prodi" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="jenjang" class="text-sm font-medium text-black">Jenjang</Label>
                                <select id="jenjang" v-model="form.jenjang" :class="sel" required>
                                    <option value="">Pilih jenjang</option>
                                    <option v-for="item in jenjang" :key="item" :value="item">{{ item }}</option>
                                </select>
                                <InputError :message="form.errors.jenjang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tahun_berdiri" class="text-sm font-medium text-black">Tahun Berdiri</Label>
                                <Input id="tahun_berdiri" v-model="form.tahun_berdiri" type="number" :class="inp" required />
                                <InputError :message="form.errors.tahun_berdiri" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="kaprodi" class="text-sm font-medium text-black">Kaprodi</Label>
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

                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Akreditasi</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="status_akreditasi" class="text-sm font-medium text-black">Status Akreditasi</Label>
                                <Input id="status_akreditasi" v-model="form.status_akreditasi" type="text" :class="inp" required />
                                <InputError :message="form.errors.status_akreditasi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="no_sk_akreditasi" class="text-sm font-medium text-black">Nomor SK Akreditasi</Label>
                                <Input id="no_sk_akreditasi" v-model="form.no_sk_akreditasi" type="text" :class="inp" />
                                <InputError :message="form.errors.no_sk_akreditasi" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_akreditasi_mulai" class="text-sm font-medium text-black">Tanggal Akreditasi Mulai</Label>
                                <DatePicker
                                    id="tanggal_akreditasi_mulai"
                                    v-model="form.tanggal_akreditasi_mulai"
                                    placeholder="Pilih tanggal mulai"
                                    :required="false"
                                />
                                <InputError :message="form.errors.tanggal_akreditasi_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_akreditasi_akhir" class="text-sm font-medium text-black">Tanggal Akreditasi Akhir</Label>
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

                    <div class="flex justify-end pt-2">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
