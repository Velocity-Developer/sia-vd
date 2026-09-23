<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type ProgramStudiShowProps = {
    id: number;
    kode_prodi: string;
    nama_prodi: string;
    jenjang: string;
    status_akreditasi: string;
    no_sk_akreditasi?: string | null;
    tanggal_akreditasi_mulai: string;
    tanggal_akreditasi_akhir: string;
    tahun_berdiri: number;
    fakultas?: { id: number; kode_fakultas: string; nama_fakultas: string; dekan?: { user?: { name?: string } } | null } | null;
    ketuaProgramStudi?: { user?: { name?: string } } | null;
    ketua_program_studi?: { user?: { name?: string } } | null;
};

const props = defineProps<{ programStudi: ProgramStudiShowProps }>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    if (typeof val === 'string') {
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 10);
        return val;
    }
    return String(val);
};

const kaprodi = () => props.programStudi.ketuaProgramStudi?.user?.name ?? props.programStudi.ketua_program_studi?.user?.name ?? '-';
const fakultas = () => (props.programStudi as any).fakultas ?? null;
</script>

<template>
    <Head :title="`Detail ${props.programStudi.nama_prodi}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Program Studi', href: '#' }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Detail Program Studi</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Ringkasan informasi program studi, fakultas induk, dan pimpinan prodi.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('admin.program-studi.index')"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route('admin.program-studi.edit', props.programStudi.id)"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Program Studi</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Prodi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.programStudi.kode_prodi) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Nama Prodi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.programStudi.nama_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Jenjang</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.programStudi.jenjang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Status Akreditasi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.programStudi.status_akreditasi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">No. SK Akreditasi</dt>
                            <dd class="break-words break-all text-[15px] font-medium leading-5 text-black">
                                {{ v(props.programStudi.no_sk_akreditasi) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Tanggal Akreditasi Mulai</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">
                                {{ v(props.programStudi.tanggal_akreditasi_mulai) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Tanggal Akreditasi Akhir</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">
                                {{ v(props.programStudi.tanggal_akreditasi_akhir) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Tahun Berdiri</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.programStudi.tahun_berdiri) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Fakultas & Pimpinan</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Fakultas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(fakultas()?.nama_fakultas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Fakultas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(fakultas()?.kode_fakultas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Dekan Fakultas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(fakultas()?.dekan?.user?.name) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kaprodi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(kaprodi()) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
