<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title, href: '#' }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Halaman {{ title }} sedang disiapkan.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
