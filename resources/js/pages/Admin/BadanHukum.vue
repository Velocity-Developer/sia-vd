<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface BadanHukum {
    id: number;
    nama_badan_hukum: string | null;
    tanggal_berdiri: string | null;
    nomor_akta_terakhir: string | null;
    tanggal_akta_terakhir: string | null;
    nomor_pengesahan: string | null;
    tanggal_pengesahan: string | null;
    alamat_jalan: string | null;
    provinsi_id: number | null;
    kota_id: number | null;
    kode_pos: string | null;
    telepon: string | null;
    faximili: string | null;
    email: string | null;
    website: string | null;
}

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    badanHukum: BadanHukum;
    provinsis: { id: number; nama: string }[];
    kotas: { id: number; provinsi_id: number; nama: string }[];
}>();

const form = useForm({
    nama_badan_hukum: props.badanHukum.nama_badan_hukum ?? '',
    tanggal_berdiri: props.badanHukum.tanggal_berdiri ?? '',
    nomor_akta_terakhir: props.badanHukum.nomor_akta_terakhir ?? '',
    tanggal_akta_terakhir: props.badanHukum.tanggal_akta_terakhir ?? '',
    nomor_pengesahan: props.badanHukum.nomor_pengesahan ?? '',
    tanggal_pengesahan: props.badanHukum.tanggal_pengesahan ?? '',
    alamat_jalan: props.badanHukum.alamat_jalan ?? '',
    provinsi_id: props.badanHukum.provinsi_id ?? ('' as number | ''),
    kota_id: props.badanHukum.kota_id ?? ('' as number | ''),
    kode_pos: props.badanHukum.kode_pos ?? '',
    telepon: props.badanHukum.telepon ?? '',
    faximili: props.badanHukum.faximili ?? '',
    email: props.badanHukum.email ?? '',
    website: props.badanHukum.website ?? '',
});

// Pilihan kota mengikuti provinsi; kota yang tidak termasuk provinsi baru dikosongkan.
const kotaPilihan = computed(() => props.kotas.filter((kota) => kota.provinsi_id === form.provinsi_id));
watch(
    () => form.provinsi_id,
    () => {
        if (!kotaPilihan.value.some((kota) => kota.id === form.kota_id)) form.kota_id = '';
    },
);

const submit = () => form.put(route('admin.badan-hukum.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Badan Hukum" />
    <AppLayout :breadcrumbs="[{ title: 'Badan Hukum', href: route('admin.badan-hukum.edit') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Badan Hukum</h1>
                        <p class="deskripsi-halaman">Kelola identitas badan hukum penyelenggara perguruan tinggi.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="kartu p-6" @submit.prevent="submit">
                    <div class="grid gap-2 sm:max-w-[200px]">
                        <Label for="id_badan_hukum" class="label-isian">ID Badan Hukum</Label>
                        <Input id="id_badan_hukum" :model-value="String(props.badanHukum.id)" disabled />
                    </div>

                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="nama_badan_hukum" class="label-isian">Nama Badan Hukum</Label>
                            <Input id="nama_badan_hukum" v-model="form.nama_badan_hukum" required />
                            <InputError :message="form.errors.nama_badan_hukum" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="tanggal_berdiri" class="label-isian">Tanggal Berdiri</Label>
                            <Input id="tanggal_berdiri" v-model="form.tanggal_berdiri" type="date" />
                            <InputError :message="form.errors.tanggal_berdiri" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="nomor_akta_terakhir" class="label-isian">Nomor Akta Terakhir</Label>
                            <Input id="nomor_akta_terakhir" v-model="form.nomor_akta_terakhir" />
                            <InputError :message="form.errors.nomor_akta_terakhir" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="tanggal_akta_terakhir" class="label-isian">Tanggal Akta Terakhir</Label>
                            <Input id="tanggal_akta_terakhir" v-model="form.tanggal_akta_terakhir" type="date" />
                            <InputError :message="form.errors.tanggal_akta_terakhir" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="nomor_pengesahan" class="label-isian">Nomor Pengesahan</Label>
                            <Input id="nomor_pengesahan" v-model="form.nomor_pengesahan" />
                            <InputError :message="form.errors.nomor_pengesahan" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="tanggal_pengesahan" class="label-isian">Tanggal Pengesahan</Label>
                            <Input id="tanggal_pengesahan" v-model="form.tanggal_pengesahan" type="date" />
                            <InputError :message="form.errors.tanggal_pengesahan" />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <Label for="alamat_jalan" class="label-isian">Alamat Jalan</Label>
                        <textarea
                            id="alamat_jalan"
                            v-model="form.alamat_jalan"
                            rows="2"
                            placeholder="Nama jalan dan nomor"
                            class="isian isian-area"
                        />
                        <InputError :message="form.errors.alamat_jalan" />
                    </div>

                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="provinsi_id" class="label-isian">Provinsi</Label>
                            <select id="provinsi_id" v-model="form.provinsi_id" class="isian isian-pilih">
                                <option value="">Pilih provinsi</option>
                                <option v-for="provinsi in props.provinsis" :key="provinsi.id" :value="provinsi.id">{{ provinsi.nama }}</option>
                            </select>
                            <InputError :message="form.errors.provinsi_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="kota_id" class="label-isian">Kota/Kabupaten</Label>
                            <select id="kota_id" v-model="form.kota_id" class="isian isian-pilih" :disabled="!form.provinsi_id">
                                <option value="">{{ form.provinsi_id ? 'Pilih kota/kabupaten' : 'Pilih provinsi dulu' }}</option>
                                <option v-for="kota in kotaPilihan" :key="kota.id" :value="kota.id">{{ kota.nama }}</option>
                            </select>
                            <InputError :message="form.errors.kota_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="kode_pos" class="label-isian">Kode Pos</Label>
                            <Input id="kode_pos" v-model="form.kode_pos" inputmode="numeric" maxlength="10" />
                            <InputError :message="form.errors.kode_pos" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="telepon" class="label-isian">Telepon</Label>
                            <Input id="telepon" v-model="form.telepon" placeholder="0411-123456" />
                            <InputError :message="form.errors.telepon" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="faximili" class="label-isian">Faximili</Label>
                            <Input id="faximili" v-model="form.faximili" />
                            <InputError :message="form.errors.faximili" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email" class="label-isian">Email</Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="info@example.or.id" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="website" class="label-isian">Website</Label>
                            <Input id="website" v-model="form.website" placeholder="https://example.or.id" />
                            <InputError :message="form.errors.website" />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2">
                        <p v-if="form.recentlySuccessful" class="text-sm text-[#1aae39]">Tersimpan.</p>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
