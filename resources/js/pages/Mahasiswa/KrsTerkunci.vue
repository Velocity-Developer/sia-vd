<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';

const props = defineProps<{
    tahunAkademik: string;
    status: string;
    alasanTolak: string | null;
    total: number;
    items: { nama: string; subtotal: number }[];
    batasKrs: string | null;
}>();

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
const tanggal = (nilai: string | null) => (nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(nilai)) : null);
</script>

<template>
    <Head title="Rencana Studi (KRS)" />
    <AppLayout :breadcrumbs="[{ title: 'Rencana Studi (KRS)', href: route('mahasiswa.krs') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kartu p-6 text-center">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[#fdf1e9]">
                        <Lock class="size-6 text-[#dd5b00]" />
                    </div>
                    <h1 class="judul-halaman mt-4">Pengisian KRS Terkunci</h1>
                    <p class="deskripsi-halaman mx-auto mt-2">
                        <template v-if="props.status === 'menunggu_verifikasi'">
                            Bukti bayar semester {{ props.tahunAkademik }} sudah Anda kirim dan sedang diperiksa bagian keuangan. Halaman KRS terbuka
                            dengan sendirinya setelah pembayaran dinyatakan lunas.
                        </template>
                        <template v-else>
                            Tagihan semester {{ props.tahunAkademik }} belum lunas. Unggah bukti bayar di Info Biaya Kuliah; setelah diverifikasi
                            bagian keuangan, halaman KRS terbuka dengan sendirinya.
                        </template>
                    </p>
                    <div v-if="props.status === 'ditolak'" class="alert-gagal mt-4" role="alert">
                        Bukti bayar sebelumnya ditolak: {{ props.alasanTolak }}. Silakan unggah ulang.
                    </div>

                    <div class="mt-5 rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3 text-left dark:border-border dark:bg-muted">
                        <div class="flex items-center justify-between">
                            <span class="teks-bantu font-medium uppercase tracking-[0.08em]">Total Tagihan</span>
                            <span class="text-lg font-bold text-black dark:text-foreground">{{ rupiah(props.total) }}</span>
                        </div>
                        <ul v-if="props.items.length" class="mt-2 space-y-1 border-t border-[#e6e6e6] pt-2 dark:border-border">
                            <li
                                v-for="(item, index) in props.items"
                                :key="index"
                                class="flex items-center justify-between text-sm text-[#31302e] dark:text-foreground"
                            >
                                <span>{{ item.nama }}</span>
                                <span class="tabular-nums">{{ rupiah(item.subtotal) }}</span>
                            </li>
                        </ul>
                    </div>

                    <p v-if="tanggal(props.batasKrs)" class="mt-3 text-sm font-medium text-[#dd5b00]">
                        Periode pengisian KRS berakhir {{ tanggal(props.batasKrs) }}. Selesaikan pembayaran sebelum tanggal tersebut.
                    </p>

                    <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                        <Button as-child variant="outline">
                            <Link :href="route('mahasiswa.dashboard')">Kembali ke Beranda</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="route('mahasiswa.info-biaya-kuliah')">Lihat Info Biaya Kuliah</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
