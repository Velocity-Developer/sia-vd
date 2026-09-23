<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';

const props = defineProps<{
    tahunAkademik: string;
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
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[720px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-[#e6e6e6] bg-white p-6 text-center shadow-sm">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-[#fdf1e9]">
                        <Lock class="size-6 text-[#dd5b00]" />
                    </div>
                    <h1 class="mt-4 text-[22px] font-bold leading-7 text-black">Pengisian KRS Terkunci</h1>
                    <p class="mt-2 text-sm leading-5 text-[#615d59]">
                        Tagihan semester {{ props.tahunAkademik }} belum lunas. Setelah pembayaran Anda diverifikasi bagian keuangan, halaman KRS
                        terbuka dengan sendirinya.
                    </p>

                    <div class="mt-5 rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3 text-left">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Total Tagihan</span>
                            <span class="text-lg font-bold text-black">{{ rupiah(props.total) }}</span>
                        </div>
                        <ul v-if="props.items.length" class="mt-2 space-y-1 border-t border-[#e6e6e6] pt-2">
                            <li v-for="(item, index) in props.items" :key="index" class="flex items-center justify-between text-sm text-[#31302e]">
                                <span>{{ item.nama }}</span>
                                <span>{{ rupiah(item.subtotal) }}</span>
                            </li>
                        </ul>
                    </div>

                    <p v-if="tanggal(props.batasKrs)" class="mt-3 text-sm font-medium text-[#dd5b00]">
                        Periode pengisian KRS berakhir {{ tanggal(props.batasKrs) }}. Selesaikan pembayaran sebelum tanggal tersebut.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <Link :href="route('mahasiswa.info-biaya-kuliah')">
                            <Button class="h-10 rounded-lg bg-[#0075de] px-5 text-sm font-medium text-white hover:bg-[#005bab]">
                                Lihat Info Biaya Kuliah
                            </Button>
                        </Link>
                        <Link :href="route('mahasiswa.dashboard')" class="text-sm font-medium text-[#615d59] hover:underline"
                            >Kembali ke Beranda</Link
                        >
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
