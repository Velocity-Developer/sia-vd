<script setup lang="ts">
import DaftarMahasiswaNilai, { type DaftarMahasiswaNilaiProps } from '@/components/DaftarMahasiswaNilai.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<DaftarMahasiswaNilaiProps>();
</script>

<template>
    <Head title="KHS" />
    <AppLayout :breadcrumbs="[{ title: 'KHS', href: route('admin.khs.index') }]">
        <div class="halaman">
            <DaftarMahasiswaNilai
                v-bind="props"
                rute="admin.khs"
                rute-unduh="admin.khs.download"
                judul="Kartu Hasil Studi"
                deskripsi="KHS mahasiswa per tahun akademik: lihat detail atau unduh PDF."
                label-aksi="Detail"
            />
        </div>
    </AppLayout>
</template>
