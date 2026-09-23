<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => form.post(route('password.email'));
</script>

<template>
    <AuthBase title="Lupa Kata Sandi" description="Masukkan email akun Anda. Tautan atur ulang kata sandi akan dikirim ke email tersebut.">
        <Head title="Lupa Kata Sandi" />

        <div v-if="status" class="mb-4 rounded-lg border border-[#c9ecd2] bg-[#f2fbf4] px-3 py-2 text-sm text-[#1aae39]">
            {{ status }}
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="email" class="text-sm font-medium text-[#31302e]">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="nama@example.com"
                    class="h-10 rounded-lg border-[#d8d5d2] bg-white text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                />
                <InputError :message="form.errors.email" />
            </div>

            <Button
                type="submit"
                :disabled="form.processing"
                class="h-10 w-full rounded-lg bg-[#0075de] text-sm font-medium text-white hover:bg-[#005bab]"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Kirim Tautan Atur Ulang
            </Button>

            <p class="text-center text-sm text-[#615d59]">
                Sudah ingat kata sandinya?
                <Link :href="route('login')" class="font-medium text-[#0075de] hover:underline">Kembali ke halaman masuk</Link>
            </p>
        </form>
    </AuthBase>
</template>
