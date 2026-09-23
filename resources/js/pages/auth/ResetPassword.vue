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

const gayaIsian =
    'h-10 rounded-lg border-[#d8d5d2] bg-white text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15';
</script>

<template>
    <AuthBase title="Kata Sandi Baru" description="Buat kata sandi baru untuk akun Anda.">
        <Head title="Atur Ulang Kata Sandi" />

        <!-- Tautan kedaluwarsa atau sudah dipakai dijawab sebagai galat email/token oleh Laravel;
             ditampilkan sebagai pesan utuh supaya pengguna tahu harus meminta tautan baru. -->
        <div
            v-if="form.errors.token || form.errors.email"
            class="mb-4 rounded-lg border border-[#f6d7c4] bg-[#fdf6f1] px-3 py-2 text-sm text-[#dd5b00]"
        >
            {{ form.errors.token ?? form.errors.email }}
            <Link :href="route('password.request')" class="font-medium text-[#0075de] hover:underline">Minta tautan baru</Link>
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="email" class="text-sm font-medium text-[#31302e]">Email</Label>
                <Input id="email" v-model="form.email" type="email" readonly autocomplete="email" :class="[gayaIsian, 'bg-[#f6f5f4]']" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password" class="text-sm font-medium text-[#31302e]">Kata Sandi Baru</Label>
                <div class="relative">
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="lihatSandi ? 'text' : 'password'"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        :class="[gayaIsian, 'pr-10']"
                    />
                    <button
                        type="button"
                        tabindex="-1"
                        :aria-label="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        class="absolute right-2 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-[#a39e98] transition-colors hover:bg-[#f6f5f4] hover:text-[#615d59]"
                        @click="lihatSandi = !lihatSandi"
                    >
                        <component :is="lihatSandi ? EyeOff : Eye" class="size-4" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password_confirmation" class="text-sm font-medium text-[#31302e]">Ulangi Kata Sandi Baru</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="lihatSandi ? 'text' : 'password'"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi"
                    :class="gayaIsian"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                :disabled="form.processing"
                class="mt-1 h-10 w-full rounded-lg bg-[#0075de] text-sm font-medium text-white hover:bg-[#005bab]"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Simpan Kata Sandi
            </Button>
        </form>
    </AuthBase>
</template>
