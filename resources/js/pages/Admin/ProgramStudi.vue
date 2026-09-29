<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type ProgramStudi = {
    id: number;
    kode_prodi: string;
    nama_prodi: string;
    jenjang: string;
    status_akreditasi: string;
    fakultas?: { nama_fakultas?: string } | null;
    ketuaProgramStudi?: { user?: { name?: string } } | null;
    ketua_program_studi?: { user?: { name?: string } } | null;
};

const kaprodiName = (item: ProgramStudi): string => item.ketuaProgramStudi?.user?.name ?? (item as any).ketua_program_studi?.user?.name ?? '-';
type Pagination = { data: ProgramStudi[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    programStudis: Pagination;
    search?: string;
    fakultas: { id: number; nama_fakultas: string }[];
    fakultasId: number | null;
}>();

const search = ref(props.search ?? '');
const fakultasId = ref<number | string>(props.fakultasId ?? 'all');
const applyFilters = () =>
    router.get(
        route('admin.program-studi.index'),
        { search: search.value, fakultas_id: fakultasId.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, applyFilters);

const confirmOpen = ref(false);
const pendingItem = ref<ProgramStudi | null>(null);

const remove = (item: ProgramStudi) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.program-studi.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Program Studi" />
    <AppLayout :breadcrumbs="[{ title: 'Program Studi', href: route('admin.program-studi.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Program Studi</h1>
                        <p class="deskripsi-halaman">Kelola program studi per fakultas, jenjang, dan kaprodi.</p>
                    </div>
                    <Button as-child>
                        <Link :href="route('admin.program-studi.create')">Tambah Program Studi</Link>
                    </Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau nama program studi" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="fakultasId" label="Filter fakultas" @change="applyFilters">
                        <option value="all">Semua Fakultas</option>
                        <option v-for="item in props.fakultas" :key="item.id" :value="item.id">{{ item.nama_fakultas }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.programStudis.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1040px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode</th>
                                    <th>Nama Program Studi</th>
                                    <th>Fakultas</th>
                                    <th>Jenjang</th>
                                    <th>Akreditasi</th>
                                    <th>Kaprodi</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.programStudis.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.programStudis.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode_prodi }}</td>
                                    <td>{{ item.nama_prodi }}</td>
                                    <td>{{ item.fakultas?.nama_fakultas ?? '-' }}</td>
                                    <td>{{ item.jenjang }}</td>
                                    <td>{{ item.status_akreditasi }}</td>
                                    <td>{{ kaprodiName(item) }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                <Link :href="route('admin.program-studi.show', item.id)" title="Detail" aria-label="Detail"
                                                    ><Eye
                                                /></Link>
                                            </Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="route('admin.program-studi.edit', item.id)" title="Edit" aria-label="Edit"
                                                    ><Pencil
                                                /></Link>
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
                                <tr v-if="!props.programStudis.data.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Belum ada data program studi. Tambahkan prodi baru untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.programStudis.links" :total="props.programStudis.total" />

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
