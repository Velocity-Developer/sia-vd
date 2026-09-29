<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const page = usePage<{ flash?: { error?: string | null } }>();
const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout title="Verifikasi Email" description="Klik tautan yang baru kami kirim ke email Anda untuk memverifikasi alamat email.">
        <Head title="Verifikasi Email" />

        <div v-if="status === 'verification-link-sent'" class="alert-sukses mb-4 text-center" role="status">
            Tautan verifikasi baru sudah dikirim ke email Anda.
        </div>

        <div v-if="page.props.flash?.error" class="alert-gagal mb-4 text-center" role="alert">
            {{ page.props.flash.error }}
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button type="submit" :disabled="form.processing" class="w-full">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Kirim Ulang Email Verifikasi
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm"> Keluar </TextLink>
        </form>
    </AuthLayout>
</template>
