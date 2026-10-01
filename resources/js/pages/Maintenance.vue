<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { type Maintenance } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Clock, LogOut } from 'lucide-vue-next';

// Ditampilkan (HTTP 503) ke dosen/mahasiswa yang sesinya masih terbuka saat mode maintenance aktif.
defineProps<{ maintenance: Maintenance }>();
</script>

<template>
    <AuthBase title="Sedang Dalam Pemeliharaan" description="Untuk sementara sistem belum bisa dipakai.">
        <Head title="Pemeliharaan Sistem" />

        <div class="flex flex-col gap-5">
            <p class="whitespace-pre-line text-[#31302e] dark:text-foreground">{{ maintenance.pesan }}</p>

            <div v-if="maintenance.perkiraan_selesai" class="alert-info flex items-center gap-2" role="status">
                <Clock class="size-4 shrink-0" />
                <span>Perkiraan selesai: {{ maintenance.perkiraan_selesai }}</span>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Button as="a" href="" class="flex-1">Coba Lagi</Button>
                <Button variant="outline" as-child class="flex-1">
                    <Link method="post" :href="route('logout')" as="button"><LogOut /> Keluar</Link>
                </Button>
            </div>
        </div>
    </AuthBase>
</template>
