<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatJamDari } from '@/lib/presensi';
import { Maximize, Minimize } from 'lucide-vue-next';
import QRCode from 'qrcode';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

type Kode = { terbuka: boolean; pin?: string; url?: string; sisa_detik?: number; sampai?: string; hadir?: number; total?: number };

const props = defineProps<{
    /** Endpoint JSON kode yang sedang berlaku (route presensi.pertemuan.kode). */
    urlKode: string;
    judul: string;
}>();
const emit = defineEmits<{ hadirBerubah: []; tertutup: [] }>();

const data = ref<Kode | null>(null);
const sisa = ref(0);
const gagal = ref(false);
const kanvas = ref<HTMLCanvasElement | null>(null);
const wadah = ref<HTMLElement | null>(null);
const layarPenuh = ref(false);
let detak: number | undefined;
let sejakAmbil = 0;

const ambil = async () => {
    sejakAmbil = 0;
    try {
        const respon = await fetch(props.urlKode, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!respon.ok) throw new Error(String(respon.status));
        const baru: Kode = await respon.json();
        gagal.value = false;

        if (!baru.terbuka) {
            data.value = baru;
            emit('tertutup');
            return;
        }
        if (data.value?.hadir !== undefined && data.value.hadir !== baru.hadir) emit('hadirBerubah');

        data.value = baru;
        sisa.value = baru.sisa_detik ?? 30;
    } catch {
        // Jaringan terputus sebentar: coba lagi di detik berikutnya, kode lama tetap tampil.
        gagal.value = true;
    }
};

watch(
    () => data.value?.url,
    async (url) => {
        await nextTick();
        if (url && kanvas.value) await QRCode.toCanvas(kanvas.value, url, { width: layarPenuh.value ? 520 : 280, margin: 1 });
    },
);

const ubahLayarPenuh = async () => {
    if (document.fullscreenElement) await document.exitFullscreen();
    else await wadah.value?.requestFullscreen();
};
const saatLayarPenuh = async () => {
    layarPenuh.value = document.fullscreenElement === wadah.value;
    await nextTick();
    if (data.value?.url && kanvas.value) await QRCode.toCanvas(kanvas.value, data.value.url, { width: layarPenuh.value ? 520 : 280, margin: 1 });
};

// Tab yang tidak sedang dilihat tidak meminta kode ke server; begitu dibuka lagi, kode terbaru langsung diambil.
const saatVisibilitasBerubah = () => {
    if (document.visibilityState === 'visible') ambil();
};

onMounted(() => {
    ambil();
    document.addEventListener('fullscreenchange', saatLayarPenuh);
    document.addEventListener('visibilitychange', saatVisibilitasBerubah);
    // Kode berganti saat hitung mundur habis; jumlah hadir diperbarui tiap 5 detik.
    detak = window.setInterval(() => {
        sisa.value = Math.max(sisa.value - 1, 0);
        sejakAmbil++;
        if (document.visibilityState === 'hidden') return;
        if (sisa.value === 0 || sejakAmbil >= 5 || gagal.value) ambil();
    }, 1000);
});
onUnmounted(() => {
    window.clearInterval(detak);
    document.removeEventListener('fullscreenchange', saatLayarPenuh);
    document.removeEventListener('visibilitychange', saatVisibilitasBerubah);
});
</script>

<template>
    <div
        ref="wadah"
        class="flex flex-col items-center gap-3 rounded-xl border border-[#e6e6e6] bg-white p-5"
        :class="layarPenuh ? 'justify-center gap-6 p-10' : ''"
    >
        <p v-if="layarPenuh" class="text-center text-2xl font-bold text-black">{{ props.judul }}</p>
        <template v-if="data?.terbuka">
            <canvas ref="kanvas" class="rounded-lg" aria-label="QR presensi" />
            <div class="text-center">
                <p class="text-xs uppercase tracking-[0.08em] text-[#a39e98]">PIN</p>
                <p class="font-mono font-bold tracking-[0.3em] text-black" :class="layarPenuh ? 'text-7xl' : 'text-4xl'">{{ data.pin }}</p>
            </div>
            <div class="h-1.5 w-full max-w-[280px] overflow-hidden rounded-full bg-[#f0eeec]">
                <div class="h-full bg-[#0075de] transition-[width] duration-1000 ease-linear" :style="{ width: `${(sisa / 30) * 100}%` }" />
            </div>
            <p class="text-sm text-[#615d59]" :class="layarPenuh ? 'text-lg' : ''">
                Berganti dalam {{ sisa }} detik · dibuka sampai {{ formatJamDari(data.sampai) }} · hadir {{ data.hadir }}/{{ data.total }}
            </p>
            <p v-if="gagal" class="text-xs text-[#dd5b00]">Koneksi terputus, mencoba lagi…</p>
            <Button type="button" variant="outline" size="sm" @click="ubahLayarPenuh">
                <component :is="layarPenuh ? Minimize : Maximize" class="mr-1 size-4" /> {{ layarPenuh ? 'Keluar layar penuh' : 'Layar penuh' }}
            </Button>
        </template>
        <p v-else-if="data" class="text-sm text-[#615d59]">Presensi mandiri sudah ditutup.</p>
        <p v-else class="text-sm text-[#615d59]">Memuat kode…</p>
    </div>
</template>
