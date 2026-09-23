<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle } from 'lucide-vue-next';
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
</script>

<template>
    <AuthBase title="Masuk" description="Gunakan username dan kata sandi akun Anda.">
        <Head title="Masuk" />

        <div v-if="status" class="mb-4 rounded-lg border border-[#c9ecd2] bg-[#f2fbf4] px-3 py-2 text-sm text-[#1aae39]">
            {{ status }}
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="username" class="text-sm font-medium text-[#31302e]">Username</Label>
                <Input
                    id="username"
                    v-model="form.username"
                    type="text"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="username"
                    placeholder="NIM, NIDN, atau username"
                    class="h-10 rounded-lg border-[#d8d5d2] bg-white text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                />
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-1.5">
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
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="lihatSandi ? 'text' : 'password'"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        placeholder="Kata sandi"
                        class="h-10 rounded-lg border-[#d8d5d2] bg-white pr-10 text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
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

            <Label for="remember" class="flex w-fit items-center gap-2.5 text-sm text-[#31302e]">
                <Checkbox id="remember" v-model:checked="form.remember" tabindex="3" />
                <span>Ingat saya di perangkat ini</span>
            </Label>

            <Button
                type="submit"
                tabindex="4"
                :disabled="form.processing"
                class="mt-1 h-10 w-full rounded-lg bg-[#0075de] text-sm font-medium text-white hover:bg-[#005bab]"
            >
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Masuk
            </Button>

            <p v-if="!canResetPassword" class="text-center text-sm text-[#615d59]">Lupa kata sandi? Hubungi admin.</p>
        </form>
    </AuthBase>
</template>
