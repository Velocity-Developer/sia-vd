<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

interface Pengaturan {
    aktif: boolean;
    untuk_dosen: boolean;
    untuk_mahasiswa: boolean;
    pesan: string | null;
    perkiraan_selesai: string | null;
}

const props = defineProps<{ pengaturan: Pengaturan; pesanBawaan: string }>();

const form = useForm({
    aktif: props.pengaturan.aktif,
    untuk_dosen: props.pengaturan.untuk_dosen,
    untuk_mahasiswa: props.pengaturan.untuk_mahasiswa,
    pesan: props.pengaturan.pesan ?? '',
    perkiraan_selesai: props.pengaturan.perkiraan_selesai ?? '',
});

const simpan = () =>
    form
        .transform((data) => ({
            ...data,
            aktif: data.aktif ? 1 : 0,
            untuk_dosen: data.untuk_dosen ? 1 : 0,
            untuk_mahasiswa: data.untuk_mahasiswa ? 1 : 0,
        }))
        .put(route('pengaturan-maintenance.update'), { preserveScroll: true });
</script>

<template>
    <PengaturanSistemLayout>
        <Head title="Pengaturan Maintenance" />

        <form class="kartu grid gap-6 p-6" @submit.prevent="simpan">
            <HeadingSmall
                title="Mode Maintenance"
                description="Menutup sementara sistem bagi dosen dan/atau mahasiswa, misalnya saat perbaikan data atau pembaruan sistem"
            />

            <div class="grid gap-2">
                <Label for="aktif" class="label-isian flex w-fit items-center gap-2.5 font-normal">
                    <Checkbox id="aktif" v-model="form.aktif" />
                    <span class="font-medium">Aktifkan mode maintenance</span>
                </Label>
                <p class="teks-bantu">
                    Admin/karyawan tidak terkena dan tetap bisa masuk seperti biasa, begitu juga pengguna lain yang punya izin Pengaturan Maintenance.
                </p>
            </div>

            <div class="grid gap-2">
                <Label class="label-isian">Berlaku Untuk</Label>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <Label for="untuk_dosen" class="label-isian flex w-fit items-center gap-2.5 font-normal">
                        <Checkbox id="untuk_dosen" v-model="form.untuk_dosen" />
                        <span>Dosen</span>
                    </Label>
                    <Label for="untuk_mahasiswa" class="label-isian flex w-fit items-center gap-2.5 font-normal">
                        <Checkbox id="untuk_mahasiswa" v-model="form.untuk_mahasiswa" />
                        <span>Mahasiswa</span>
                    </Label>
                </div>
                <p class="teks-bantu">
                    Pengguna yang terkena tidak bisa masuk, dan yang sedang membuka sistem langsung melihat halaman pemeliharaan.
                </p>
                <InputError :message="(form.errors as Record<string, string>).untuk" />
            </div>

            <div class="grid gap-2">
                <Label class="label-isian" for="pesan">Pesan</Label>
                <textarea id="pesan" v-model="form.pesan" rows="3" maxlength="500" class="isian isian-area" :placeholder="pesanBawaan" />
                <p class="teks-bantu">Kosongkan untuk memakai pesan bawaan. Pesan juga tampil di halaman masuk.</p>
                <InputError :message="form.errors.pesan" />
            </div>

            <div class="grid gap-2 sm:max-w-xs">
                <Label class="label-isian" for="perkiraan_selesai">Perkiraan Selesai</Label>
                <Input id="perkiraan_selesai" v-model="form.perkiraan_selesai" type="datetime-local" />
                <p class="teks-bantu">Opsional. Hanya informasi; mode maintenance tidak mati otomatis.</p>
                <InputError :message="form.errors.perkiraan_selesai" />
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
