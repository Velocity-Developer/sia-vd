<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LoaderCircle, Mail, MailCheck } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

// Pesan sukses datang dari sesi, jadi butuh penanda sendiri untuk menampilkan formulirnya lagi.
const kirimUlang = ref(false);

const kirimKeEmailLain = () => {
    form.email = '';
    form.clearErrors();
    kirimUlang.value = true;
};

const submit = () => {
    kirimUlang.value = false;
    form.post(route('password.email'));
};

const isian =
    'h-11 rounded-xl border-[#d8d5d2] bg-white pl-10 text-sm text-[#31302e] shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-4 focus-visible:ring-[#0075de]/10';
</script>

<template>
    <AuthBase title="Lupa Kata Sandi" description="Masukkan email akun Anda. Kami kirimkan tautan untuk membuat kata sandi baru.">
        <Head title="Lupa Kata Sandi" />

        <!-- Sesudah tautan dikirim, isian disembunyikan agar tidak ada yang menekan kirim berulang kali. -->
        <div v-if="status && !kirimUlang" class="space-y-5">
            <div class="flex gap-3 rounded-xl border border-[#c9ecd2] bg-[#f2fbf4] px-4 py-3.5" role="status">
                <MailCheck class="mt-0.5 size-5 shrink-0 text-[#1aae39]" />
                <div class="space-y-1">
                    <p class="text-sm font-medium text-[#1aae39]">{{ status }}</p>
                    <p class="text-sm leading-5 text-[#615d59]">
                        Tautan berlaku 60 menit. Bila belum masuk, periksa folder spam atau hubungi bagian akademik.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('login')">
                    <Button class="h-11 rounded-xl bg-[#0075de] px-5 text-sm font-semibold text-white hover:bg-[#005bab]">
                        Kembali ke Halaman Masuk
                    </Button>
                </Link>
                <button type="button" class="text-sm font-medium text-[#615d59] hover:underline" @click="kirimKeEmailLain">
                    Kirim ke email lain
                </button>
            </div>
        </div>

        <form v-else class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email" class="text-sm font-medium text-[#31302e]">Email</Label>
                <div class="relative">
                    <Mail class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="nama@example.com"
                        :class="isian"
                    />
                </div>
                <p class="text-xs text-[#615d59]">Gunakan email yang terdaftar di sistem akademik, bukan email pribadi lain.</p>
                <InputError :message="form.errors.email" />
            </div>

            <Button
                type="submit"
                :disabled="form.processing"
                class="h-11 w-full rounded-xl bg-[#0075de] text-sm font-semibold text-white shadow-sm hover:bg-[#005bab]"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Mengirim…' : 'Kirim Tautan Atur Ulang' }}
            </Button>

            <Link :href="route('login')" class="inline-flex items-center justify-center gap-1.5 text-sm font-medium text-[#615d59] hover:text-black">
                <ArrowLeft class="size-4" />
                Kembali ke halaman masuk
            </Link>
        </form>
    </AuthBase>
</template>
