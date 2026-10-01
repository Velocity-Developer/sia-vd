<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Upload, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Institusi {
    nama_pt: string;
    badan_hukum_id: number | null;
    singkatan: string | null;
    logo: string | null;
    logo_url: string | null;
    npsn: string | null;
    alamat: string | null;
    alamat_lain: string | null;
    provinsi_id: number | null;
    kota_id: number | null;
    kode_pos: string | null;
    telepon: string | null;
    faximili: string | null;
    email: string | null;
    website: string | null;
    tahun_berdiri: number | null;
    nomor_akta_terakhir: string | null;
    tanggal_akta_terakhir: string | null;
    nomor_pengesahan: string | null;
    tanggal_pengesahan: string | null;
    akreditasi: string | null;
    zona_waktu: string;
}

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    institusi: Institusi;
    zonaWaktu: { value: string; label: string }[];
    badanHukums: { id: number; nama_badan_hukum: string | null }[];
    provinsis: { id: number; nama: string }[];
    kotas: { id: number; provinsi_id: number; nama: string }[];
}>();

const form = useForm<{
    nama_pt: string;
    badan_hukum_id: number | '';
    singkatan: string;
    logo: File | null;
    npsn: string;
    alamat: string;
    alamat_lain: string;
    provinsi_id: number | '';
    kota_id: number | '';
    kode_pos: string;
    telepon: string;
    faximili: string;
    email: string;
    website: string;
    tahun_berdiri: string;
    nomor_akta_terakhir: string;
    tanggal_akta_terakhir: string;
    nomor_pengesahan: string;
    tanggal_pengesahan: string;
    akreditasi: string;
    zona_waktu: string;
}>({
    nama_pt: props.institusi.nama_pt ?? '',
    badan_hukum_id: props.institusi.badan_hukum_id ?? '',
    singkatan: props.institusi.singkatan ?? '',
    logo: null,
    npsn: props.institusi.npsn ?? '',
    alamat: props.institusi.alamat ?? '',
    alamat_lain: props.institusi.alamat_lain ?? '',
    provinsi_id: props.institusi.provinsi_id ?? '',
    kota_id: props.institusi.kota_id ?? '',
    kode_pos: props.institusi.kode_pos ?? '',
    telepon: props.institusi.telepon ?? '',
    faximili: props.institusi.faximili ?? '',
    email: props.institusi.email ?? '',
    website: props.institusi.website ?? '',
    tahun_berdiri: props.institusi.tahun_berdiri ? String(props.institusi.tahun_berdiri) : '',
    nomor_akta_terakhir: props.institusi.nomor_akta_terakhir ?? '',
    tanggal_akta_terakhir: props.institusi.tanggal_akta_terakhir ?? '',
    nomor_pengesahan: props.institusi.nomor_pengesahan ?? '',
    tanggal_pengesahan: props.institusi.tanggal_pengesahan ?? '',
    akreditasi: props.institusi.akreditasi ?? '',
    zona_waktu: props.institusi.zona_waktu ?? 'Asia/Jakarta',
});

// Pilihan kota mengikuti provinsi; kota yang tidak termasuk provinsi baru dikosongkan.
const kotaPilihan = computed(() => props.kotas.filter((kota) => kota.provinsi_id === form.provinsi_id));
watch(
    () => form.provinsi_id,
    () => {
        if (!kotaPilihan.value.some((kota) => kota.id === form.kota_id)) form.kota_id = '';
    },
);

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
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('admin.perguruan-tinggi.update'), {
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
    <Head title="Perguruan Tinggi" />
    <AppLayout :breadcrumbs="[{ title: 'Perguruan Tinggi', href: route('admin.perguruan-tinggi.edit') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Perguruan Tinggi</h1>
                        <p class="deskripsi-halaman">Kelola identitas perguruan tinggi yang dipakai di seluruh aplikasi.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" enctype="multipart/form-data" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Identitas</h2>
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

                            <div class="grid gap-2">
                                <Label for="badan_hukum_id" class="label-isian">Badan Hukum</Label>
                                <select id="badan_hukum_id" v-model="form.badan_hukum_id" class="isian isian-pilih">
                                    <option value="">Pilih badan hukum</option>
                                    <option v-for="badanHukum in props.badanHukums" :key="badanHukum.id" :value="badanHukum.id">
                                        {{ badanHukum.id }} — {{ badanHukum.nama_badan_hukum ?? '(nama belum diisi)' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.badan_hukum_id" />
                            </div>

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

                            <div class="grid gap-2">
                                <Label for="akreditasi" class="label-isian">Akreditasi</Label>
                                <Input id="akreditasi" v-model="form.akreditasi" placeholder="Baik Sekali" />
                                <InputError :message="form.errors.akreditasi" />
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
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Akta &amp; Pengesahan</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
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
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Alamat &amp; Kontak</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="alamat" class="label-isian">Alamat</Label>
                            <textarea id="alamat" v-model="form.alamat" rows="2" placeholder="Nama jalan dan nomor" class="isian isian-area" />
                            <InputError :message="form.errors.alamat" />
                        </div>

                        <div class="mt-4 grid gap-2">
                            <Label for="alamat_lain" class="label-isian">Alamat Lain</Label>
                            <textarea
                                id="alamat_lain"
                                v-model="form.alamat_lain"
                                rows="2"
                                placeholder="Kampus atau kantor lain"
                                class="isian isian-area"
                            />
                            <InputError :message="form.errors.alamat_lain" />
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
                                <Label for="telepon" class="label-isian">Nomor Telepon</Label>
                                <Input id="telepon" v-model="form.telepon" placeholder="021-1234567" />
                                <InputError :message="form.errors.telepon" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="faximili" class="label-isian">Faximili</Label>
                                <Input id="faximili" v-model="form.faximili" />
                                <InputError :message="form.errors.faximili" />
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
                            Zona waktu dipakai untuk jam sekarang di seluruh sistem (periode KRS, batas waktu tugas dan ujian, presensi) serta label
                            WIB/WITA/WIT. Jadwal dan tenggat yang sudah diisi tidak digeser; catatan waktu otomatis yang sudah ada (mis. waktu kumpul
                            tugas) tetap tercatat dengan zona lama, jadi sebaiknya diatur sekali di awal.
                        </p>
                    </section>

                    <div class="flex items-center justify-end gap-2">
                        <p v-if="form.recentlySuccessful" class="text-sm text-[#1aae39]">Tersimpan.</p>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
