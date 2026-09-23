<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Item = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };
type Tagihan = { id: number; status: string; total: number; tanggal_lunas: string | null; items: Item[] };

const props = defineProps<{
    mahasiswa: { nama: string | null; nim: string | null; prodi: string | null; angkatan: number | null };
    tahunAkademik: string | null;
    tagihan: Tagihan | null;
    sks: number;
}>();

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
</script>

<template>
    <Head title="Rincian Tagihan" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Tagihan Mahasiswa', href: route('admin.tagihan.index') },
            { title: 'Rincian', href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">{{ props.mahasiswa.nama }}</h1>
                    <p class="text-sm text-[#615d59]">
                        {{ props.mahasiswa.nim }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan }} ·
                        {{ props.tahunAkademik ?? 'Tahun akademik tidak dipilih' }}
                    </p>
                </div>

                <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Status</p>
                            <span
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="props.tagihan?.status === 'lunas' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#fdf1e9] text-[#dd5b00]'"
                            >
                                {{ props.tagihan?.status === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Total Tagihan</p>
                            <p class="text-[22px] font-bold text-black">{{ rupiah(props.tagihan?.total ?? 0) }}</p>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-[#615d59]">SKS diambil pada semester ini: {{ props.sks }} SKS.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Komponen</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nominal</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jumlah</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.tagihan?.items ?? []" :key="index">
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ item.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.cara_hitung === 'per_sks' ? 'Per SKS' : 'Tetap' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ rupiah(item.nominal_satuan) }}</td>
                                    <td class="px-4 py-3 text-center text-[15px] text-[#31302e]">{{ item.jumlah }}</td>
                                    <td class="px-4 py-3 text-right text-[15px] font-medium text-black">{{ rupiah(item.subtotal) }}</td>
                                </tr>
                                <tr v-if="!props.tagihan?.items?.length">
                                    <td colspan="4" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tagihan semester ini belum diterbitkan untuk mahasiswa ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Link :href="route('admin.tagihan.index')" class="text-sm font-medium text-[#0075de] hover:underline"
                    >← Kembali ke daftar tagihan</Link
                >
            </div>
        </div>
    </AppLayout>
</template>
