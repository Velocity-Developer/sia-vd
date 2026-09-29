<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';

type Jenis = {
    id: number;
    kode: string;
    nama: string;
    cara_hitung: string;
    kategori: string;
    keterangan: string | null;
    aktif: boolean;
    urutan: number;
    tarif_count: number;
};

const props = defineProps<{ jenisBiaya: Jenis[] }>();
const page = usePage<{ flash?: { success?: string; error?: string } }>();

const caraHitung = (jenis: { cara_hitung: string; kategori: string }) => {
    if (jenis.kategori === 'semester') return jenis.cara_hitung === 'per_sks' ? 'Per SKS' : 'Tetap per semester';
    if (['pendadaran', 'wisuda', 'cuti'].includes(jenis.kategori)) return 'Tetap';
    if (jenis.cara_hitung === 'per_sks') return 'Per SKS mata kuliah';

    return jenis.kategori === 'susulan' ? 'Tetap per ujian' : 'Tetap per mata kuliah';
};
const LABEL_KATEGORI: Record<string, string> = {
    remidi: 'Remidi',
    susulan: 'Susulan',
    pendadaran: 'Info pendadaran',
    wisuda: 'Info wisuda',
    cuti: 'Info cuti',
};

const hapus = (jenis: Jenis) => {
    if (!confirm(`Hapus jenis biaya "${jenis.nama}"? Tagihan yang sudah terbit tidak berubah.`)) return;

    router.delete(route('admin.jenis-biaya.destroy', jenis.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Jenis Biaya" />
    <AppLayout :breadcrumbs="[{ title: 'Jenis Biaya', href: route('admin.jenis-biaya.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Jenis Biaya</h1>
                        <p class="deskripsi-halaman">Komponen biaya kuliah beserta tarifnya per program studi dan angkatan.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.jenis-biaya.create')">Tambah Jenis Biaya</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[780px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode</th>
                                    <th>Nama</th>
                                    <th>Cara Hitung</th>
                                    <th class="text-center">Tarif</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(jenis, index) in props.jenisBiaya" :key="jenis.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="font-medium text-black">{{ jenis.kode }}</td>
                                    <td>
                                        <span class="block">{{ jenis.nama }}</span>
                                        <span v-if="jenis.keterangan" class="teks-bantu block">{{ jenis.keterangan }}</span>
                                    </td>
                                    <td>
                                        {{ caraHitung(jenis) }}
                                        <span
                                            v-if="LABEL_KATEGORI[jenis.kategori]"
                                            class="ml-1 rounded-full bg-[#dd5b00]/10 px-2 py-0.5 text-xs font-medium text-[#dd5b00]"
                                            >{{ LABEL_KATEGORI[jenis.kategori] }}</span
                                        >
                                    </td>
                                    <td class="text-center">{{ jenis.tarif_count }}</td>
                                    <td>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="jenis.aktif ? 'bg-[#1aae39]/10 text-[#137a2a]' : 'bg-[#f6f5f4] text-[#615d59]'"
                                        >
                                            {{ jenis.aktif ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="route('admin.jenis-biaya.edit', jenis.id)" title="Edit" aria-label="Edit jenis biaya"
                                                    ><Pencil
                                                /></Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus jenis biaya"
                                                @click="hapus(jenis)"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.jenisBiaya.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada jenis biaya. Tambahkan dulu, mis. SPP Tetap dan SPP per SKS.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
