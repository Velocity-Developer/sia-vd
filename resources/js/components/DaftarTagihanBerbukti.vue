<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { formatTanggal } from '@/lib/presensi';
import { rupiah, STATUS_TAGIHAN_REMIDI, type Rincian, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

export type TagihanBerbukti = {
    id: number;
    judul: string | null;
    kelas: string | null;
    tahun_akademik: string;
    rincian: Rincian[];
    total: number;
    status: StatusTagihanRemidi;
    batas_bayar: string | null;
    boleh_unggah: boolean;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
};

/**
 * Daftar tagihan yang dibayar lewat unggah bukti (remidi, susulan) di halaman Biaya Kuliah mahasiswa.
 */
const props = defineProps<{
    judul: string;
    keterangan: string;
    tagihan: TagihanBerbukti[];
    ruteUnggah: string;
    ruteBukti: string;
    pesanGugur: string;
    pesanDibatalkan?: string;
}>();

const formBukti = useForm<{ bukti: File | null }>({ bukti: null });
const unggahUntuk = ref<number | null>(null);
// Input berkas bawaan tidak ikut kosong saat form direset, jadi dirender ulang lewat key.
const kunciInput = ref(0);
const pilihBukti = (tagihan: TagihanBerbukti, event: Event) => {
    formBukti.clearErrors();
    formBukti.bukti = (event.target as HTMLInputElement).files?.[0] ?? null;
    unggahUntuk.value = tagihan.id;
};
const kirimBukti = (tagihan: TagihanBerbukti) =>
    formBukti.post(route(props.ruteUnggah, tagihan.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            formBukti.reset();
            unggahUntuk.value = null;
            kunciInput.value++;
        },
    });
</script>

<template>
    <div v-if="props.tagihan.length" class="rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
        <div class="border-b border-[#e6e6e6] px-5 py-4">
            <h2 class="text-lg font-semibold text-black">{{ props.judul }}</h2>
            <p class="text-sm text-[#615d59]">{{ props.keterangan }}</p>
        </div>
        <div class="divide-y divide-[#e6e6e6]">
            <div v-for="tagihan in props.tagihan" :key="tagihan.id" class="flex flex-col gap-3 px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-black">{{ tagihan.judul }}</p>
                        <p class="text-xs text-[#a39e98]">Kelas {{ tagihan.kelas }} · {{ tagihan.tahun_akademik }}</p>
                        <p v-for="r in tagihan.rincian" :key="r.nama" class="text-xs text-[#615d59]">
                            {{ r.nama }}<template v-if="r.jumlah > 1"> · {{ r.jumlah }} SKS × {{ rupiah(r.nominal_satuan) }}</template>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-black">{{ rupiah(tagihan.total) }}</p>
                        <span
                            class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="STATUS_TAGIHAN_REMIDI[tagihan.status].kelas"
                            >{{ STATUS_TAGIHAN_REMIDI[tagihan.status].label }}</span
                        >
                        <p v-if="tagihan.batas_bayar && !['lunas', 'dibatalkan'].includes(tagihan.status)" class="mt-1 text-xs text-[#a39e98]">
                            Batas bayar {{ formatTanggal(tagihan.batas_bayar) }}
                        </p>
                    </div>
                </div>
                <p v-if="tagihan.status === 'ditolak' || (tagihan.status === 'gugur' && tagihan.alasan_tolak)" class="text-sm text-[#b42318]">
                    Bukti ditolak: {{ tagihan.alasan_tolak }}
                </p>
                <p v-if="tagihan.status === 'gugur'" class="text-sm text-[#615d59]">{{ props.pesanGugur }}</p>
                <p v-if="tagihan.status === 'dibatalkan' && props.pesanDibatalkan" class="text-sm text-[#615d59]">{{ props.pesanDibatalkan }}</p>
                <p v-if="tagihan.ada_bukti" class="text-sm text-[#615d59]">
                    Bukti diunggah {{ formatTanggal(tagihan.bukti_diunggah_at) }}.
                    <a :href="route(props.ruteBukti, tagihan.id)" target="_blank" rel="noopener" class="font-medium text-[#0075de] hover:underline"
                        >Lihat bukti</a
                    >
                </p>
                <div v-if="tagihan.boleh_unggah" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        :id="`bukti-${props.ruteUnggah}-${tagihan.id}`"
                        :key="`bukti-${tagihan.id}-${kunciInput}`"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="text-sm file:mr-3 file:rounded-full file:border file:border-[#dddddd] file:bg-white file:px-3 file:py-1.5 file:text-sm"
                        @change="pilihBukti(tagihan, $event)"
                    />
                    <Button
                        size="sm"
                        class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                        :disabled="unggahUntuk !== tagihan.id || !formBukti.bukti || formBukti.processing"
                        @click="kirimBukti(tagihan)"
                        >{{ tagihan.ada_bukti ? 'Ganti Bukti' : 'Kirim Bukti' }}</Button
                    >
                </div>
                <InputError v-if="unggahUntuk === tagihan.id" :message="formBukti.errors.bukti" />
            </div>
        </div>
    </div>
</template>
