<script setup lang="ts">
import DaftarMahasiswaNilai, { type DaftarMahasiswaNilaiProps } from '@/components/DaftarMahasiswaNilai.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<DaftarMahasiswaNilaiProps>();
</script>

<template>
    <Head title="Detail Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Nilai', href: route('admin.detail-nilai.index') }]">
        <div class="halaman">
            <DaftarMahasiswaNilai
                v-bind="props"
                rute="admin.detail-nilai"
                judul="Detail Nilai"
                deskripsi="Nilai tiap mahasiswa per mata kuliah dalam satu tahun akademik."
                label-aksi="Detail"
            />
        </div>
    </AppLayout>
</template>
