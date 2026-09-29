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
</script>

<template>
    <AuthBase title="Lupa Kata Sandi" description="Masukkan email akun Anda. Kami kirimkan tautan untuk membuat kata sandi baru.">
        <Head title="Lupa Kata Sandi" />

        <!-- Sesudah tautan dikirim, isian disembunyikan agar tidak ada yang menekan kirim berulang kali. -->
        <div v-if="status && !kirimUlang" class="space-y-5">
            <div class="alert-sukses flex gap-3" role="status">
                <MailCheck class="mt-0.5 size-5 shrink-0" />
                <div class="space-y-1">
                    <p class="font-medium">{{ status }}</p>
                    <p class="text-[#615d59] dark:text-muted-foreground">
                        Tautan berlaku 60 menit. Bila belum masuk, periksa folder spam atau hubungi bagian akademik.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button as-child>
                    <Link :href="route('login')">Kembali ke Halaman Masuk</Link>
                </Button>
                <Button type="button" variant="ghost" @click="kirimKeEmailLain">Kirim ke email lain</Button>
            </div>
        </div>

        <form v-else class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email" class="label-isian">Email</Label>
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
                        class="pl-10"
                    />
                </div>
                <p class="teks-bantu">Gunakan email yang terdaftar di sistem akademik, bukan email pribadi lain.</p>
                <InputError :message="form.errors.email" />
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                {{ form.processing ? 'Mengirim…' : 'Kirim Tautan Atur Ulang' }}
            </Button>

            <Link
                :href="route('login')"
                class="inline-flex items-center justify-center gap-1.5 text-sm font-medium text-[#615d59] hover:text-black dark:text-muted-foreground dark:hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Kembali ke halaman masuk
            </Link>
        </form>
    </AuthBase>
</template>
