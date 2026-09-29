<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';

interface Props {
    className?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kata Sandi',
        href: '/settings/password',
    },
];

const passwordInput = ref<HTMLInputElement>();
const currentPasswordInput = ref<HTMLInputElement>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Kata Sandi" />

        <SettingsLayout>
            <form class="kartu p-6" @submit.prevent="updatePassword">
                <HeadingSmall title="Ubah Kata Sandi" description="Gunakan kata sandi yang panjang dan acak agar akun tetap aman." />

                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2 sm:max-w-[calc(50%-0.5rem)]">
                        <Label for="current_password" class="label-isian">Kata Sandi Saat Ini</Label>
                        <Input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Kata sandi saat ini"
                        />
                        <InputError :message="form.errors.current_password" />
                    </div>

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="password" class="label-isian">Kata Sandi Baru</Label>
                            <Input
                                id="password"
                                ref="passwordInput"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Kata sandi baru"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation" class="label-isian">Konfirmasi Kata Sandi</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Ulangi kata sandi baru"
                            />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-[#1aae39]">Tersimpan</p>
                    </Transition>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
