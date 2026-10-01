<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import RecaptchaWidget from '@/components/RecaptchaWidget.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Pengaturan {
    aktif: boolean;
    site_key: string | null;
    secret_key_tersimpan: boolean;
}

const props = defineProps<{ pengaturan: Pengaturan }>();

const form = useForm({
    aktif: props.pengaturan.aktif,
    site_key: props.pengaturan.site_key ?? '',
    secret_key: '',
    token_uji: '',
});

const widget = ref<InstanceType<typeof RecaptchaWidget> | null>(null);

// Site key reCAPTCHA v2 berupa 40 karakter; widget uji baru dipasang bila isiannya masuk akal.
const siteKeyUji = computed(() => form.site_key.trim());
const tampilkanUji = computed(() => form.aktif && siteKeyUji.value.length >= 30);

const simpan = () =>
    form
        .transform((data) => ({ ...data, aktif: data.aktif ? 1 : 0 }))
        .put(route('pengaturan-recaptcha.update'), {
            preserveScroll: true,
            onSuccess: () => form.reset('secret_key'),
            onFinish: () => widget.value?.reset(),
        });
</script>

<template>
    <PengaturanSistemLayout>
        <Head title="Pengaturan reCAPTCHA" />

        <form class="kartu grid gap-6 p-6" @submit.prevent="simpan">
            <HeadingSmall
                title="Google reCAPTCHA v2"
                description='Kotak centang "Saya bukan robot" di halaman masuk untuk menahan percobaan masuk otomatis'
            />

            <Label for="aktif" class="label-isian flex w-fit items-center gap-2.5 font-normal">
                <Checkbox id="aktif" v-model="form.aktif" />
                <span>Tampilkan captcha di halaman masuk</span>
            </Label>
            <InputError :message="form.errors.aktif" />

            <div class="grid content-start gap-4 sm:grid-cols-2">
                <div class="grid content-start gap-2">
                    <Label class="label-isian" for="site_key">Site Key</Label>
                    <Input id="site_key" v-model="form.site_key" autocomplete="off" spellcheck="false" placeholder="6Lc…" />
                    <InputError :message="form.errors.site_key" />
                </div>
                <div class="grid content-start gap-2">
                    <Label class="label-isian" for="secret_key">Secret Key</Label>
                    <Input
                        id="secret_key"
                        v-model="form.secret_key"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="props.pengaturan.secret_key_tersimpan ? 'Tersimpan — isi hanya bila ingin mengganti' : 'Secret key'"
                    />
                    <p class="teks-bantu">Disimpan terenkripsi dan tidak pernah ditampilkan kembali.</p>
                    <InputError :message="form.errors.secret_key" />
                </div>
            </div>

            <p class="teks-bantu">
                Buat kunci di
                <a
                    href="https://www.google.com/recaptcha/admin/create"
                    target="_blank"
                    rel="noopener"
                    class="font-medium text-[#0075de] hover:underline"
                    >konsol Google reCAPTCHA</a
                >
                dengan jenis <strong>reCAPTCHA v2 → Kotak centang "Saya bukan robot"</strong>, lalu daftarkan domain situs ini.
            </p>

            <div v-if="form.aktif" class="grid gap-2 rounded-lg border border-[#e6e6e6] p-4 dark:border-border">
                <Label class="label-isian">Captcha Uji</Label>
                <p class="teks-bantu">
                    Wajib dicentang saat menyalakan captcha atau mengganti kunci. Kunci yang salah akan membuat semua pengguna tidak bisa masuk, jadi
                    sistem memeriksanya ke Google lebih dulu.
                </p>
                <RecaptchaWidget v-if="tampilkanUji" :key="siteKeyUji" ref="widget" v-model="form.token_uji" :site-key="siteKeyUji" />
                <p v-else class="teks-bantu">Isi site key terlebih dahulu.</p>
                <InputError :message="form.errors.token_uji" />
            </div>

            <div class="flex justify-end gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Simpan Pengaturan
                </Button>
            </div>
        </form>
    </PengaturanSistemLayout>
</template>
