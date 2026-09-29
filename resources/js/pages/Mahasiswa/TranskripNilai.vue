<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';

type Item = { id: number; kode: string; nama: string; jenis: string; sks: number; nilai: string; diambil: number };
defineProps<{
    transkrip: Item[];
    ringkasan: { totalMatkul: number; totalSks: number; totalSksLulus: number; totalMutu: number; ipk: number | null };
}>();
</script>

<template>
    <Head title="Transkrip Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Transkrip Nilai', href: route('mahasiswa.transkrip') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Transkrip Nilai</h1>
                        <p class="deskripsi-halaman">Rekapitulasi seluruh hasil studi yang telah dinilai.</p>
                    </div>
                    <Button v-if="transkrip.length" as-child>
                        <a :href="route('mahasiswa.transkrip.download')">
                            <Download class="h-4 w-4" />
                            Download Transkrip
                        </a>
                    </Button>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-[#0075de] bg-[#0075de] p-6 text-white shadow-sm">
                        <p class="text-xs uppercase tracking-[0.08em] text-blue-100">IPK</p>
                        <p class="mt-2 text-3xl font-bold">{{ ringkasan.ipk?.toFixed(2) ?? '-' }}</p>
                    </div>
                    <div class="kartu p-6">
                        <p class="teks-bantu uppercase tracking-[0.08em]">Mata Kuliah</p>
                        <p class="mt-2 text-2xl font-bold text-black dark:text-foreground">{{ ringkasan.totalMatkul }}</p>
                    </div>
                    <div class="kartu p-6">
                        <p class="teks-bantu uppercase tracking-[0.08em]">SKS Lulus / Total SKS</p>
                        <p class="mt-2 text-2xl font-bold text-black dark:text-foreground">
                            {{ ringkasan.totalSksLulus }} / {{ ringkasan.totalSks }}
                        </p>
                    </div>
                </div>
                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[680px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Jenis Mata Kuliah</th>
                                    <th class="text-center">SKS</th>
                                    <th class="text-center">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in transkrip" :key="item.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <p class="font-medium text-black dark:text-foreground">{{ item.nama }}</p>
                                        <p class="teks-bantu">
                                            {{ item.kode }}<span v-if="item.diambil > 1"> · diambil {{ item.diambil }}x, nilai terbaik</span>
                                        </p>
                                    </td>
                                    <td>{{ item.jenis }}</td>
                                    <td class="text-center tabular-nums">{{ item.sks }}</td>
                                    <td class="text-center">
                                        <span
                                            class="inline-flex min-w-9 justify-center rounded-full bg-[#eaf4ff] px-2.5 py-1 text-xs font-bold text-[#0075de]"
                                            >{{ item.nilai }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!transkrip.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">Belum ada nilai pada transkrip.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
