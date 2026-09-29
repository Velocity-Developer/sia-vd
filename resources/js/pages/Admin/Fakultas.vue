<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Fakultas = {
    id: number;
    kode_fakultas: string;
    nama_fakultas: string;
    dekan?: { user?: { name?: string } } | null;
    tanggal_berdiri?: string | null;
    no_telp?: string | null;
    email?: string | null;
};
type Pagination = { data: Fakultas[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{ fakultas: Pagination; search?: string }>();

const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.fakultas.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));

const confirmOpen = ref(false);
const pendingItem = ref<Fakultas | null>(null);

const remove = (item: Fakultas) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.fakultas.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Fakultas" />
    <AppLayout :breadcrumbs="[{ title: 'Fakultas', href: route('admin.fakultas.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Fakultas</h1>
                        <p class="deskripsi-halaman">Kelola data fakultas, dekan, dan informasi kontak.</p>
                    </div>
                    <Button as-child>
                        <Link :href="route('admin.fakultas.create')">Tambah Fakultas</Link>
                    </Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau nama fakultas" aria-label="Cari" class="pl-9" />
                    </div>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.fakultas.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[780px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode Fakultas</th>
                                    <th>Nama Fakultas</th>
                                    <th>Dekan</th>
                                    <th>Email</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.fakultas.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.fakultas.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode_fakultas }}</td>
                                    <td>{{ item.nama_fakultas }}</td>
                                    <td>{{ item.dekan?.user?.name ?? '-' }}</td>
                                    <td class="max-w-[220px] truncate" :title="item.email ?? ''">{{ item.email ?? '-' }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                <Link :href="route('admin.fakultas.show', item.id)" title="Detail" aria-label="Detail"><Eye /></Link>
                                            </Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="route('admin.fakultas.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link>
                                            </Button>
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
                                <tr v-if="!props.fakultas.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada data fakultas. Tambahkan fakultas baru untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.fakultas.links" :total="props.fakultas.total" />

                <AlertModal
                    :open="confirmOpen"
                    description="Anda yakin ingin menghapus data ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="confirmDelete"
                    @cancel="confirmOpen = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
