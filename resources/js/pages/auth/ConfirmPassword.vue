<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <AuthLayout title="Konfirmasi Kata Sandi" description="Bagian ini dilindungi. Masukkan kata sandi Anda untuk melanjutkan.">
        <Head title="Konfirmasi Kata Sandi" />

        <form @submit.prevent="submit">
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label html-for="password" class="text-sm font-medium text-[#31302e]">Kata Sandi</Label>
                    <Input
                        id="password"
                        type="password"
                        class="h-10 rounded-lg border-[#d8d5d2] bg-white text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center">
                    <Button class="h-10 w-full rounded-lg bg-[#0075de] text-sm font-medium text-white hover:bg-[#005bab]" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        Konfirmasi Kata Sandi
                    </Button>
                </div>
            </div>
        </form>
    </AuthLayout>
</template>
