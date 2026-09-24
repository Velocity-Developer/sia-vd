<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PengaturanSistemLayout from '@/layouts/PengaturanSistemLayout.vue';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ImageUp, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Pengaturan = {
    nama_aplikasi: string | null;
    login_judul: string | null;
    login_teks: string | null;
    login_sorotan: boolean;
    login_tata_letak: 'panel' | 'tengah';
    sidebar_bawaan: 'lebar' | 'ringkas';
    favicon_url: string | null;
    login_gambar_url: string | null;
};

const props = defineProps<{ pengaturan: Pengaturan; bawaan: { login_judul: string; login_teks: string } }>();
const page = usePage<SharedData>();

const form = useForm({
    nama_aplikasi: props.pengaturan.nama_aplikasi ?? '',
    login_judul: props.pengaturan.login_judul ?? '',
    login_teks: props.pengaturan.login_teks ?? '',
    login_sorotan: props.pengaturan.login_sorotan,
    login_tata_letak: props.pengaturan.login_tata_letak,
    sidebar_bawaan: props.pengaturan.sidebar_bawaan,
    favicon: null as File | null,
    hapus_favicon: false,
    login_gambar: null as File | null,
    hapus_login_gambar: false,
});

// Pratinjau berkas yang baru dipilih; bila dihapus, kembali ke bawaan.
const pratinjauFavicon = ref<string | null>(props.pengaturan.favicon_url);
const pratinjauGambar = ref<string | null>(props.pengaturan.login_gambar_url);
const pilih = (event: Event, kolom: 'favicon' | 'login_gambar') => {
    const berkas = (event.target as HTMLInputElement).files?.[0] ?? null;
    form[kolom] = berkas;
    form[kolom === 'favicon' ? 'hapus_favicon' : 'hapus_login_gambar'] = false;
    const url = berkas ? URL.createObjectURL(berkas) : null;
    if (kolom === 'favicon') pratinjauFavicon.value = url ?? props.pengaturan.favicon_url;
    else pratinjauGambar.value = url ?? props.pengaturan.login_gambar_url;
};
const hapus = (kolom: 'favicon' | 'login_gambar') => {
    form[kolom] = null;
    form[kolom === 'favicon' ? 'hapus_favicon' : 'hapus_login_gambar'] = true;
    if (kolom === 'favicon') pratinjauFavicon.value = null;
    else pratinjauGambar.value = null;
};

// Nilai yang benar-benar dipakai bila kolom dikosongkan.
const namaBawaan = computed(() => page.props.institusi?.singkatan || page.props.institusi?.nama_pt);
const faviconTampil = computed(() => pratinjauFavicon.value ?? page.props.institusi?.logo_url ?? '/favicon.ico');

const simpan = () =>
    form
        .transform((data) => ({
            ...data,
            login_sorotan: data.login_sorotan ? 1 : 0,
            hapus_favicon: data.hapus_favicon ? 1 : 0,
            hapus_login_gambar: data.hapus_login_gambar ? 1 : 0,
        }))
        .post(route('pengaturan-tampilan.update'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.reset('favicon', 'login_gambar'),
        });

const tataLetak = [
    { value: 'panel', judul: 'Panel samping', teks: 'Panel merek di kiri, form masuk di kanan' },
    { value: 'tengah', judul: 'Kartu di tengah', teks: 'Form masuk di tengah layar, logo di atasnya' },
] as const;

const kartu = 'rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm';
const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
</script>

<template>
    <Head title="Pengaturan Tampilan" />
    <PengaturanSistemLayout>
        <form class="flex flex-col gap-4" @submit.prevent="simpan">
            <section :class="kartu">
                <h2 class="text-lg font-semibold text-black">Identitas di browser</h2>
                <p class="mt-1 text-sm text-[#615d59]">Nama dan ikon yang tampil di tab browser serta bookmark.</p>

                <div class="mt-5 grid content-start items-start gap-6 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="nama_aplikasi">Nama di tab browser</Label>
                        <Input id="nama_aplikasi" v-model="form.nama_aplikasi" maxlength="100" :placeholder="namaBawaan" :class="inp" />
                        <p class="text-xs text-[#a39e98]">
                            Kosongkan untuk memakai singkatan/nama institusi. Contoh tab: "Dashboard - {{ form.nama_aplikasi || namaBawaan }}".
                        </p>
                        <InputError :message="form.errors.nama_aplikasi" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="favicon">Favicon</Label>
                        <div class="flex items-center gap-3">
                            <div class="flex size-12 items-center justify-center rounded-lg border border-[#e6e6e6] bg-[#f6f5f4]">
                                <img :src="faviconTampil" alt="Favicon" class="size-8 object-contain" />
                            </div>
                            <label
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-[#dddddd] bg-white px-3 py-2 text-sm font-medium hover:bg-[#f6f5f4]"
                            >
                                <ImageUp class="size-4" /> Pilih berkas
                                <input id="favicon" type="file" accept=".png,.ico,.webp" class="sr-only" @change="pilih($event, 'favicon')" />
                            </label>
                            <Button v-if="pratinjauFavicon" type="button" variant="ghost" size="sm" @click="hapus('favicon')"
                                ><X class="mr-1 size-4" /> Hapus</Button
                            >
                        </div>
                        <p class="text-xs text-[#a39e98]">PNG, ICO, atau WEBP persegi, maks. 512 KB. Tanpa favicon, dipakai logo institusi.</p>
                        <InputError :message="form.errors.favicon" />
                    </div>
                </div>
            </section>

            <section :class="kartu">
                <h2 class="text-lg font-semibold text-black">Halaman masuk</h2>
                <p class="mt-1 text-sm text-[#615d59]">Berlaku juga untuk halaman lupa dan atur ulang kata sandi.</p>

                <div class="mt-5 grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Tata letak halaman masuk">
                    <label
                        v-for="opsi in tataLetak"
                        :key="opsi.value"
                        class="flex cursor-pointer gap-3 rounded-lg border p-3"
                        :class="form.login_tata_letak === opsi.value ? 'border-[#0075de] bg-[#f6f9fd]' : 'border-[#e6e6e6]'"
                    >
                        <input v-model="form.login_tata_letak" type="radio" :value="opsi.value" class="mt-1 size-4 shrink-0 accent-[#0075de]" />
                        <span class="flex-1">
                            <span class="block text-sm font-medium text-black">{{ opsi.judul }}</span>
                            <span class="block text-xs text-[#615d59]">{{ opsi.teks }}</span>
                            <!-- Sketsa kecil tata letak -->
                            <span class="mt-2 flex h-14 overflow-hidden rounded border border-[#e6e6e6] bg-[#f6f5f4]" aria-hidden="true">
                                <template v-if="opsi.value === 'panel'">
                                    <span class="w-3/5 bg-[#0075de]" />
                                    <span class="flex flex-1 items-center justify-center"
                                        ><span class="h-8 w-3/5 rounded-sm bg-white shadow-sm"
                                    /></span>
                                </template>
                                <span v-else class="flex flex-1 items-center justify-center"
                                    ><span class="h-9 w-1/3 rounded-sm bg-white shadow-sm"
                                /></span>
                            </span>
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.login_tata_letak" />

                <div class="mt-5 grid content-start items-start gap-6 lg:grid-cols-[1fr,280px]">
                    <div class="grid content-start gap-4">
                        <div class="grid content-start gap-2">
                            <Label for="login_judul">Judul</Label>
                            <Input id="login_judul" v-model="form.login_judul" maxlength="120" :placeholder="props.bawaan.login_judul" :class="inp" />
                            <InputError :message="form.errors.login_judul" />
                        </div>
                        <div v-if="form.login_tata_letak === 'panel'" class="grid content-start gap-2">
                            <Label for="login_teks">Teks sambutan</Label>
                            <textarea
                                id="login_teks"
                                v-model="form.login_teks"
                                rows="3"
                                maxlength="300"
                                :placeholder="props.bawaan.login_teks"
                                class="rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                            />
                            <p class="text-xs text-[#a39e98]">Kosongkan untuk memakai teks bawaan.</p>
                            <InputError :message="form.errors.login_teks" />
                        </div>
                        <p v-if="form.login_tata_letak === 'tengah'" class="-mt-2 text-xs text-[#a39e98]">
                            Pada kartu di tengah, judul tampil di bawah nama institusi; teks sambutan dan daftar fitur tidak ditampilkan.
                        </p>
                        <Label v-else for="login_sorotan" class="flex w-fit items-center gap-2.5 text-sm text-[#31302e]">
                            <Checkbox id="login_sorotan" v-model="form.login_sorotan" />
                            <span>Tampilkan daftar fitur (Rencana Studi, Materi &amp; Tugas, Hasil Studi)</span>
                        </Label>
                        <div class="grid content-start gap-2">
                            <Label for="login_gambar">Gambar latar</Label>
                            <div class="flex items-center gap-3">
                                <label
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-[#dddddd] bg-white px-3 py-2 text-sm font-medium hover:bg-[#f6f5f4]"
                                >
                                    <ImageUp class="size-4" /> Pilih gambar
                                    <input
                                        id="login_gambar"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        class="sr-only"
                                        @change="pilih($event, 'login_gambar')"
                                    />
                                </label>
                                <Button v-if="pratinjauGambar" type="button" variant="ghost" size="sm" @click="hapus('login_gambar')"
                                    ><X class="mr-1 size-4" /> Hapus</Button
                                >
                            </div>
                            <p class="text-xs text-[#a39e98]">
                                JPG/PNG/WEBP maks. 2 MB, mis. foto kampus. Diberi lapisan warna agar teks tetap terbaca.
                            </p>
                            <InputError :message="form.errors.login_gambar" />
                        </div>
                    </div>

                    <!-- Pratinjau panel merek -->
                    <div
                        v-if="form.login_tata_letak === 'panel'"
                        class="relative flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-xl bg-[#0075de] bg-cover bg-center p-5 text-white"
                        :style="pratinjauGambar ? { backgroundImage: `url(${pratinjauGambar})` } : undefined"
                        aria-label="Pratinjau panel halaman masuk"
                    >
                        <div v-if="pratinjauGambar" class="absolute inset-0 bg-[#0075de]/80" aria-hidden="true" />
                        <div class="relative">
                            <p class="text-lg font-bold leading-tight">{{ form.login_judul || props.bawaan.login_judul }}</p>
                            <p class="mt-1 text-xs leading-4 text-white/80">{{ form.login_teks || props.bawaan.login_teks }}</p>
                            <div v-if="form.login_sorotan" class="mt-3 space-y-1.5">
                                <div v-for="n in 3" :key="n" class="h-2 rounded bg-white/25" :style="{ width: `${90 - n * 15}%` }" />
                            </div>
                        </div>
                    </div>

                    <!-- Pratinjau kartu di tengah -->
                    <div
                        v-else
                        class="relative flex aspect-[3/4] flex-col items-center justify-center overflow-hidden rounded-xl bg-[#f6f5f4] bg-cover bg-center p-5"
                        :style="pratinjauGambar ? { backgroundImage: `url(${pratinjauGambar})` } : undefined"
                        aria-label="Pratinjau kartu halaman masuk"
                    >
                        <div v-if="pratinjauGambar" class="absolute inset-0 bg-[#0075de]/80" aria-hidden="true" />
                        <div class="relative flex w-full flex-col items-center text-center">
                            <img
                                :src="page.props.institusi?.logo_url ?? faviconTampil"
                                alt=""
                                class="size-8 rounded-lg bg-white object-contain p-1 shadow-sm"
                            />
                            <p class="mt-2 text-xs font-semibold" :class="pratinjauGambar ? 'text-white' : 'text-black'">
                                {{ page.props.institusi?.nama_pt }}
                            </p>
                            <p class="text-[10px]" :class="pratinjauGambar ? 'text-white/80' : 'text-[#615d59]'">
                                {{ form.login_judul || props.bawaan.login_judul }}
                            </p>
                            <div class="mt-3 w-4/5 space-y-2 rounded-lg bg-white p-3 shadow-md">
                                <div class="h-2.5 w-1/2 rounded bg-[#31302e]/70" />
                                <div class="h-5 rounded border border-[#e6e6e6]" />
                                <div class="h-5 rounded border border-[#e6e6e6]" />
                                <div class="h-5 rounded bg-[#0075de]" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section :class="kartu">
                <h2 class="text-lg font-semibold text-black">Sidebar</h2>
                <p class="mt-1 text-sm text-[#615d59]">
                    Bentuk sidebar saat pengguna pertama kali masuk. Setelah pengguna membuka/menutup sidebar sendiri, pilihannya diingat di
                    browsernya.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <label
                        v-for="opsi in [
                            { value: 'lebar', judul: 'Lebar', teks: 'Ikon dan nama menu' },
                            { value: 'ringkas', judul: 'Ringkas', teks: 'Ikon saja, lebih banyak ruang' },
                        ]"
                        :key="opsi.value"
                        class="flex min-w-[220px] cursor-pointer items-start gap-3 rounded-lg border p-3"
                        :class="form.sidebar_bawaan === opsi.value ? 'border-[#0075de] bg-[#f6f9fd]' : 'border-[#e6e6e6]'"
                    >
                        <input v-model="form.sidebar_bawaan" type="radio" :value="opsi.value" class="mt-1 size-4 accent-[#0075de]" />
                        <span>
                            <span class="block text-sm font-medium text-black">{{ opsi.judul }}</span>
                            <span class="block text-xs text-[#615d59]">{{ opsi.teks }}</span>
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.sidebar_bawaan" />
            </section>

            <div class="flex justify-end">
                <Button type="submit" class="h-10 rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]" :disabled="form.processing"
                    >Simpan</Button
                >
            </div>
        </form>
    </PengaturanSistemLayout>
</template>
