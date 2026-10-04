<script setup lang="ts">
import IsiTranskrip, { type TranskripProps } from '@/components/IsiTranskrip.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';

const props = defineProps<
    TranskripProps & { mahasiswa: { id: number; nim: string | null; nama: string | null; prodi: string | null; angkatan: string | null } }
>();
</script>

<template>
    <Head :title="`Transkrip ${props.mahasiswa.nama ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Transkrip Nilai', href: route('admin.transkrip-nilai.index') },
            { title: props.mahasiswa.nim ?? '-', href: route('admin.transkrip-nilai.show', props.mahasiswa.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.mahasiswa.nama }}</h1>
                        <p class="deskripsi-halaman">
                            NIM {{ props.mahasiswa.nim ?? '-' }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan ?? '-' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <Link :href="route('admin.transkrip-nilai.index')"><ArrowLeft class="size-4" /> Kembali</Link>
                        </Button>
                        <Button as-child>
                            <a :href="route('admin.transkrip-nilai.download', props.mahasiswa.id)"><Download class="size-4" /> Download Transkrip</a>
                        </Button>
                    </div>
                </div>

                <IsiTranskrip :transkrip="props.transkrip" :ringkasan="props.ringkasan" :kelulusan="props.kelulusan" />
            </div>
        </div>
    </AppLayout>
</template>
