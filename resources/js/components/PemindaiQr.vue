<script setup lang="ts">
import QrScanner from 'qr-scanner';
import { onMounted, onUnmounted, ref } from 'vue';

const emit = defineEmits<{ hasil: [teks: string] }>();

const video = ref<HTMLVideoElement | null>(null);
const galat = ref('');
let pemindai: QrScanner | null = null;

/** Hentikan kamera; dipanggil induk setelah hasil diproses atau modal ditutup. */
const berhenti = () => {
    pemindai?.stop();
    pemindai?.destroy();
    pemindai = null;
};
defineExpose({ berhenti });

onMounted(async () => {
    // Browser hanya memberi akses kamera di HTTPS (atau localhost).
    if (!window.isSecureContext) {
        galat.value = 'Kamera hanya bisa dipakai bila situs dibuka lewat HTTPS. Pindai QR dengan aplikasi kamera HP, atau ketik PIN.';
        return;
    }
    if (!video.value) return;

    pemindai = new QrScanner(video.value, (hasil) => emit('hasil', hasil.data), {
        preferredCamera: 'environment',
        highlightScanRegion: true,
        highlightCodeOutline: true,
        maxScansPerSecond: 5,
    });

    try {
        await pemindai.start();
    } catch (error) {
        const nama = error instanceof DOMException ? error.name : String(error);
        galat.value =
            nama === 'NotAllowedError'
                ? 'Izin kamera ditolak. Izinkan kamera untuk situs ini di pengaturan browser, lalu coba lagi.'
                : 'Kamera tidak ditemukan atau sedang dipakai aplikasi lain.';
        berhenti();
    }
});

onUnmounted(berhenti);
</script>

<template>
    <div class="overflow-hidden rounded-lg bg-black">
        <video v-show="!galat" ref="video" class="aspect-square w-full object-cover" muted playsinline />
        <p v-if="galat" class="bg-[#fff6e0] px-4 py-6 text-center text-sm text-[#8a5a00]">{{ galat }}</p>
    </div>
</template>
