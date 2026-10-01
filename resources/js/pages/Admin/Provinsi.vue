<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Item = { id: number; kode: string; nama: string; kotas_count: number };
type Pagination = { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{ provinsis: Pagination; search?: string }>();

const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.provinsi.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));

const confirmOpen = ref(false);
const pendingItem = ref<Item | null>(null);

const remove = (item: Item) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.provinsi.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Provinsi" />
    <AppLayout :breadcrumbs="[{ title: 'Provinsi', href: route('admin.provinsi.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Provinsi</h1>
                        <p class="deskripsi-halaman">Kelola daftar provinsi beserta kodenya.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.provinsi.create')">Tambah Provinsi</Link></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau nama provinsi" aria-label="Cari" class="pl-9" />
                    </div>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.provinsis.total }}</span> data<span v-if="props.search">
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
                        <table class="tabel min-w-[640px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode</th>
                                    <th>Nama Provinsi</th>
                                    <th>Jumlah Kota/Kabupaten</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.provinsis.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.provinsis.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode }}</td>
                                    <td>{{ item.nama }}</td>
                                    <td class="text-center">{{ item.kotas_count }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.provinsi.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link
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
                                <tr v-if="!props.provinsis.data.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">
                                        Data provinsi akan tampil di sini. Tambahkan provinsi baru untuk memulai.
                                    </td>
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

                <Pagination :links="props.provinsis.links" :total="props.provinsis.total" />
            </div>
        </div>
    </AppLayout>
</template>
