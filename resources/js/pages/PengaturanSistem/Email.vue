<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import TemplateEmailEditor, { type Template } from '@/components/TemplateEmailEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Pengaturan {
    mailer: string;
    host: string | null;
    port: number | null;
    encryption: string;
    username: string | null;
    from_address: string | null;
    from_name: string | null;
    password_tersimpan: boolean;
}

const props = defineProps<{ pengaturan: Pengaturan; template: Template[] }>();

const form = useForm({
    mailer: props.pengaturan.mailer ?? 'log',
    host: props.pengaturan.host ?? '',
    port: props.pengaturan.port ? String(props.pengaturan.port) : '587',
    encryption: props.pengaturan.encryption ?? 'tls',
    username: props.pengaturan.username ?? '',
    password: '',
    from_address: props.pengaturan.from_address ?? '',
    from_name: props.pengaturan.from_name ?? '',
});

const formUji = useForm({ email_tujuan: '' });

const pakaiSmtp = computed(() => form.mailer === 'smtp');

const simpan = () => form.put(route('pengaturan-email.update'), { preserveScroll: true, onSuccess: () => form.reset('password') });
const kirimUji = () => formUji.post(route('pengaturan-email.uji'), { preserveScroll: true });
</script>

<template>
    <PengaturanSistemLayout>
        <Head title="Pengaturan Email" />

        <form class="kartu grid gap-6 p-6" @submit.prevent="simpan">
            <HeadingSmall title="Pengaturan Email" description="Menentukan cara sistem mengirim surel, termasuk tautan atur ulang kata sandi" />

            <div class="grid gap-2">
                <Label class="label-isian" for="mailer">Metode Pengiriman</Label>
                <SelectFilter v-model="form.mailer" label="Metode pengiriman" penuh>
                    <option value="smtp">SMTP — surel benar-benar dikirim</option>
                    <option value="log">Log — surel hanya dicatat di storage/logs/mail.log</option>
                </SelectFilter>
                <p class="teks-bantu">Pilih Log saat pengujian agar tidak ada surel yang benar-benar terkirim.</p>
                <InputError :message="form.errors.mailer" />
            </div>

            <template v-if="pakaiSmtp">
                <div class="grid content-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label class="label-isian" for="host">Host SMTP</Label>
                        <Input id="host" v-model="form.host" placeholder="smtp.gmail.com" />
                        <InputError :message="form.errors.host" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="label-isian" for="port">Port</Label>
                        <Input id="port" v-model="form.port" type="number" min="1" max="65535" placeholder="587" />
                        <InputError :message="form.errors.port" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="label-isian" for="encryption">Enkripsi</Label>
                        <SelectFilter v-model="form.encryption" label="Enkripsi" penuh>
                            <option value="tls">TLS (umumnya port 587)</option>
                            <option value="ssl">SSL (umumnya port 465)</option>
                            <option value="none">Tanpa enkripsi</option>
                        </SelectFilter>
                        <InputError :message="form.errors.encryption" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="label-isian" for="username">Username SMTP</Label>
                        <Input id="username" v-model="form.username" autocomplete="off" placeholder="akun@domain.com" />
                        <InputError :message="form.errors.username" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="label-isian" for="password">Kata Sandi SMTP</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="props.pengaturan.password_tersimpan ? 'Tersimpan — isi hanya bila ingin mengganti' : 'Kata sandi SMTP'"
                        />
                        <p class="teks-bantu">
                            Untuk Gmail gunakan App Password, bukan kata sandi akun. Kata sandi disimpan terenkripsi dan tidak pernah ditampilkan
                            kembali.
                        </p>
                        <InputError :message="form.errors.password" />
                    </div>
                </div>

                <div class="grid content-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label class="label-isian" for="from_address">Email Pengirim</Label>
                        <Input id="from_address" v-model="form.from_address" type="email" placeholder="no-reply@domain.com" />
                        <InputError :message="form.errors.from_address" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="label-isian" for="from_name">Nama Pengirim</Label>
                        <Input id="from_name" v-model="form.from_name" placeholder="Nama institusi" />
                        <InputError :message="form.errors.from_name" />
                    </div>
                </div>
            </template>

            <div class="flex justify-end gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Simpan Pengaturan
                </Button>
            </div>
        </form>

        <div class="kartu p-6">
            <HeadingSmall title="Kirim Surel Uji" description="Memakai pengaturan yang sudah tersimpan di atas" />
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start" @submit.prevent="kirimUji">
                <div class="grid w-full gap-2 sm:max-w-sm">
                    <Label for="email_tujuan" class="sr-only">Email tujuan</Label>
                    <Input id="email_tujuan" v-model="formUji.email_tujuan" type="email" required placeholder="tujuan@domain.com" />
                    <InputError :message="formUji.errors.email_tujuan" />
                </div>
                <Button type="submit" variant="outline" :disabled="formUji.processing">
                    <LoaderCircle v-if="formUji.processing" class="animate-spin" />
                    Kirim Surel Uji
                </Button>
            </form>
        </div>

        <TemplateEmailEditor :template="props.template" />
    </PengaturanSistemLayout>
</template>
