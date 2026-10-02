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

type Matkul = { id: number; kode_matkul: string; nama_matkul: string; semester: number };
type Item = {
    id: number;
    mata_kuliah: (Matkul & { prodi?: { nama_prodi: string; jenjang: string } | null }) | null;
    prasyarat: Matkul | null;
};
type Pagination = { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    prasyarat: Pagination;
    search?: string;
    programStudis: { id: number; nama_prodi: string; jenjang: string }[];
    programStudiId: number | null;
}>();

const search = ref(props.search ?? '');
const programStudiId = ref<number | string>(props.programStudiId ?? 'all');
const applyFilters = () =>
    router.get(
        route('admin.prasyarat.index'),
        { search: search.value, program_studi_id: programStudiId.value },
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
    router.delete(route('admin.prasyarat.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Mata Kuliah Prasyarat" />
    <AppLayout :breadcrumbs="[{ title: 'Mata Kuliah Prasyarat', href: route('admin.prasyarat.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Mata Kuliah Prasyarat</h1>
                        <p class="deskripsi-halaman">Mata kuliah hanya bisa diambil di KRS bila semua prasyaratnya sudah lulus.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.prasyarat.create')">Tambah Prasyarat</Link></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau nama mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="programStudiId" label="Filter program studi" @change="applyFilters">
                        <option value="all">Semua Program Studi</option>
                        <option v-for="prodi in props.programStudis" :key="prodi.id" :value="prodi.id">
                            {{ prodi.nama_prodi }} ({{ prodi.jenjang }})
                        </option>
                    </SelectFilter>

                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.prasyarat.total }}</span> data<span v-if="props.search">
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
                        <table class="tabel min-w-[880px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Mata Kuliah Prasyarat</th>
                                    <th>Program Studi</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.prasyarat.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.prasyarat.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ item.mata_kuliah?.nama_matkul }}</span>
                                        <span class="teks-bantu block"
                                            >{{ item.mata_kuliah?.kode_matkul }} · smt {{ item.mata_kuliah?.semester }}</span
                                        >
                                    </td>
                                    <td>
                                        <span class="block">{{ item.prasyarat?.nama_matkul }}</span>
                                        <span class="teks-bantu block">{{ item.prasyarat?.kode_matkul }} · smt {{ item.prasyarat?.semester }}</span>
                                    </td>
                                    <td>
                                        {{
                                            item.mata_kuliah?.prodi ? `${item.mata_kuliah.prodi.nama_prodi} (${item.mata_kuliah.prodi.jenjang})` : '-'
                                        }}
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.prasyarat.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link
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
                                <tr v-if="!props.prasyarat.data.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">
                                        Prasyarat mata kuliah akan tampil di sini. Tambahkan prasyarat untuk memulai.
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

                <Pagination :links="props.prasyarat.links" :total="props.prasyarat.total" />
            </div>
        </div>
    </AppLayout>
</template>
