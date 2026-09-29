<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

// Halaman cadangan `/dashboard`: hanya untuk akun yang tidak punya izin dashboard admin, dosen, maupun mahasiswa.
// Pengguna lain dialihkan ke berandanya masing-masing (lihat routes/web.php).
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Beranda', href: '/dashboard' }];
const page = usePage<SharedData>();
</script>

<template>
    <Head title="Beranda" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Selamat datang{{ page.props.auth?.user?.name ? `, ${page.props.auth.user.name}` : '' }}</h1>
                        <p class="deskripsi-halaman">Gunakan menu di samping untuk membuka halaman yang dapat Anda akses.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
