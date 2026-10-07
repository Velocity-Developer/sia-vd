<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Baris hak cipta + kredit pembuat; warna mengikuti elemen pembungkusnya.
const page = usePage<SharedData>();
const pemilik = computed(() => page.props.institusi?.nama_pt ?? page.props.name);
const tahun = new Date().getFullYear();
</script>

<template>
    <span>
        © {{ tahun }} {{ pemilik }}. Design by
        <a href="https://velocitydeveloper.com" target="_blank" rel="noopener" class="font-medium underline-offset-2 hover:underline"
            >Velocity Developer</a
        >
    </span>
</template>
