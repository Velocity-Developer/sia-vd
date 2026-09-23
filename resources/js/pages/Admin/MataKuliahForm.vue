<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    mataKuliah: Record<string, any> | null;
    programStudis: { id: number; nama_prodi: string; jenjang: string; nama_fakultas: string }[];
}>();

const groupedProdi = computed(() => {
    const groups: Record<string, { id: number; name: string }[]> = {};
    for (const item of props.programStudis) {
        const key = item.nama_fakultas ?? 'Fakultas Lainnya';
        (groups[key] ??= []).push({ id: item.id, name: `${item.jenjang} - ${item.nama_prodi}` });
    }
    return Object.entries(groups).map(([label, options]) => ({ label, options }));
});

const title = `${props.mataKuliah ? 'Edit' : 'Tambah'} Mata Kuliah`;

const form = useForm({
    kode_matkul: props.mataKuliah?.kode_matkul ?? '',
    nama_matkul: props.mataKuliah?.nama_matkul ?? '',
    sks: props.mataKuliah?.sks ?? '',
    semester: props.mataKuliah?.semester ?? '',
    jenis: props.mataKuliah?.jenis ?? '',
    prodi_id: props.mataKuliah?.prodi_id ?? '',
});

const submit = () =>
    props.mataKuliah ? form.put(route('admin.mata-kuliah.update', props.mataKuliah.id)) : form.post(route('admin.mata-kuliah.store'));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Mata Kuliah', href: route('admin.mata-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Lengkapi kode, nama, SKS, semester, jenis, dan program studi.</p>
                    </div>
                    <Link :href="route('admin.mata-kuliah.index')"
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
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Mata Kuliah</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="prodi_id" class="text-sm font-medium text-black">Program Studi</Label>
                            <SearchSelect
                                id="prodi_id"
                                v-model="form.prodi_id"
                                :groups="groupedProdi"
                                placeholder="Pilih program studi"
                                search-placeholder="Cari program studi"
                                required
                            />
                            <InputError :message="form.errors.prodi_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_matkul" class="text-sm font-medium text-black">Kode Mata Kuliah</Label>
                                <Input id="kode_matkul" v-model="form.kode_matkul" type="text" :class="inp" required />
                                <InputError :message="form.errors.kode_matkul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_matkul" class="text-sm font-medium text-black">Nama Mata Kuliah</Label>
                                <Input id="nama_matkul" v-model="form.nama_matkul" type="text" :class="inp" required />
                                <InputError :message="form.errors.nama_matkul" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="sks" class="text-sm font-medium text-black">SKS</Label>
                                <Input id="sks" v-model="form.sks" type="number" min="1" max="6" :class="inp" required />
                                <InputError :message="form.errors.sks" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="semester" class="text-sm font-medium text-black">Semester</Label>
                                <Input id="semester" v-model="form.semester" type="number" min="1" max="14" :class="inp" required />
                                <InputError :message="form.errors.semester" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jenis" class="text-sm font-medium text-black">Jenis</Label>
                                <select id="jenis" v-model="form.jenis" :class="sel" required>
                                    <option value="">Pilih jenis</option>
                                    <option value="Wajib">Wajib</option>
                                    <option value="Pilihan">Pilihan</option>
                                </select>
                                <InputError :message="form.errors.jenis" />
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
