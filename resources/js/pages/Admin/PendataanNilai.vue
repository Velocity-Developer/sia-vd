<script setup lang="ts">
import DaftarMahasiswaNilai, { type DaftarMahasiswaNilaiProps } from '@/components/DaftarMahasiswaNilai.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';

const props = defineProps<DaftarMahasiswaNilaiProps>();
</script>

<template>
    <Head title="Pendataan Nilai Akhir" />
    <AppLayout :breadcrumbs="[{ title: 'Pendataan Nilai Akhir', href: route('admin.pendataan-nilai.index') }]">
        <div class="halaman">
            <DaftarMahasiswaNilai
                v-bind="props"
                rute="admin.pendataan-nilai"
                rute-aksi="admin.pendataan-nilai.show"
                judul="Pendataan Nilai Akhir"
                deskripsi="Rekap konversi nilai akhir angka menjadi huruf sesuai Bobot Nilai, beserta status validasinya, per mata kuliah yang pernah diambil mahasiswa."
                label-aksi="Lihat"
                :ikon-aksi="Eye"
            />
        </div>
    </AppLayout>
</template>
