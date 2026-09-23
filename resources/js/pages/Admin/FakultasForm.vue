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

const props = defineProps<{ fakultas: Record<string, any> | null; dosen: { id: number; name: string }[] }>();

const title = `${props.fakultas ? 'Edit' : 'Tambah'} Fakultas`;

const form = useForm({
    kode_fakultas: props.fakultas?.kode_fakultas ?? '',
    nama_fakultas: props.fakultas?.nama_fakultas ?? '',
    dekan_id: props.fakultas?.dekan_id ?? '',
    tanggal_berdiri: props.fakultas?.tanggal_berdiri?.slice(0, 10) ?? '',
    no_telp: props.fakultas?.no_telp ?? '',
    email: props.fakultas?.email ?? '',
});

const submit = () => (props.fakultas ? form.put(route('admin.fakultas.update', props.fakultas.id)) : form.post(route('admin.fakultas.store')));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Fakultas', href: route('admin.fakultas.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Lengkapi data fakultas. Semua field wajib diisi.</p>
                    </div>
                    <Link :href="route('admin.fakultas.index')"
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

                <form
                    @submit.prevent="submit"
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Fakultas</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="kode_fakultas" class="text-sm font-medium text-black">Kode Fakultas</Label>
                            <Input id="kode_fakultas" v-model="form.kode_fakultas" type="text" :class="inp" required />
                            <InputError :message="form.errors.kode_fakultas" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nama_fakultas" class="text-sm font-medium text-black">Nama Fakultas</Label>
                            <Input id="nama_fakultas" v-model="form.nama_fakultas" type="text" :class="inp" required />
                            <InputError :message="form.errors.nama_fakultas" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="dekan_id" class="text-sm font-medium text-black">Dekan</Label>
                        <SearchSelect
                            id="dekan_id"
                            v-model="form.dekan_id"
                            :options="props.dosen"
                            placeholder="Pilih dekan"
                            search-placeholder="Cari dekan"
                            required
                        />
                        <InputError :message="form.errors.dekan_id" />
                    </div>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tanggal_berdiri" class="text-sm font-medium text-black">Tanggal Berdiri</Label>
                            <DatePicker id="tanggal_berdiri" v-model="form.tanggal_berdiri" placeholder="Pilih tanggal berdiri" />
                            <InputError :message="form.errors.tanggal_berdiri" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="no_telp" class="text-sm font-medium text-black">Nomor Telepon</Label>
                            <Input id="no_telp" v-model="form.no_telp" type="text" :class="inp" required />
                            <InputError :message="form.errors.no_telp" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="email" class="text-sm font-medium text-black">Email</Label>
                        <Input id="email" v-model="form.email" type="email" :class="inp" required />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
