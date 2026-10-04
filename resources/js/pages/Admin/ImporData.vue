<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, Upload } from 'lucide-vue-next';
import { ref } from 'vue';

type Hasil = { berhasil: number; galat: string[]; jumlahGalat: number };

const props = defineProps<{
    jenis: string;
    judul: string;
    akun: boolean;
    kolom: { judul: string; wajib: boolean; catatan: string }[];
    pilihanJenis: { id: string; name: string }[];
    maksBaris: number;
    hasil: Hasil | null;
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();

const jenis = ref<string | number>(props.jenis);
const gantiJenis = () => router.get(route('admin.impor.index', String(jenis.value)));

const berkasInput = ref<HTMLInputElement | null>(null);
const form = useForm<{ berkas: File | null; kirim_tautan_sandi: boolean }>({ berkas: null, kirim_tautan_sandi: false });
const pilihBerkas = (e: Event) => (form.berkas = (e.target as HTMLInputElement).files?.[0] ?? null);
const unggah = () =>
    form.post(route('admin.impor.store', props.jenis), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.berkas = null;
            if (berkasInput.value) berkasInput.value.value = '';
        },
    });
</script>

<template>
    <Head :title="`Impor ${props.judul}`" />
    <AppLayout :breadcrumbs="[{ title: `Impor ${props.judul}`, href: route('admin.impor.index', props.jenis) }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Impor {{ props.judul }} dari Excel</h1>
                        <p class="deskripsi-halaman">
                            Unduh template, isi satu baris per data, lalu unggah. Semua baris diperiksa dulu: bila ada yang salah, tidak ada data yang
                            disimpan.
                        </p>
                    </div>
                    <SelectFilter v-if="props.pilihanJenis.length > 1" v-model="jenis" label="Jenis data" @change="gantiJenis">
                        <option v-for="j in props.pilihanJenis" :key="j.id" :value="j.id">{{ j.name }}</option>
                    </SelectFilter>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <section v-if="props.hasil?.galat.length" class="kartu p-6">
                    <h2 class="judul-bagian">Galat di Berkas</h2>
                    <p class="teks-bantu mt-1">
                        {{ props.hasil.jumlahGalat }} galat<span v-if="props.hasil.jumlahGalat > props.hasil.galat.length">
                            (ditampilkan {{ props.hasil.galat.length }} pertama)</span
                        >. Nomor baris sesuai baris di Excel.
                    </p>
                    <ul class="mt-3 max-h-80 list-disc space-y-1 overflow-y-auto pl-5 text-sm text-[#b54a00]">
                        <li v-for="(g, i) in props.hasil.galat" :key="i">{{ g }}</li>
                    </ul>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">1. Unduh Template</h2>
                    <p class="teks-bantu mt-1">
                        Template berisi satu baris contoh, lembar Petunjuk, dan lembar referensi kode (mis. kode program studi). Maksimal
                        {{ props.maksBaris }} baris per berkas.
                    </p>
                    <Button as-child variant="outline" class="mt-4">
                        <a :href="route('admin.impor.template', props.jenis)"><Download /> Unduh Template {{ props.judul }}</a>
                    </Button>
                </section>

                <form class="kartu p-6" @submit.prevent="unggah">
                    <h2 class="judul-bagian">2. Unggah Berkas</h2>
                    <div class="mt-4 grid max-w-md gap-2">
                        <Label for="berkas" class="label-isian">Berkas Excel (.xlsx)</Label>
                        <input
                            id="berkas"
                            ref="berkasInput"
                            type="file"
                            accept=".xlsx,.xls"
                            class="isian py-1.5 file:mr-3 file:rounded file:border-0 file:bg-[#f6f5f4] file:px-3 file:py-1 file:text-sm"
                            @change="pilihBerkas"
                        />
                        <InputError :message="form.errors.berkas" />
                    </div>
                    <Label v-if="props.akun" for="kirim_tautan_sandi" class="label-isian mt-4 flex w-fit items-start gap-2.5 font-normal">
                        <Checkbox id="kirim_tautan_sandi" v-model="form.kirim_tautan_sandi" class="mt-0.5" />
                        <span>
                            Kirim email tautan atur kata sandi kepada akun yang kolom Kata Sandi-nya kosong. Perhatikan batas kirim email harian
                            server.
                        </span>
                    </Label>
                    <p v-if="props.akun" class="teks-bantu mt-3">
                        Akun hasil impor langsung aktif dan terverifikasi. Bila kolom Kata Sandi kosong dan email tidak dikirim, pengguna bisa memakai
                        Lupa Kata Sandi di halaman masuk.
                    </p>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing || !form.berkas"><Upload /> Impor</Button>
                    </div>
                </form>

                <section class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[640px]">
                            <thead>
                                <tr>
                                    <th>Kolom</th>
                                    <th>Wajib</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="k in props.kolom" :key="k.judul">
                                    <td class="font-medium text-black">{{ k.judul }}</td>
                                    <td>{{ k.wajib ? 'Ya' : '-' }}</td>
                                    <td class="teks-bantu">{{ k.catatan || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
