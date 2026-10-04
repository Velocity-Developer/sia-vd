<script setup lang="ts">
import IsiTranskrip, { type TranskripProps } from '@/components/IsiTranskrip.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';

defineProps<TranskripProps>();
</script>

<template>
    <Head title="Transkrip Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Transkrip Nilai', href: route('mahasiswa.transkrip') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Transkrip Nilai</h1>
                        <p class="deskripsi-halaman">Rekapitulasi seluruh hasil studi yang telah dinilai.</p>
                    </div>
                    <Button v-if="transkrip.length" as-child>
                        <a :href="route('mahasiswa.transkrip.download')">
                            <Download class="h-4 w-4" />
                            Download Transkrip
                        </a>
                    </Button>
                </div>
                <IsiTranskrip :transkrip="transkrip" :ringkasan="ringkasan" :kelulusan="kelulusan" />
            </div>
        </div>
    </AppLayout>
</template>
