<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { labelJenisPenilaian } from '@/lib/penilaian';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, FileSpreadsheet, Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type MataKuliah = {
    id: number;
    kode_matkul: string;
    nama_matkul: string;
    sks: number;
    semester: number;
    jenis: string;
    tugas_akhir: boolean;
    jenis_penilaian: string;
    prodi?: { nama_prodi?: string; fakultas?: { nama_fakultas?: string } | null } | null;
};
type Pagination = { data: MataKuliah[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    mataKuliahs: Pagination;
    search?: string;
    programStudis: { id: number; nama_prodi: string; jenjang: string }[];
    programStudiId: number | null;
}>();

const search = ref(props.search ?? '');
const programStudiId = ref<number | string>(props.programStudiId ?? 'all');
const applyFilters = () =>
    router.get(
        route('admin.mata-kuliah.index'),
        { search: search.value, program_studi_id: programStudiId.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, applyFilters);

const confirmOpen = ref(false);
const pendingItem = ref<MataKuliah | null>(null);

const remove = (item: MataKuliah) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.mata-kuliah.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};

const jenisBadge = (jenis: string) => (jenis === 'Wajib' ? 'bg-[#0075de]/10 text-[#0075de]' : 'bg-[#f59e0b]/10 text-[#92400e]');
</script>

<template>
    <Head title="Mata Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Mata Kuliah', href: route('admin.mata-kuliah.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Mata Kuliah</h1>
                        <p class="deskripsi-halaman">Kelola mata kuliah per program studi, SKS, dan semester.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <Link :href="route('admin.impor.index', 'mata-kuliah')"><FileSpreadsheet /> Impor Excel</Link>
                        </Button>
                        <Button as-child><Link :href="route('admin.mata-kuliah.create')">Tambah Mata Kuliah</Link></Button>
                    </div>
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
                        <span class="font-medium text-black">{{ props.mataKuliahs.total }}</span> data<span v-if="props.search">
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
                        <table class="tabel min-w-[1040px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode</th>
                                    <th>Nama Mata Kuliah</th>
                                    <th>SKS</th>
                                    <th>Semester</th>
                                    <th>Jenis</th>
                                    <th>Program Studi</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.mataKuliahs.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.mataKuliahs.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode_matkul }}</td>
                                    <td>
                                        {{ item.nama_matkul }}
                                    </td>
                                    <td class="text-center">{{ item.sks }}</td>
                                    <td class="text-center">
                                        {{ item.semester }}
                                    </td>
                                    <td>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="jenisBadge(item.jenis)">{{
                                            item.jenis
                                        }}</span>
                                        <span
                                            v-if="item.jenis_penilaian !== 'reguler'"
                                            class="ml-1 inline-flex rounded-full bg-[#1aae39]/10 px-2.5 py-0.5 text-xs font-medium text-[#137a2a]"
                                            >{{ labelJenisPenilaian[item.jenis_penilaian] ?? item.jenis_penilaian }}</span
                                        >
                                    </td>
                                    <td>
                                        <span class="block">{{ item.prodi?.nama_prodi ?? '-' }}</span>
                                        <span class="teks-bantu block">{{ item.prodi?.fakultas?.nama_fakultas ?? '' }}</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]"
                                                ><Link :href="route('admin.mata-kuliah.show', item.id)" title="Detail" aria-label="Detail"
                                                    ><Eye /></Link
                                            ></Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.mata-kuliah.edit', item.id)" title="Edit" aria-label="Edit"
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
                                <tr v-if="!props.mataKuliahs.data.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">
                                        Data mata kuliah akan tampil di sini. Tambahkan mata kuliah baru untuk memulai.
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

                <Pagination :links="props.mataKuliahs.links" :total="props.mataKuliahs.total" />
            </div>
        </div>
    </AppLayout>
</template>
