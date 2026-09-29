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
                    <Label html-for="password" class="label-isian">Kata Sandi</Label>
                    <Input id="password" type="password" v-model="form.password" required autocomplete="current-password" autofocus />

                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center">
                    <Button type="submit" class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Konfirmasi Kata Sandi
                    </Button>
                </div>
            </div>
        </form>
    </AuthLayout>
</template>
