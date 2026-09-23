<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Institusi {
    nama_pt: string;
    singkatan: string | null;
    logo: string | null;
    logo_url: string | null;
    npsn: string | null;
    alamat: string | null;
    telepon: string | null;
    email: string | null;
    website: string | null;
    tahun_berdiri: number | null;
}

const props = defineProps<{ institusi: Institusi }>();

const page = usePage<{ flash: { success?: string; error?: string } }>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan Institusi',
        href: '/settings/institusi',
    },
];

const form = useForm<{
    nama_pt: string;
    singkatan: string;
    logo: File | null;
    npsn: string;
    alamat: string;
    telepon: string;
    email: string;
    website: string;
    tahun_berdiri: string;
}>({
    nama_pt: props.institusi.nama_pt ?? '',
    singkatan: props.institusi.singkatan ?? '',
    logo: null,
    npsn: props.institusi.npsn ?? '',
    alamat: props.institusi.alamat ?? '',
    telepon: props.institusi.telepon ?? '',
    email: props.institusi.email ?? '',
    website: props.institusi.website ?? '',
    tahun_berdiri: props.institusi.tahun_berdiri ? String(props.institusi.tahun_berdiri) : '',
});

const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(props.institusi.logo_url ?? null);

const pickFile = () => fileInput.value?.click();

const onFile = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    preview.value = file ? URL.createObjectURL(file) : (props.institusi.logo_url ?? null);
};

const removeFile = () => {
    form.logo = null;
    preview.value = props.institusi.logo_url ?? null;
    if (fileInput.value) fileInput.value.value = '';
};

const submit = () => {
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('institusi.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            // Berkas yang sudah terunggah dilepas dan pratinjau memakai logo tersimpan,
            // supaya yang tampil setelah menyimpan adalah data yang benar-benar ada di server.
            form.logo = null;
            if (fileInput.value) fileInput.value.value = '';
            preview.value = props.institusi.logo_url ?? null;
        },
    });
};

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pengaturan Institusi" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Pengaturan Institusi" description="Kelola identitas perguruan tinggi yang dipakai di seluruh aplikasi" />

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="space-y-6" enctype="multipart/form-data" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="nama_pt">Nama Perguruan Tinggi</Label>
                        <Input id="nama_pt" v-model="form.nama_pt" :class="inp" required />
                        <InputError :message="form.errors.nama_pt" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="singkatan">Singkatan/Nama Pendek</Label>
                        <Input id="singkatan" v-model="form.singkatan" :class="inp" />
                        <InputError :message="form.errors.singkatan" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="logo">Logo</Label>
                        <input id="logo" ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-[#e6e6e6] bg-[#f6f5f4]"
                            >
                                <img v-if="preview" :src="preview" alt="Logo institusi" class="size-full object-contain" />
                                <span v-else class="text-[11px] font-medium text-[#a39e98]">Belum ada</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white"
                                    @click="pickFile"
                                >
                                    <Upload class="mr-2 size-4" /> Pilih Logo
                                </Button>
                                <Button
                                    v-if="form.logo"
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-9 rounded-lg border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-white"
                                    aria-label="Batalkan logo baru"
                                    @click="removeFile"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <p class="text-xs text-[#615d59]">Format jpg, jpeg, png, atau webp. Maksimal 2 MB.</p>
                        <InputError :message="form.errors.logo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="npsn">NPSN/Kode Perguruan Tinggi</Label>
                        <Input id="npsn" v-model="form.npsn" :class="inp" />
                        <InputError :message="form.errors.npsn" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="alamat">Alamat</Label>
                        <textarea
                            id="alamat"
                            v-model="form.alamat"
                            rows="3"
                            placeholder="Jalan, kelurahan, kecamatan, kota, kode pos"
                            class="rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        />
                        <InputError :message="form.errors.alamat" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="telepon">Nomor Telepon</Label>
                        <Input id="telepon" v-model="form.telepon" :class="inp" placeholder="021-1234567" />
                        <InputError :message="form.errors.telepon" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email Resmi</Label>
                        <Input id="email" v-model="form.email" type="email" :class="inp" placeholder="info@example.ac.id" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="website">Website</Label>
                        <Input id="website" v-model="form.website" :class="inp" placeholder="https://example.ac.id" />
                        <InputError :message="form.errors.website" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="tahun_berdiri">Tahun Berdiri</Label>
                        <Input id="tahun_berdiri" v-model="form.tahun_berdiri" type="number" min="1000" max="2100" :class="inp" placeholder="2001" />
                        <InputError :message="form.errors.tahun_berdiri" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                        <p v-if="form.recentlySuccessful" class="text-sm text-[#615d59]">Tersimpan.</p>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
