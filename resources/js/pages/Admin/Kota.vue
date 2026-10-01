<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Item = { id: number; kode: string; nama: string; provinsi: { id: number; kode: string; nama: string } | null };
type Pagination = { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    kotas: Pagination;
    search?: string;
    provinsis: { id: number; kode: string; nama: string }[];
    provinsiId: number | null;
}>();

const search = ref(props.search ?? '');
const provinsiId = ref<number | string>(props.provinsiId ?? 'all');
const applyFilters = () =>
    router.get(
        route('admin.kota.index'),
        { search: search.value, provinsi_id: provinsiId.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, applyFilters);

const confirmOpen = ref(false);
const pendingItem = ref<Item | null>(null);

const remove = (item: Item) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.kota.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Kota/Kabupaten" />
    <AppLayout :breadcrumbs="[{ title: 'Kota/Kabupaten', href: route('admin.kota.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Kota/Kabupaten</h1>
                        <p class="deskripsi-halaman">Kelola daftar kota dan kabupaten per provinsi beserta kodenya.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.kota.create')">Tambah Kota/Kabupaten</Link></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau nama kota/kabupaten" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="provinsiId" label="Filter provinsi" @change="applyFilters">
                        <option value="all">Semua Provinsi</option>
                        <option v-for="provinsi in props.provinsis" :key="provinsi.id" :value="provinsi.id">{{ provinsi.nama }}</option>
                    </SelectFilter>

                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.kotas.total }}</span> data<span v-if="props.search">
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
                        <table class="tabel min-w-[680px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode</th>
                                    <th>Nama Kota/Kabupaten</th>
                                    <th>Provinsi</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.kotas.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.kotas.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode }}</td>
                                    <td>{{ item.nama }}</td>
                                    <td>{{ item.provinsi?.nama ?? '-' }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.kota.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link
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
                                <tr v-if="!props.kotas.data.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">
                                        Data kota/kabupaten akan tampil di sini. Tambahkan kota/kabupaten baru untuk memulai.
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

                <Pagination :links="props.kotas.links" :total="props.kotas.total" />
            </div>
        </div>
    </AppLayout>
</template>
