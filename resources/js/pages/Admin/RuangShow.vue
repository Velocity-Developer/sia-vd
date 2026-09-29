<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type RuangShowProps = {
    id: number;
    kode_ruang: string;
    nama_ruang: string;
    kapasitas: number;
    detail: string | null;
};

const props = defineProps<{ ruang: RuangShowProps }>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    return String(val);
};
</script>

<template>
    <Head :title="`Detail ${props.ruang.nama_ruang}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Ruang', href: '#' }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Ruang</h1>
                        <p class="deskripsi-halaman">Ringkasan informasi ruang dan kapasitas.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"><Link :href="route('admin.ruang.index')">Kembali</Link></Button>
                        <Button as-child><Link :href="route('admin.ruang.edit', props.ruang.id)">Edit</Link></Button>
                    </div>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Ruang</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Ruang</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.ruang.kode_ruang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Nama Ruang</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.ruang.nama_ruang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kapasitas</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.ruang.kapasitas) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="teks-bantu">Detail</dt>
                            <dd class="whitespace-pre-wrap break-words text-sm text-black dark:text-foreground">{{ v(props.ruang.detail) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
