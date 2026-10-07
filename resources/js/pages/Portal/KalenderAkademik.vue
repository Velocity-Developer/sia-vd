<script setup lang="ts">
import PortalLayout from '@/layouts/PortalLayout.vue';
import { Head } from '@inertiajs/vue3';

type Kegiatan = { kegiatan: string; mulai: string; selesai: string | null };

const props = defineProps<{ tahunAkademik: string | null; kegiatan: Kegiatan[] }>();

const tanggal = (nilai: string) =>
    new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${nilai}T00:00:00`));
const rentang = (item: Kegiatan) =>
    item.selesai && item.selesai !== item.mulai ? `${tanggal(item.mulai)} – ${tanggal(item.selesai)}` : tanggal(item.mulai);
</script>

<template>
    <Head title="Kalender Akademik" />
    <PortalLayout>
        <div class="mx-auto w-full max-w-[880px] px-4 py-8 sm:py-12">
            <h1 class="judul-halaman">Kalender Akademik</h1>
            <p class="deskripsi-halaman">
                {{ props.tahunAkademik ? `Tahun Akademik ${props.tahunAkademik}` : 'Jadwal kegiatan akademik semester berjalan.' }}
            </p>

            <div class="tabel-wadah mt-6">
                <div class="tabel-gulir">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th>Kegiatan</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.kegiatan" :key="item.kegiatan">
                                <td class="font-medium">{{ item.kegiatan }}</td>
                                <td>{{ rentang(item) }}</td>
                            </tr>
                            <tr v-if="!props.kegiatan.length" class="baris-kosong">
                                <td colspan="2" class="tabel-kosong">Kalender akademik semester ini belum tersedia.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
