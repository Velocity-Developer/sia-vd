<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import RecaptchaWidget from '@/components/RecaptchaWidget.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, Lock, User, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    recaptchaSiteKey?: string | null;
}>();

const form = useForm({
    username: '',
    password: '',
    remember: false,
    'g-recaptcha-response': '',
});

const lihatSandi = ref(false);

const maintenance = computed(() => usePage<SharedData>().props.maintenance);
const labelMaintenance = computed(() => (maintenance.value?.untuk ?? []).map((jenis) => (jenis === 'dosen' ? 'dosen' : 'mahasiswa')).join(' dan '));
const captcha = ref<InstanceType<typeof RecaptchaWidget> | null>(null);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
            // Token captcha sekali pakai; sesudah gagal masuk pengguna harus mencentang ulang.
            captcha.value?.reset();
        },
    });
};
</script>

<template>
    <AuthBase
        title="Masuk ke Akun Anda"
        description="Masuk dengan NIM (mahasiswa), NIDN (dosen), atau username, beserta kata sandi yang diberikan kampus."
    >
        <Head title="Masuk" />

        <div v-if="maintenance?.aktif" class="alert-info mb-5" role="status">
            <p class="font-medium">Sistem sedang dalam pemeliharaan untuk {{ labelMaintenance }}.</p>
            <p class="mt-1 whitespace-pre-line">{{ maintenance.pesan }}</p>
            <p v-if="maintenance.perkiraan_selesai" class="mt-1">Perkiraan selesai: {{ maintenance.perkiraan_selesai }}</p>
        </div>

        <div v-if="status" class="alert-sukses mb-5" role="status">
            {{ status }}
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="username" class="label-isian">NIM / NIDN / Username</Label>
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
                        class="pl-10"
                    />
                </div>
                <InputError :message="form.errors.username" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password" class="label-isian">Kata Sandi</Label>
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
                        class="pl-10 pr-11"
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

            <Label for="remember" class="label-isian flex w-fit items-center gap-2.5 font-normal">
                <Checkbox id="remember" v-model="form.remember" tabindex="3" />
                <span>Ingat saya di perangkat ini</span>
            </Label>

            <div v-if="recaptchaSiteKey" class="grid gap-2">
                <RecaptchaWidget ref="captcha" v-model="form['g-recaptcha-response']" :site-key="recaptchaSiteKey" />
                <InputError :message="(form.errors as Record<string, string>).captcha" />
            </div>

            <Button type="submit" tabindex="4" :disabled="form.processing" class="w-full">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                {{ form.processing ? 'Memproses…' : 'Masuk' }}
            </Button>

            <Button as-child variant="outline" class="w-full">
                <Link :href="route('pmb.informasi')"><UserPlus /> Link Pendaftaran Mahasiswa Baru</Link>
            </Button>

            <p class="text-center text-sm text-[#615d59] dark:text-muted-foreground">
                {{
                    canResetPassword
                        ? 'Belum punya akun? Hubungi bagian akademik kampus Anda.'
                        : 'Lupa kata sandi atau belum punya akun? Hubungi admin.'
                }}
            </p>
        </form>
    </AuthBase>
</template>
