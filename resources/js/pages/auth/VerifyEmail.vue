<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout title="Verifikasi Email" description="Klik tautan yang baru kami kirim ke email Anda untuk memverifikasi alamat email.">
        <Head title="Verifikasi Email" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 rounded-lg border border-[#c9ecd2] bg-[#f2fbf4] px-3 py-2 text-center text-sm text-[#1aae39]"
        >
            Tautan verifikasi baru sudah dikirim ke email Anda.
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" class="h-10 w-full rounded-lg bg-[#0075de] text-sm font-medium text-white hover:bg-[#005bab]">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Kirim Ulang Email Verifikasi
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm"> Keluar </TextLink>
        </form>
    </AuthLayout>
</template>
