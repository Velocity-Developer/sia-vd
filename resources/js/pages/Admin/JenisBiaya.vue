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

const caraHitung = (jenis: { cara_hitung: string; kategori: string }) =>
    jenis.kategori === 'remidi'
        ? jenis.cara_hitung === 'per_sks'
            ? 'Per SKS mata kuliah'
            : 'Tetap per mata kuliah'
        : jenis.cara_hitung === 'per_sks'
          ? 'Per SKS'
          : 'Tetap per semester';

const hapus = (jenis: Jenis) => {
    if (!confirm(`Hapus jenis biaya "${jenis.nama}"? Tagihan yang sudah terbit tidak berubah.`)) return;

    router.delete(route('admin.jenis-biaya.destroy', jenis.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Jenis Biaya" />
    <AppLayout :breadcrumbs="[{ title: 'Jenis Biaya', href: route('admin.jenis-biaya.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Jenis Biaya</h1>
                        <p class="text-sm text-[#615d59]">Komponen biaya kuliah beserta tarifnya per program studi dan angkatan.</p>
                    </div>
                    <Link :href="route('admin.jenis-biaya.create')">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Jenis Biaya</Button>
                    </Link>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[780px] text-left lg:min-w-0">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kode</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Cara Hitung</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tarif</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="jenis in props.jenisBiaya" :key="jenis.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] font-medium text-black">{{ jenis.kode }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ jenis.nama }}</span>
                                        <span v-if="jenis.keterangan" class="block text-xs text-[#a39e98]">{{ jenis.keterangan }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        {{ caraHitung(jenis) }}
                                        <span
                                            v-if="jenis.kategori === 'remidi'"
                                            class="ml-1 rounded-full bg-[#fff4e5] px-2 py-0.5 text-xs font-medium text-[#dd5b00]"
                                            >Remidi</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-center text-[15px] text-[#31302e]">{{ jenis.tarif_count }}</td>
                                    <td class="px-4 py-3 text-[15px]">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="jenis.aktif ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f6f5f4] text-[#a39e98]'"
                                        >
                                            {{ jenis.aktif ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Link :href="route('admin.jenis-biaya.edit', jenis.id)" title="Edit" aria-label="Edit jenis biaya">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99]"
                                                >
                                                    <Pencil class="size-4" />
                                                </Button>
                                            </Link>
                                            <button type="button" title="Hapus" aria-label="Hapus jenis biaya" @click="hapus(jenis)">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00]"
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.jenisBiaya.length">
                                    <td colspan="6" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Belum ada jenis biaya. Tambahkan dulu, mis. SPP Tetap dan SPP per SKS.
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
