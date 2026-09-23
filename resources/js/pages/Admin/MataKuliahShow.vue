<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type MataKuliahShowProps = {
    id: number;
    kode_matkul: string;
    nama_matkul: string;
    sks: number;
    semester: number;
    jenis: string;
    prodi?: {
        id: number;
        kode_prodi: string;
        nama_prodi: string;
        jenjang: string;
        fakultas?: { kode_fakultas: string; nama_fakultas: string } | null;
    } | null;
};

const props = defineProps<{ mataKuliah: MataKuliahShowProps }>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    if (typeof val === 'string') {
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 10);
        return val;
    }
    return String(val);
};

const prodi = () => (props.mataKuliah as any).prodi ?? null;
</script>

<template>
    <Head :title="`Detail ${props.mataKuliah.nama_matkul}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Mata Kuliah', href: '#' }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Detail Mata Kuliah</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Ringkasan informasi mata kuliah, program studi, dan fakultas induk.</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('admin.mata-kuliah.index')"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route('admin.mata-kuliah.edit', props.mataKuliah.id)"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Mata Kuliah</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Mata Kuliah</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.mataKuliah.kode_matkul) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Nama Mata Kuliah</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.mataKuliah.nama_matkul) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">SKS</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.mataKuliah.sks) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Semester</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.mataKuliah.semester) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Jenis</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.mataKuliah.jenis) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Program Studi & Fakultas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Program Studi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(prodi()?.nama_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Prodi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(prodi()?.kode_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Jenjang</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(prodi()?.jenjang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Fakultas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(prodi()?.fakultas?.nama_fakultas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Fakultas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(prodi()?.fakultas?.kode_fakultas) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
