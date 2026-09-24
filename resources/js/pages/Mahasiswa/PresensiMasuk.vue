<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { JENIS_PERTEMUAN, formatJamDari, formatTanggal, infoStatusPresensi, jam, type JenisPertemuan } from '@/lib/presensi';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    pertemuan: {
        id: number;
        pertemuan_ke: number;
        tanggal: string;
        jam_mulai: string;
        jam_akhir: string;
        jenis: JenisPertemuan;
        kelas_kuliah?: { kode_kelas: string; mata_kuliah?: { nama_matkul: string } | null } | null;
        ruang?: { kode_ruang: string } | null;
    } | null;
    kode: string;
    terdaftar: boolean;
    terbuka: boolean;
    presensi: { status: string; waktu_presensi: string | null } | null;
}>();

const sudahHadir = computed(() => props.presensi?.status === 'hadir' || props.presensi?.status === 'terlambat');
const form = useForm({ pertemuan_id: props.pertemuan?.id ?? 0, kode: props.kode });
const konfirmasi = () => form.post(route('mahasiswa.presensi.check-in'));
</script>

<template>
    <Head title="Konfirmasi Presensi" />
    <AppLayout :breadcrumbs="[{ title: 'Presensi', href: route('mahasiswa.presensi') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[520px] flex-col gap-4 px-4 py-8">
                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 text-center shadow-sm">
                    <template v-if="props.pertemuan">
                        <p class="text-xs uppercase tracking-[0.08em] text-[#a39e98]">Presensi</p>
                        <h1 class="mt-1 text-xl font-bold text-black">{{ props.pertemuan.kelas_kuliah?.mata_kuliah?.nama_matkul }}</h1>
                        <p class="mt-1 text-sm text-[#615d59]">
                            {{ props.pertemuan.kelas_kuliah?.kode_kelas }} · Pertemuan {{ props.pertemuan.pertemuan_ke }}
                            <template v-if="props.pertemuan.jenis !== 'kuliah'"> ({{ JENIS_PERTEMUAN[props.pertemuan.jenis] }})</template>
                        </p>
                        <p class="text-sm text-[#615d59]">
                            {{ formatTanggal(props.pertemuan.tanggal) }} · {{ jam(props.pertemuan.jam_mulai) }}–{{ jam(props.pertemuan.jam_akhir) }}
                            <template v-if="props.pertemuan.ruang"> · {{ props.pertemuan.ruang.kode_ruang }}</template>
                        </p>

                        <p v-if="sudahHadir" class="mt-6 flex items-center justify-center gap-1.5 font-medium text-[#1a7f37]">
                            <CircleCheck class="size-5" /> Anda sudah tercatat
                            {{ infoStatusPresensi(props.presensi?.status ?? '')?.label.toLowerCase() }} pukul
                            {{ formatJamDari(props.presensi?.waktu_presensi) }}.
                        </p>
                        <template v-else-if="props.terbuka">
                            <Button
                                class="mt-6 h-12 w-full rounded-full bg-[#0075de] text-base text-white hover:bg-[#005bab]"
                                :disabled="form.processing"
                                @click="konfirmasi"
                            >
                                Konfirmasi hadir
                            </Button>
                            <InputError class="mt-2" :message="form.errors.kode" />
                            <p class="mt-3 text-xs text-[#a39e98]">Kode QR berlaku sebentar. Bila kedaluwarsa, pindai ulang QR di layar kelas.</p>
                        </template>
                        <p v-else class="mt-6 text-sm text-[#dd5b00]">Presensi mandiri pertemuan ini belum dibuka atau sudah ditutup dosen.</p>
                    </template>
                    <p v-else class="text-sm text-[#dd5b00]">Anda tidak terdaftar di kelas pertemuan ini.</p>

                    <Link :href="route('mahasiswa.presensi')" class="mt-6 inline-block text-sm font-medium text-[#0075de] hover:underline">
                        Ke halaman Presensi
                    </Link>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
