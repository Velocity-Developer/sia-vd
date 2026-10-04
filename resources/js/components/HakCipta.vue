<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Satu teks hak cipta untuk semua halaman; nama mengikuti Pengaturan Sistem → Institusi, tahun mengikuti tahun berjalan.
const page = usePage<SharedData>();
const nama = computed(() => page.props.institusi?.nama_pt ?? page.props.name);
const tahun = new Date().getFullYear();
</script>

<template>
    <p>
        ©{{ tahun }} {{ nama }}. All Rights Reserved. Design by
        <a href="https://velocitydeveloper.com" target="_blank" rel="noopener" class="underline-offset-2 hover:underline">Velocity Developer</a>
    </p>
</template>
