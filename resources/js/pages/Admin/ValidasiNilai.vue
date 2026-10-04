<script setup lang="ts">
import DaftarMahasiswaNilai, { type DaftarMahasiswaNilaiProps } from '@/components/DaftarMahasiswaNilai.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<DaftarMahasiswaNilaiProps>();
</script>

<template>
    <Head title="Validasi Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Validasi Nilai', href: route('admin.validasi-nilai.index') }]">
        <div class="halaman">
            <DaftarMahasiswaNilai
                v-bind="props"
                rute="admin.validasi-nilai"
                judul="Validasi Nilai"
                deskripsi="Pilih mahasiswa untuk memvalidasi nilai akhirnya per mata kuliah dalam satu tahun akademik."
                label-aksi="Validasi"
            />
        </div>
    </AppLayout>
</template>
