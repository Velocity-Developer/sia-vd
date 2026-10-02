<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Mahasiswa = { nim: string; nama: string; status: string; prodi: string | null; dosen_wali: string | null };

// `mahasiswa` sudah urut NIM dari server (Aktif dan Pindahan saja), jadi rentang = potongan daftar ini.
const props = defineProps<{
    mahasiswa: Mahasiswa[];
    dosen: { id: number; name: string }[];
}>();

const form = useForm({ nim_awal: '', nim_akhir: '', dosen_wali_id: null as number | null });

// SearchSelect memakai id angka: id = posisi di daftar + 1.
const opsiNim = computed(() => props.mahasiswa.map((mhs, index) => ({ id: index + 1, name: `${mhs.nim} — ${mhs.nama}` })));
const posisi = (nim: string) => props.mahasiswa.findIndex((mhs) => mhs.nim === nim) + 1 || null;
const pilihNim = (kolom: 'nim_awal' | 'nim_akhir', id: number) => (form[kolom] = props.mahasiswa[id - 1]?.nim ?? '');

const awal = computed(() => posisi(form.nim_awal));
const akhir = computed(() => posisi(form.nim_akhir));
const terbalik = computed(() => awal.value !== null && akhir.value !== null && awal.value > akhir.value);
const dalamRentang = computed(() => (awal.value && akhir.value && !terbalik.value ? props.mahasiswa.slice(awal.value - 1, akhir.value) : []));

const submit = () =>
    form.put(route('admin.penasehat-akademik.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <Head title="Set Penasehat Akademik" />
    <AppLayout :breadcrumbs="[{ title: 'Set Penasehat Akademik', href: route('admin.penasehat-akademik.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Set Penasehat Akademik</h1>
                        <p class="deskripsi-halaman">
                            Atur dosen wali sekaligus untuk mahasiswa berstatus Aktif atau Pindahan dalam satu rentang NIM.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Rentang NIM dan Pembimbing</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="nim_awal" class="label-isian">NIM Awal</Label>
                                <SearchSelect
                                    id="nim_awal"
                                    :model-value="awal"
                                    :options="opsiNim"
                                    placeholder="Pilih NIM awal"
                                    search-placeholder="Cari NIM atau nama"
                                    required
                                    @update:model-value="pilihNim('nim_awal', $event)"
                                />
                                <InputError :message="form.errors.nim_awal" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nim_akhir" class="label-isian">NIM Akhir</Label>
                                <SearchSelect
                                    id="nim_akhir"
                                    :model-value="akhir"
                                    :options="opsiNim"
                                    placeholder="Pilih NIM akhir"
                                    search-placeholder="Cari NIM atau nama"
                                    required
                                    @update:model-value="pilihNim('nim_akhir', $event)"
                                />
                                <InputError :message="terbalik ? 'NIM akhir harus sama dengan atau sesudah NIM awal.' : form.errors.nim_akhir" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="dosen_wali_id" class="label-isian">Pembimbing Akademik</Label>
                                <SearchSelect
                                    id="dosen_wali_id"
                                    v-model="form.dosen_wali_id"
                                    :options="props.dosen"
                                    placeholder="Pilih dosen"
                                    search-placeholder="Cari nama dosen"
                                    required
                                />
                                <InputError :message="form.errors.dosen_wali_id" />
                            </div>
                        </div>
                        <p class="teks-bantu mt-3">
                            Pilihan NIM hanya mahasiswa berstatus Aktif atau Pindahan. Dosen wali lama mahasiswa dalam rentang akan diganti.
                        </p>
                    </section>

                    <div class="flex items-center justify-end gap-3">
                        <p v-if="dalamRentang.length" class="info-jumlah">
                            <span class="font-medium text-black">{{ dalamRentang.length }}</span> mahasiswa akan diubah
                        </p>
                        <Button type="submit" :disabled="form.processing || !dalamRentang.length || !form.dosen_wali_id">Simpan</Button>
                    </div>
                </form>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Program Studi</th>
                                    <th>Status</th>
                                    <th>Dosen Wali Saat Ini</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(mhs, index) in dalamRentang" :key="mhs.nim">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="font-medium">{{ mhs.nim }}</td>
                                    <td>{{ mhs.nama }}</td>
                                    <td>{{ mhs.prodi ?? '-' }}</td>
                                    <td>{{ mhs.status }}</td>
                                    <td>{{ mhs.dosen_wali ?? '-' }}</td>
                                </tr>
                                <tr v-if="!dalamRentang.length">
                                    <td colspan="6" class="tabel-kosong">
                                        {{
                                            props.mahasiswa.length
                                                ? 'Pilih NIM awal dan NIM akhir untuk melihat mahasiswa dalam rentang.'
                                                : 'Belum ada mahasiswa Aktif atau Pindahan yang memiliki NIM.'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
