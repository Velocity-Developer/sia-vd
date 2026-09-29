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
        <div class="halaman">
            <!-- Halaman fokus hasil pindai QR: lebar sempit di tengah -->
            <div class="mx-auto flex w-full max-w-[520px] flex-col gap-6 px-4 py-6">
                <section class="kartu p-6 text-center">
                    <template v-if="props.pertemuan">
                        <p class="teks-bantu uppercase tracking-[0.08em]">Presensi</p>
                        <h1 class="judul-halaman mt-1">{{ props.pertemuan.kelas_kuliah?.mata_kuliah?.nama_matkul }}</h1>
                        <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                            {{ props.pertemuan.kelas_kuliah?.kode_kelas }} · Pertemuan {{ props.pertemuan.pertemuan_ke }}
                            <template v-if="props.pertemuan.jenis !== 'kuliah'"> ({{ JENIS_PERTEMUAN[props.pertemuan.jenis] }})</template>
                        </p>
                        <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                            {{ formatTanggal(props.pertemuan.tanggal) }} · {{ jam(props.pertemuan.jam_mulai) }}–{{ jam(props.pertemuan.jam_akhir) }}
                            <template v-if="props.pertemuan.ruang"> · {{ props.pertemuan.ruang.kode_ruang }}</template>
                        </p>

                        <div v-if="sudahHadir" class="alert-sukses mt-6 flex items-center justify-center gap-1.5 font-medium">
                            <CircleCheck class="size-5 shrink-0" />
                            <span
                                >Anda sudah tercatat {{ infoStatusPresensi(props.presensi?.status ?? '')?.label.toLowerCase() }} pukul
                                {{ formatJamDari(props.presensi?.waktu_presensi) }}.</span
                            >
                        </div>
                        <template v-else-if="props.terbuka">
                            <Button class="mt-6 w-full" :disabled="form.processing" @click="konfirmasi">Konfirmasi hadir</Button>
                            <InputError class="mt-2" :message="form.errors.kode" />
                            <p class="teks-bantu mt-3">Kode QR berlaku sebentar. Bila kedaluwarsa, pindai ulang QR di layar kelas.</p>
                        </template>
                        <div v-else class="alert-gagal mt-6">Presensi mandiri pertemuan ini belum dibuka atau sudah ditutup dosen.</div>
                    </template>
                    <div v-else class="alert-gagal">Anda tidak terdaftar di kelas pertemuan ini.</div>

                    <Button as-child variant="outline" class="mt-6">
                        <Link :href="route('mahasiswa.presensi')">Ke halaman Presensi</Link>
                    </Button>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
