<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type RuangShowProps = {
    id: number;
    kode_ruang: string;
    nama_ruang: string;
    kapasitas: number;
    detail: string | null;
};

const props = defineProps<{ ruang: RuangShowProps }>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    return String(val);
};
</script>

<template>
    <Head :title="`Detail ${props.ruang.nama_ruang}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Ruang', href: '#' }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Detail Ruang</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Ringkasan informasi ruang dan kapasitas.</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('admin.ruang.index')"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route('admin.ruang.edit', props.ruang.id)"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Ruang</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Ruang</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.ruang.kode_ruang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Nama Ruang</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.ruang.nama_ruang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kapasitas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.ruang.kapasitas) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Detail</dt>
                            <dd class="whitespace-pre-wrap break-words text-[15px] leading-5 text-black">{{ v(props.ruang.detail) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
