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
    <Head title="Informasi & Pengumuman" />
    <AppLayout :breadcrumbs="[{ title: 'Informasi & Pengumuman', href: route('mahasiswa.info-kuliah') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Informasi & Pengumuman</h1>
                        <p class="deskripsi-halaman">Informasi, pengumuman, dan berkas perkuliahan.</p>
                    </div>
                </div>
                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[640px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Informasi</th>
                                    <th>File</th>
                                    <th>Tanggal Upload</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.infoKuliahs.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.infoKuliahs.from ?? 1) + index }}</td>
                                    <td class="whitespace-pre-line">{{ item.information }}</td>
                                    <td>
                                        <a
                                            :href="route('berkas.info-kuliah', item.id)"
                                            target="_blank"
                                            rel="noopener"
                                            class="font-medium text-[#0075de] hover:underline"
                                            >Lihat file</a
                                        >
                                    </td>
                                    <td>{{ formatDateTime(item.created_at) ?? '-' }}</td>
                                </tr>
                                <tr v-if="!props.infoKuliahs.data.length" class="baris-kosong">
                                    <td colspan="4" class="tabel-kosong">Belum ada informasi & pengumuman.</td>
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
