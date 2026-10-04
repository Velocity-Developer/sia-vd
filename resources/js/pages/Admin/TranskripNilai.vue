<script setup lang="ts">
import DaftarMahasiswaNilai, { type DaftarMahasiswaNilaiProps } from '@/components/DaftarMahasiswaNilai.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<DaftarMahasiswaNilaiProps>();
</script>

<template>
    <Head title="Transkrip Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Transkrip Nilai', href: route('admin.transkrip-nilai.index') }]">
        <div class="halaman">
            <DaftarMahasiswaNilai
                v-bind="props"
                rute="admin.transkrip-nilai"
                rute-unduh="admin.transkrip-nilai.download"
                judul="Transkrip Nilai"
                deskripsi="Rekapitulasi seluruh nilai mahasiswa: lihat detail atau unduh PDF transkrip."
                label-aksi="Detail"
            />
        </div>
    </AppLayout>
</template>
