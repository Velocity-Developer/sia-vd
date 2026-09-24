<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, Lock, User } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const lihatSandi = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const isian =
    'h-11 rounded-xl border-[#d8d5d2] bg-white pl-10 text-sm text-[#31302e] shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-4 focus-visible:ring-[#0075de]/10';
</script>

<template>
    <AuthBase title="Masuk ke Akun Anda" description="Gunakan username dan kata sandi yang diberikan kampus.">
        <Head title="Masuk" />

        <div v-if="status" class="mb-5 rounded-xl border border-[#c9ecd2] bg-[#f2fbf4] px-4 py-3 text-sm text-[#1aae39]" role="status">
            {{ status }}
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="username" class="text-sm font-medium text-[#31302e]">Username</Label>
                <div class="relative">
                    <User class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                    <Input
                        id="username"
                        v-model="form.username"
                        type="text"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="username"
                        placeholder="NIM, NIDN, atau username"
                        :class="isian"
                    />
                </div>
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password" class="text-sm font-medium text-[#31302e]">Kata Sandi</Label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        :tabindex="5"
                        class="text-sm font-medium text-[#0075de] hover:underline"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>
                <div class="relative">
                    <Lock class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="lihatSandi ? 'text' : 'password'"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        :class="[isian, 'pr-11']"
                    />
                    <button
                        type="button"
                        tabindex="-1"
                        :aria-label="lihatSandi ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        class="absolute right-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-[#a39e98] transition-colors hover:bg-[#f6f5f4] hover:text-[#615d59]"
                        @click="lihatSandi = !lihatSandi"
                    >
                        <component :is="lihatSandi ? EyeOff : Eye" class="size-4" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <Label for="remember" class="flex w-fit items-center gap-2.5 text-sm text-[#31302e]">
                <Checkbox id="remember" v-model="form.remember" tabindex="3" />
                <span>Ingat saya di perangkat ini</span>
            </Label>

            <Button
                type="submit"
                tabindex="4"
                :disabled="form.processing"
                class="h-11 w-full rounded-xl bg-[#0075de] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#005bab]"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </Button>

            <p class="text-center text-sm text-[#615d59]">
                {{
                    canResetPassword
                        ? 'Belum punya akun? Hubungi bagian akademik kampus Anda.'
                        : 'Lupa kata sandi atau belum punya akun? Hubungi admin.'
                }}
            </p>
        </form>
    </AuthBase>
</template>
