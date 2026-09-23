<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

type Item = { id: number; kode: string; nama: string; jenis: string; sks: number; nilai: string; diambil: number };
defineProps<{
    transkrip: Item[];
    ringkasan: { totalMatkul: number; totalSks: number; totalSksLulus: number; totalMutu: number; ipk: number | null };
}>();
</script>

<template>
    <Head title="Transkrip Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Transkrip Nilai', href: route('mahasiswa.transkrip') }]">
        <div class="min-h-full bg-[#f6f5f4] dark:bg-gray-950">
            <div class="mx-auto flex w-full max-w-[1100px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div>
                    <h1 class="text-[26px] font-bold tracking-[-0.625px] text-black dark:text-white">Transkrip Nilai</h1>
                    <p class="mt-1 text-sm text-[#615d59] dark:text-gray-400">Rekapitulasi seluruh hasil studi yang telah dinilai.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-[#0075de] bg-[#0075de] p-5 text-white shadow-sm">
                        <p class="text-xs uppercase tracking-[0.08em] text-blue-100">IPK</p>
                        <p class="mt-2 text-3xl font-bold">{{ ringkasan.ipk?.toFixed(2) ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</p>
                        <p class="mt-2 text-2xl font-bold text-black dark:text-white">{{ ringkasan.totalMatkul }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs uppercase tracking-[0.08em] text-[#a39e98]">SKS Lulus / Total SKS</p>
                        <p class="mt-2 text-2xl font-bold text-black dark:text-white">{{ ringkasan.totalSksLulus }} / {{ ringkasan.totalSks }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4] dark:border-gray-800 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Mata Kuliah</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Jenis Mata Kuliah</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-[#a39e98]">SKS</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-[#a39e98]">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6] dark:divide-gray-800">
                                <tr v-for="(item, index) in transkrip" :key="item.id" class="hover:bg-[#f6f5f4]/60 dark:hover:bg-gray-800">
                                    <td class="px-4 py-4 text-sm text-[#615d59]">{{ index + 1 }}</td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-black dark:text-white">{{ item.nama }}</p>
                                        <p class="text-xs text-[#8a8580]">
                                            {{ item.kode }}<span v-if="item.diambil > 1"> · diambil {{ item.diambil }}x, nilai terbaik</span>
                                        </p>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-[#615d59] dark:text-gray-300">{{ item.jenis }}</td>
                                    <td class="px-4 py-4 text-center text-sm text-[#31302e] dark:text-gray-200">{{ item.sks }}</td>
                                    <td class="px-4 py-4 text-center">
                                        <span
                                            class="inline-flex min-w-9 justify-center rounded-full bg-[#eaf4ff] px-2.5 py-1 text-sm font-bold text-[#0075de]"
                                            >{{ item.nilai }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!transkrip.length">
                                    <td colspan="5" class="px-4 py-16 text-center text-sm text-[#615d59]">Belum ada nilai pada transkrip.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
