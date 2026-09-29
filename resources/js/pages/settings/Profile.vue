<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    namaTerkunci?: boolean;
    status?: string;
    className?: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan Profil',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
// Dibaca ulang tiap respons agar status verifikasi ikut berubah sesudah email diganti.
const terverifikasi = computed(() => Boolean((page.props.auth.user as User).email_verified_at));
const galatKirim = computed(() => (page.props.flash as { error?: string | null } | undefined)?.error);

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pengaturan Profil" />

        <SettingsLayout>
            <form class="kartu p-6" @submit.prevent="submit">
                <HeadingSmall title="Informasi Profil" description="Perbarui nama dan alamat email Anda" />

                <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name" class="label-isian">Nama</Label>
                        <Input id="name" v-model="form.name" required autocomplete="name" placeholder="Nama lengkap" :disabled="props.namaTerkunci" />
                        <p v-if="props.namaTerkunci" class="teks-bantu">Nama mengikuti data akademik. Hubungi bagian akademik untuk mengubahnya.</p>
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="label-isian">Alamat Email</Label>
                        <Input id="email" v-model="form.email" type="email" required autocomplete="username" placeholder="Alamat email" />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div v-if="mustVerifyEmail && !terverifikasi" class="mt-4 grid gap-2">
                    <div class="alert-info">
                        Alamat email Anda belum terverifikasi. Menu lain terbuka setelah Anda mengeklik tautan di email.
                        <Link :href="route('verification.send')" method="post" as="button" class="font-medium underline underline-offset-2">
                            Kirim ulang email verifikasi.
                        </Link>
                    </div>

                    <div v-if="status === 'verification-link-sent'" class="alert-sukses">
                        Tautan verifikasi baru sudah dikirim ke alamat email Anda.
                    </div>
                    <div v-if="galatKirim" class="alert-gagal" role="alert">
                        {{ galatKirim }}
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-[#1aae39]">Tersimpan.</p>
                    </Transition>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
                </div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
