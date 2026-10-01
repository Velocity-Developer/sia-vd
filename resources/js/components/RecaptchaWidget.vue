<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Kotak centang Google reCAPTCHA v2. Token dikirim lewat v-model dan dikosongkan saat kedaluwarsa.
// Pasang `:key="siteKey"` di pemakai bila site key bisa berubah, karena widget tidak bisa ganti kunci.

interface Grecaptcha {
    render: (el: HTMLElement, opsi: Record<string, unknown>) => number;
    reset: (id?: number) => void;
}

declare global {
    interface Window {
        grecaptcha?: Grecaptcha;
        siaRecaptchaSiap?: () => void;
    }
}

const props = defineProps<{ siteKey: string }>();
const token = defineModel<string>({ default: '' });

const wadah = ref<HTMLElement | null>(null);
const gagalMuat = ref(false);
let idWidget: number | null = null;

let skrip: Promise<Grecaptcha> | null = null;

// Skrip Google dimuat sekali per halaman, walau widget dipasang berulang kali.
function muatSkrip(): Promise<Grecaptcha> {
    if (window.grecaptcha?.render) {
        return Promise.resolve(window.grecaptcha);
    }

    skrip ??= new Promise((resolve, reject) => {
        window.siaRecaptchaSiap = () => resolve(window.grecaptcha as Grecaptcha);
        const el = document.createElement('script');
        el.src = 'https://www.google.com/recaptcha/api.js?onload=siaRecaptchaSiap&render=explicit&hl=id';
        el.async = true;
        el.defer = true;
        el.onerror = () => {
            skrip = null;
            el.remove();
            reject(new Error('Skrip reCAPTCHA gagal dimuat.'));
        };
        document.head.appendChild(el);
    });

    return skrip;
}

onMounted(async () => {
    try {
        const grecaptcha = await muatSkrip();
        if (!wadah.value) return;
        idWidget = grecaptcha.render(wadah.value, {
            sitekey: props.siteKey,
            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
            callback: (nilai: string) => (token.value = nilai),
            'expired-callback': () => (token.value = ''),
            'error-callback': () => (token.value = ''),
        });
    } catch {
        gagalMuat.value = true;
    }
});

onBeforeUnmount(() => {
    token.value = '';
});

// Token reCAPTCHA hanya sekali pakai, jadi widget dikosongkan lagi sesudah formulir dikirim.
function reset() {
    token.value = '';
    if (idWidget !== null) window.grecaptcha?.reset(idWidget);
}

defineExpose({ reset });
</script>

<template>
    <div>
        <div ref="wadah" class="min-h-[78px]" />
        <p v-if="gagalMuat" class="text-sm text-red-600">Captcha gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.</p>
    </div>
</template>
