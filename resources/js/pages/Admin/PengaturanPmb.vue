<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { rupiah } from '@/lib/tagihanRemidi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Item = {
    id: number;
    kode: string;
    tahun_angkatan: number;
    tanggal_buka: string;
    tanggal_tutup: string;
    tanggal_usm_mulai: string;
    tanggal_usm_selesai: string;
    tanggal_her: string;
    nilai_minimal: number;
    kapasitas: number;
    biaya_pendaftaran: number;
    tanggal_pembayaran_mulai: string;
    tanggal_pembayaran_selesai: string;
    is_open: boolean;
};
type Pagination = { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{ periode: Pagination; search?: string }>();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
const rentang = (awal: string, akhir: string) => `${formatDate(awal)} — ${formatDate(akhir)}`;

const search = ref(props.search ?? '');
watch(search, (value) =>
    router.get(route('admin.periode-pmb.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }),
);

const confirmOpen = ref(false);
const pendingItem = ref<Item | null>(null);

const remove = (item: Item) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.periode-pmb.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Atur Periode PMB" />
    <AppLayout :breadcrumbs="[{ title: 'Atur Periode PMB', href: route('admin.periode-pmb.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Atur Periode PMB</h1>
                        <p class="deskripsi-halaman">Kelola jadwal pendaftaran, USM, her-registrasi, dan pembayaran calon mahasiswa baru.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.periode-pmb.create')">Tambah Periode</Link></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau tahun angkatan" aria-label="Cari" class="pl-9" />
                    </div>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.periode.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1100px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode</th>
                                    <th>Angkatan</th>
                                    <th>Pendaftaran</th>
                                    <th>USM</th>
                                    <th>Her-registrasi</th>
                                    <th>Pembayaran</th>
                                    <th>Nilai Min.</th>
                                    <th>Kapasitas</th>
                                    <th>Biaya</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.periode.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.periode.from ?? 1) + index }}</td>
                                    <td class="whitespace-nowrap font-medium text-black">{{ item.kode }}</td>
                                    <td>{{ item.tahun_angkatan }}</td>
                                    <td class="whitespace-nowrap">{{ rentang(item.tanggal_buka, item.tanggal_tutup) }}</td>
                                    <td class="whitespace-nowrap">{{ rentang(item.tanggal_usm_mulai, item.tanggal_usm_selesai) }}</td>
                                    <td class="whitespace-nowrap">{{ formatDate(item.tanggal_her) }}</td>
                                    <td class="whitespace-nowrap">{{ rentang(item.tanggal_pembayaran_mulai, item.tanggal_pembayaran_selesai) }}</td>
                                    <td>{{ item.nilai_minimal }}</td>
                                    <td>{{ item.kapasitas }}</td>
                                    <td class="whitespace-nowrap">{{ rupiah(item.biaya_pendaftaran) }}</td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="item.is_open ? 'bg-[#1aae39]/10 text-[#137a2a]' : 'bg-[#f6f5f4] text-[#615d59]'"
                                            >{{ item.is_open ? 'Dibuka' : 'Ditutup' }}</span
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.periode-pmb.edit', item.id)" title="Edit" aria-label="Edit"
                                                    ><Pencil /></Link
                                            ></Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus"
                                                @click="remove(item)"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.periode.data.length" class="baris-kosong">
                                    <td colspan="12" class="tabel-kosong">Periode PMB akan tampil di sini. Tambahkan periode baru untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <AlertModal
                    :open="confirmOpen"
                    description="Anda yakin ingin menghapus data ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="confirmDelete"
                    @cancel="confirmOpen = false"
                />

                <Pagination :links="props.periode.links" :total="props.periode.total" />
            </div>
        </div>
    </AppLayout>
</template>
