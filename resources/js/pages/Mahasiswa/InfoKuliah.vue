<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

type Item = { id: number; information: string; file: string; created_at: string };
type Pagination = { data: Item[]; links?: { url: string | null; label: string; active: boolean }[]; total?: number; from?: number | null };
const props = defineProps<{ infoKuliahs: Pagination }>();

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};
</script>

<template>
    <Head title="Info Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Info Kuliah', href: route('mahasiswa.info-kuliah') }]">
        <div class="min-h-full bg-[#f6f5f4] dark:bg-gray-950">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black dark:text-white">Info Kuliah</h1>
                    <p class="text-sm leading-5 text-[#615d59] dark:text-gray-400">Informasi dan berkas perkuliahan.</p>
                </div>
                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4] dark:border-gray-800 dark:bg-gray-800">
                                    <th class="w-16 px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">File</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tanggal Upload</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6] dark:divide-gray-800">
                                <tr
                                    v-for="(item, index) in props.infoKuliahs.data"
                                    :key="item.id"
                                    class="transition-colors hover:bg-[#f6f5f4]/60 dark:hover:bg-gray-800"
                                >
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#615d59] dark:text-gray-400">
                                        {{ (props.infoKuliahs.from ?? 1) + index }}
                                    </td>
                                    <td class="whitespace-pre-line px-4 py-3 text-[15px] leading-5 text-[#31302e] dark:text-gray-200">
                                        {{ item.information }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a
                                            :href="route('berkas.info-kuliah', item.id)"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-[15px] font-medium text-[#0075de] hover:underline"
                                            >Lihat file</a
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e] dark:text-gray-200">
                                        {{ formatDateTime(item.created_at) ?? '-' }}
                                    </td>
                                </tr>
                                <tr v-if="!props.infoKuliahs.data.length">
                                    <td colspan="4" class="px-4 py-16 text-center text-sm text-[#615d59] dark:text-gray-400">
                                        Belum ada info kuliah.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="props.infoKuliahs.links ?? []" :total="props.infoKuliahs.data.length" />
            </div>
        </div>
    </AppLayout>
</template>
