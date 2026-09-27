<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head } from '@inertiajs/vue3';
import { Paperclip } from 'lucide-vue-next';

type Bimbingan = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    judul: string;
    bidang: string;
    peran: string;
    pembimbing_lain: string | null;
    status: 'berjalan' | 'selesai';
    proposal_pengajuan_id: number | null;
    disahkan_at: string | null;
};

const props = defineProps<{ bimbingan: Bimbingan[] }>();
</script>

<template>
    <Head title="Bimbingan & Pendadaran" />
    <AppLayout :breadcrumbs="[{ title: 'Bimbingan & Pendadaran', href: route('dosen.bimbingan.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Bimbingan & Pendadaran</h1>
                    <p class="text-sm text-[#615d59]">Mahasiswa yang tugas akhirnya Anda bimbing.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tugas Akhir</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Peran</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="b in props.bimbingan" :key="b.id" class="align-top hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }} · {{ b.prodi }}</span>
                                    </td>
                                    <td class="max-w-[460px] px-4 py-3 text-sm text-[#31302e]">
                                        <span class="block text-[15px] font-medium text-black">{{ b.judul }}</span>
                                        <span class="block text-xs text-[#615d59]"
                                            >Bidang: {{ b.bidang }} · disahkan {{ formatTanggal(b.disahkan_at, false) }}</span
                                        >
                                        <a
                                            v-if="b.proposal_pengajuan_id"
                                            :href="route('berkas.pengajuan-akademik', [b.proposal_pengajuan_id, 'proposal'])"
                                            target="_blank"
                                            rel="noopener"
                                            class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                            ><Paperclip class="size-3" /> Proposal</a
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ b.peran }}
                                        <span v-if="b.pembimbing_lain" class="block text-xs text-[#a39e98]">bersama {{ b.pembimbing_lain }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="b.status === 'selesai' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f2f9ff] text-[#0075de]'"
                                            >{{ b.status === 'selesai' ? 'Selesai' : 'Berjalan' }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!props.bimbingan.length">
                                    <td colspan="4" class="px-4 py-14 text-center text-sm text-[#615d59]">Belum ada mahasiswa bimbingan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
