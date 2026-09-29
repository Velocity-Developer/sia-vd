<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const lihatSandi = ref(false);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Kata Sandi Baru" description="Buat kata sandi baru untuk akun Anda.">
        <Head title="Atur Ulang Kata Sandi" />

        <!-- Tautan kedaluwarsa atau sudah dipakai dijawab sebagai galat email/token oleh Laravel;
             ditampilkan sebagai pesan utuh supaya pengguna tahu harus meminta tautan baru. -->
        <div v-if="form.errors.token || form.errors.email" class="alert-gagal mb-4" role="alert">
            {{ form.errors.token ?? form.errors.email }}
            <Link :href="route('password.request')" class="font-medium underline underline-offset-2">Minta tautan baru</Link>
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email" class="label-isian">Email</Label>
                <Input id="email" v-model="form.email" type="email" readonly autocomplete="email" class="bg-[#f6f5f4] dark:bg-muted" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="label-isian">Kata Sandi Baru</Label>
                <div class="relative">
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="lihatSandi ? 'text' : 'password'"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        class="pr-11"
                    />
                    <button
                        type="button"
                        tabindex="-1"
                        :aria-label="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        class="absolute right-1 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-[#a39e98] transition-colors hover:bg-[#f6f5f4] hover:text-[#615d59] dark:hover:bg-accent"
                        @click="lihatSandi = !lihatSandi"
                    >
                        <component :is="lihatSandi ? EyeOff : Eye" class="size-4" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" class="label-isian">Ulangi Kata Sandi Baru</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="lihatSandi ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Simpan Kata Sandi
            </Button>
        </form>
    </AuthBase>
</template>
