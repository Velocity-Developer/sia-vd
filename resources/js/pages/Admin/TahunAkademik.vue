<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const formatDate = (value: string) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value));

type Item = {
    id: number;
    tahun: string;
    semester: string;
    tanggal_mulai: string;
    tanggal_akhir: string;
    tanggal_krs_awal: string;
    tanggal_krs_akhir: string;
    status: boolean;
};
const page = usePage<{ flash?: { success?: string; error?: string } }>();
const props = defineProps<{ tahunAkademiks: { data: Item[]; total: number; from: number | null; links: any[] }; search?: string }>();
const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.tahun-akademik.index'), { search: value }, { preserveState: true, replace: true }));
const confirmOpen = ref(false);
const pendingItem = ref<Item | null>(null);
const remove = (item: Item) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};
const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.tahun-akademik.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>
<template>
    <Head title="Tahun Akademik" />
    <AppLayout :breadcrumbs="[{ title: 'Tahun Akademik', href: route('admin.tahun-akademik.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Tahun Akademik</h1>
                        <p class="deskripsi-halaman">Kelola periode akademik perkuliahan.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.tahun-akademik.create')">Tambah Tahun Akademik</Link></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari tahun atau semester" aria-label="Cari" class="pl-9" />
                    </div>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.tahunAkademiks.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[920px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Tahun</th>
                                    <th>Semester</th>
                                    <th>Periode Kuliah</th>
                                    <th>Periode KRS</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.tahunAkademiks.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.tahunAkademiks.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.tahun }}</td>
                                    <td>{{ item.semester }}</td>
                                    <td>{{ formatDate(item.tanggal_mulai) }} — {{ formatDate(item.tanggal_akhir) }}</td>
                                    <td>{{ formatDate(item.tanggal_krs_awal) }} — {{ formatDate(item.tanggal_krs_akhir) }}</td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="item.status ? 'bg-[#1aae39]/10 text-[#137a2a]' : 'bg-[#f6f5f4] text-[#615d59]'"
                                            >{{ item.status ? 'Aktif' : 'Tidak Aktif' }}</span
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="route('admin.tahun-akademik.edit', item.id)" title="Edit" aria-label="Edit"
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
                                <tr v-if="!props.tahunAkademiks.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada data tahun akademik.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.tahunAkademiks.links" :total="props.tahunAkademiks.total" />

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
