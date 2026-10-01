<script setup lang="ts">
import { Button } from '@/components/ui/button';
import PmbLayout from '@/layouts/PmbLayout.vue';
import { rupiah } from '@/lib/tagihanRemidi';
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';

const props = defineProps<{
    pendaftar: { nomor_pendaftaran: string; nama: string; email: string; program_studi: string };
    periode: {
        kode: string;
        tanggal_usm_mulai: string;
        tanggal_usm_selesai: string;
        tanggal_her: string;
        biaya_pendaftaran: number;
        tanggal_pembayaran_mulai: string;
        tanggal_pembayaran_selesai: string;
    };
}>();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
const cetak = () => window.print();
const rentang = (awal: string, akhir: string) => (awal === akhir ? formatDate(awal) : `${formatDate(awal)} – ${formatDate(akhir)}`);
</script>

<template>
    <Head title="Pendaftaran Terkirim" />
    <PmbLayout>
        <div class="kartu p-6 sm:p-10">
            <div class="flex flex-col items-center text-center">
                <CircleCheck class="size-12 text-[#1aae39]" />
                <h1 class="judul-halaman mt-3">Pendaftaran Terkirim</h1>
                <p class="deskripsi-halaman max-w-[520px]">
                    Terima kasih, {{ props.pendaftar.nama }}. Simpan nomor pendaftaran di bawah ini; nomor ini dipakai untuk pembayaran dan pengumuman
                    hasil seleksi.
                </p>
                <p
                    class="mt-5 rounded-xl bg-[#f6f5f4] px-6 py-3 font-mono text-2xl font-semibold tracking-wide text-black dark:bg-muted dark:text-foreground"
                >
                    {{ props.pendaftar.nomor_pendaftaran }}
                </p>
            </div>

            <dl class="mx-auto mt-8 grid max-w-[560px] gap-x-6 gap-y-3 text-sm sm:grid-cols-[200px_1fr]">
                <dt class="text-[#615d59]">Program studi</dt>
                <dd class="font-medium text-black dark:text-foreground">{{ props.pendaftar.program_studi }}</dd>
                <dt class="text-[#615d59]">Biaya pendaftaran</dt>
                <dd class="font-medium text-black dark:text-foreground">{{ rupiah(props.periode.biaya_pendaftaran) }}</dd>
                <dt class="text-[#615d59]">Masa pembayaran</dt>
                <dd class="font-medium text-black dark:text-foreground">
                    {{ rentang(props.periode.tanggal_pembayaran_mulai, props.periode.tanggal_pembayaran_selesai) }}
                </dd>
                <dt class="text-[#615d59]">Ujian saringan masuk (USM)</dt>
                <dd class="font-medium text-black dark:text-foreground">
                    {{ rentang(props.periode.tanggal_usm_mulai, props.periode.tanggal_usm_selesai) }}
                </dd>
                <dt class="text-[#615d59]">Her-registrasi</dt>
                <dd class="font-medium text-black dark:text-foreground">{{ formatDate(props.periode.tanggal_her) }}</dd>
            </dl>

            <div class="mt-8 flex justify-center gap-2 print:hidden">
                <Button variant="outline" @click="cetak">Cetak</Button>
                <Button as-child><Link :href="route('login')">Ke Halaman Masuk</Link></Button>
            </div>
        </div>
    </PmbLayout>
</template>
