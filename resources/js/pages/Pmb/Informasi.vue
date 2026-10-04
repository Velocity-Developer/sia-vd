<script setup lang="ts">
import { Button } from '@/components/ui/button';
import PmbLayout from '@/layouts/PmbLayout.vue';
import { rupiah } from '@/lib/tagihanRemidi';
import { Head, Link } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';

type Periode = {
    kode: string;
    tahun_angkatan: number;
    tanggal_buka: string;
    tanggal_tutup: string;
    tanggal_usm_mulai: string | null;
    tanggal_usm_selesai: string | null;
    tanggal_her: string | null;
    biaya_pendaftaran: number | null;
    tanggal_pembayaran_mulai: string | null;
    tanggal_pembayaran_selesai: string | null;
    penuh: boolean;
};

const props = defineProps<{
    judul: string;
    pengantar: string | null;
    bagian: { judul: string; isi: string }[];
    periode: Periode | null;
}>();

const tanggal = (value: string) =>
    new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
const rentang = (awal: string | null, akhir: string | null) =>
    !awal ? '-' : !akhir || awal.slice(0, 10) === akhir.slice(0, 10) ? tanggal(awal) : `${tanggal(awal)} – ${tanggal(akhir)}`;
</script>

<template>
    <Head :title="props.judul" />
    <PmbLayout>
        <div class="flex flex-col gap-6">
            <section class="kartu p-6 sm:p-10">
                <h1 class="judul-halaman">{{ props.judul }}</h1>
                <p v-if="props.pengantar" class="deskripsi-halaman mt-2 whitespace-pre-line">{{ props.pengantar }}</p>

                <div v-if="props.periode" class="mt-6 rounded-xl bg-[#f6f5f4] p-5 dark:bg-muted">
                    <p class="text-sm font-semibold text-black dark:text-foreground">
                        Pendaftaran dibuka · Angkatan {{ props.periode.tahun_angkatan }}
                    </p>
                    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[220px_1fr]">
                        <dt class="text-[#615d59]">Masa pendaftaran</dt>
                        <dd class="font-medium text-black dark:text-foreground">
                            {{ rentang(props.periode.tanggal_buka, props.periode.tanggal_tutup) }}
                        </dd>
                        <template v-if="props.periode.biaya_pendaftaran">
                            <dt class="text-[#615d59]">Biaya pendaftaran</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ rupiah(props.periode.biaya_pendaftaran) }}</dd>
                        </template>
                        <template v-if="props.periode.tanggal_pembayaran_mulai">
                            <dt class="text-[#615d59]">Masa pembayaran</dt>
                            <dd class="font-medium text-black dark:text-foreground">
                                {{ rentang(props.periode.tanggal_pembayaran_mulai, props.periode.tanggal_pembayaran_selesai) }}
                            </dd>
                        </template>
                        <template v-if="props.periode.tanggal_usm_mulai">
                            <dt class="text-[#615d59]">Ujian saringan masuk (USM)</dt>
                            <dd class="font-medium text-black dark:text-foreground">
                                {{ rentang(props.periode.tanggal_usm_mulai, props.periode.tanggal_usm_selesai) }}
                            </dd>
                        </template>
                        <template v-if="props.periode.tanggal_her">
                            <dt class="text-[#615d59]">Her-registrasi</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ tanggal(props.periode.tanggal_her) }}</dd>
                        </template>
                    </dl>
                    <p v-if="props.periode.penuh" class="alert-gagal mt-4">Kuota pendaftaran periode ini sudah penuh.</p>
                    <Button v-else as-child class="mt-5">
                        <Link :href="route('pmb.daftar')"><UserPlus /> Daftar Sekarang</Link>
                    </Button>
                </div>
                <p v-else class="alert-info mt-6">Saat ini belum ada periode pendaftaran yang dibuka.</p>
            </section>

            <section v-for="item in props.bagian" :key="item.judul" class="kartu p-6 sm:px-10">
                <h2 class="judul-bagian">{{ item.judul }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-black dark:text-foreground">{{ item.isi }}</p>
            </section>
        </div>
    </PmbLayout>
</template>
