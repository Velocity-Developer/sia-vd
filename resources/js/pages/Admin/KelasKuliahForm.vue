<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    kelasKuliah: Record<string, any> | null;
    dosens: { id: number; name: string }[];
    matkulGroups: { label: string; options: { id: number; name: string }[] }[];
    tahunAkademiks: { id: number; tahun: string; semester: string }[];
}>();

const title = `${props.kelasKuliah ? 'Edit' : 'Tambah'} Kelas Kuliah`;

const form = useForm({
    kode_kelas: props.kelasKuliah?.kode_kelas ?? '',
    tahun_ajaran: props.kelasKuliah?.tahun_ajaran ?? '',
    kapasitas: props.kelasKuliah?.kapasitas ?? '',
    dosen_id: props.kelasKuliah?.dosen_id ?? '',
    matkul_id: props.kelasKuliah?.matkul_id ?? '',
    tahun_akademik_id: props.kelasKuliah?.tahun_akademik_id ?? '',
});

const submit = () =>
    props.kelasKuliah ? form.put(route('admin.kelas-kuliah.update', props.kelasKuliah.id)) : form.post(route('admin.kelas-kuliah.store'));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Lengkapi kode kelas, tahun ajaran, kapasitas, dosen pengampu, dan mata kuliah.
                        </p>
                    </div>
                    <Link :href="route('admin.kelas-kuliah.index')"
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
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Kelas Kuliah</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_kelas" class="text-sm font-medium text-black">Kode Kelas</Label>
                                <Input id="kode_kelas" v-model="form.kode_kelas" type="text" placeholder="IF101-A" :class="inp" required />
                                <InputError :message="form.errors.kode_kelas" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tahun_akademik_id" class="text-sm font-medium text-black">Tahun Akademik</Label>
                                <select id="tahun_akademik_id" v-model="form.tahun_akademik_id" :class="sel" required>
                                    <option value="">Pilih tahun akademik</option>
                                    <option v-for="ta in props.tahunAkademiks" :key="ta.id" :value="ta.id">{{ ta.tahun }} {{ ta.semester }}</option>
                                </select>
                                <InputError :message="form.errors.tahun_akademik_id" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2 sm:max-w-[240px]">
                            <Label for="kapasitas" class="text-sm font-medium text-black">Kapasitas</Label>
                            <Input id="kapasitas" v-model="form.kapasitas" type="number" min="1" max="500" :class="inp" required />
                            <InputError :message="form.errors.kapasitas" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="dosen_id" class="text-sm font-medium text-black">Dosen Pengampu</Label>
                            <SearchSelect
                                id="dosen_id"
                                v-model="form.dosen_id"
                                :options="props.dosens"
                                placeholder="Pilih dosen"
                                search-placeholder="Cari dosen (nama / NIDN)"
                                required
                            />
                            <InputError :message="form.errors.dosen_id" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="matkul_id" class="text-sm font-medium text-black">Mata Kuliah</Label>
                            <SearchSelect
                                id="matkul_id"
                                v-model="form.matkul_id"
                                :groups="props.matkulGroups"
                                placeholder="Pilih mata kuliah"
                                search-placeholder="Cari mata kuliah"
                                required
                            />
                            <InputError :message="form.errors.matkul_id" />
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
