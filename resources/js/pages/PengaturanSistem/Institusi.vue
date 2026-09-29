<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
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
    zona_waktu: string;
}

const props = defineProps<{ institusi: Institusi; zonaWaktu: { value: string; label: string }[] }>();

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
    zona_waktu: string;
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
    zona_waktu: props.institusi.zona_waktu ?? 'Asia/Jakarta',
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
</script>

<template>
    <PengaturanSistemLayout>
        <Head title="Pengaturan Institusi" />

        <form class="kartu p-6" enctype="multipart/form-data" @submit.prevent="submit">
            <HeadingSmall title="Pengaturan Institusi" description="Kelola identitas perguruan tinggi yang dipakai di seluruh aplikasi" />

            <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nama_pt" class="label-isian">Nama Perguruan Tinggi</Label>
                    <Input id="nama_pt" v-model="form.nama_pt" required />
                    <InputError :message="form.errors.nama_pt" />
                </div>

                <div class="grid gap-2">
                    <Label for="singkatan" class="label-isian">Singkatan/Nama Pendek</Label>
                    <Input id="singkatan" v-model="form.singkatan" />
                    <InputError :message="form.errors.singkatan" />
                </div>
            </div>

            <div class="mt-4 grid gap-2">
                <Label for="logo" class="label-isian">Logo</Label>
                <input id="logo" ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] dark:border-border dark:bg-muted"
                    >
                        <img v-if="preview" :src="preview" alt="Logo institusi" class="size-full object-contain" />
                        <span v-else class="teks-bantu">Belum ada</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button type="button" variant="outline" @click="pickFile"><Upload /> Pilih Logo</Button>
                        <Button
                            v-if="form.logo"
                            type="button"
                            variant="outline"
                            size="icon"
                            class="text-[#dd5b00]"
                            aria-label="Batalkan logo baru"
                            @click="removeFile"
                        >
                            <X />
                        </Button>
                    </div>
                </div>
                <p class="teks-bantu">Format jpg, jpeg, png, atau webp. Maksimal 2 MB.</p>
                <InputError :message="form.errors.logo" />
            </div>

            <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="npsn" class="label-isian">NPSN/Kode Perguruan Tinggi</Label>
                    <Input id="npsn" v-model="form.npsn" />
                    <InputError :message="form.errors.npsn" />
                </div>

                <div class="grid gap-2">
                    <Label for="tahun_berdiri" class="label-isian">Tahun Berdiri</Label>
                    <Input id="tahun_berdiri" v-model="form.tahun_berdiri" type="number" min="1000" max="2100" placeholder="2001" />
                    <InputError :message="form.errors.tahun_berdiri" />
                </div>
            </div>

            <div class="mt-4 grid gap-2">
                <Label for="alamat" class="label-isian">Alamat</Label>
                <textarea
                    id="alamat"
                    v-model="form.alamat"
                    rows="3"
                    placeholder="Jalan, kelurahan, kecamatan, kota, kode pos"
                    class="isian isian-area"
                />
                <InputError :message="form.errors.alamat" />
            </div>

            <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="telepon" class="label-isian">Nomor Telepon</Label>
                    <Input id="telepon" v-model="form.telepon" placeholder="021-1234567" />
                    <InputError :message="form.errors.telepon" />
                </div>

                <div class="grid gap-2">
                    <Label for="email" class="label-isian">Email Resmi</Label>
                    <Input id="email" v-model="form.email" type="email" placeholder="info@example.ac.id" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="website" class="label-isian">Website</Label>
                    <Input id="website" v-model="form.website" placeholder="https://example.ac.id" />
                    <InputError :message="form.errors.website" />
                </div>

                <div class="grid gap-2">
                    <Label for="zona_waktu" class="label-isian">Zona Waktu</Label>
                    <select id="zona_waktu" v-model="form.zona_waktu" class="isian isian-pilih">
                        <option v-for="zona in zonaWaktu" :key="zona.value" :value="zona.value">{{ zona.label }}</option>
                    </select>
                    <InputError :message="form.errors.zona_waktu" />
                </div>
            </div>
            <p class="teks-bantu mt-2">
                Zona waktu dipakai untuk jam sekarang di seluruh sistem (periode KRS, batas waktu tugas dan ujian, presensi) serta label WIB/WITA/WIT.
                Jadwal dan tenggat yang sudah diisi tidak digeser; catatan waktu otomatis yang sudah ada (mis. waktu kumpul tugas) tetap tercatat
                dengan zona lama, jadi sebaiknya diatur sekali di awal.
            </p>

            <div class="mt-6 flex items-center justify-end gap-2">
                <p v-if="form.recentlySuccessful" class="text-sm text-[#1aae39]">Tersimpan.</p>
                <Button type="submit" :disabled="form.processing">Simpan</Button>
            </div>
        </form>
    </PengaturanSistemLayout>
</template>
