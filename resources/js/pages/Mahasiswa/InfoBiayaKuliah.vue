<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

type Item = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };
type Tagihan = { id: number; tahun_akademik: string; status: string; total: number; tanggal_lunas: string | null; items: Item[] };

const props = defineProps<{ semesterBerjalan: Tagihan | null; tahunAktif: string | null; riwayat: Tagihan[] }>();

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
const tanggal = (nilai: string | null) => (nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(nilai)) : null);
</script>

<template>
    <Head title="Info Biaya Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Info Biaya Kuliah', href: route('mahasiswa.info-biaya-kuliah') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Info Biaya Kuliah</h1>
                    <p class="text-sm text-[#615d59]">Tagihan semester berjalan dan riwayat pembayaran Anda.</p>
                </div>

                <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Semester Berjalan</p>
                            <p class="text-lg font-semibold text-black">{{ props.semesterBerjalan?.tahun_akademik ?? props.tahunAktif ?? '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Total Tagihan</p>
                            <p class="text-[22px] font-bold text-black">{{ rupiah(props.semesterBerjalan?.total ?? 0) }}</p>
                            <span
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="props.semesterBerjalan?.status === 'lunas' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#fdf1e9] text-[#dd5b00]'"
                            >
                                {{ props.semesterBerjalan?.status === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                            </span>
                        </div>
                    </div>

                    <p v-if="props.semesterBerjalan?.tanggal_lunas" class="mt-3 text-sm text-[#615d59]">
                        Dinyatakan lunas pada {{ tanggal(props.semesterBerjalan.tanggal_lunas) }}.
                    </p>

                    <div v-if="props.semesterBerjalan?.items?.length" class="mt-4 overflow-hidden rounded-lg border border-[#e6e6e6]">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[560px] text-left">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Komponen</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nominal</th>
                                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                                            Jumlah
                                        </th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="(item, index) in props.semesterBerjalan.items" :key="index">
                                        <td class="px-4 py-2.5 text-[15px] text-black">
                                            <span class="block">{{ item.nama }}</span>
                                            <span v-if="item.cara_hitung === 'per_sks'" class="block text-xs text-[#a39e98]">
                                                {{ item.jumlah }} SKS × {{ rupiah(item.nominal_satuan) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-[15px] text-[#31302e]">{{ rupiah(item.nominal_satuan) }}</td>
                                        <td class="px-4 py-2.5 text-center text-[15px] text-[#31302e]">{{ item.jumlah }}</td>
                                        <td class="px-4 py-2.5 text-right text-[15px] font-medium text-black">{{ rupiah(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-else class="mt-4 rounded-lg border border-dashed border-[#e6e6e6] px-4 py-6 text-center text-sm text-[#615d59]">
                        Tagihan semester ini belum diterbitkan. Hubungi bagian keuangan bila Anda merasa seharusnya sudah ada.
                    </p>
                </div>

                <div v-if="props.riwayat.length" class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="border-b border-[#e6e6e6] px-5 py-4">
                        <h2 class="text-lg font-semibold text-black">Riwayat Semester Sebelumnya</h2>
                    </div>
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Semester</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Total</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tanggal Lunas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="tagihan in props.riwayat" :key="tagihan.id">
                                    <td class="px-4 py-3 text-[15px] font-medium text-black">{{ tagihan.tahun_akademik }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ rupiah(tagihan.total) }}</td>
                                    <td class="px-4 py-3 text-[15px]">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="tagihan.status === 'lunas' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#fdf1e9] text-[#dd5b00]'"
                                        >
                                            {{ tagihan.status === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ tanggal(tagihan.tanggal_lunas) ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
