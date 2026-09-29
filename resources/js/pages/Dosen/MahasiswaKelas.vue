<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Krs = {
    id: number;
    mahasiswa?: { nim?: string; user?: { name?: string; email?: string }; prodi?: { nama_prodi?: string } };
    kelas_kuliah?: { kode_kelas?: string; mata_kuliah?: { kode_matkul?: string; nama_matkul?: string } };
};
type Pagination = { data: Krs[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
const props = defineProps<{ krs: Pagination; search?: string }>();
const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('dosen.mahasiswa-kelas'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));
</script>

<template>
    <Head title="Mahasiswa Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Mahasiswa Kelas', href: route('dosen.mahasiswa-kelas') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Mahasiswa Kelas</h1>
                        <p class="deskripsi-halaman">Daftar mahasiswa yang mengambil kelas Anda.</p>
                    </div>
                </div>
                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama, NIM, kelas, atau mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.krs.total }}</span> mahasiswa
                    </p>
                </div>
                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Program Studi</th>
                                    <th>Kelas</th>
                                    <th>Mata Kuliah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.krs.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.krs.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ item.mahasiswa?.user?.name ?? '-' }}</span>
                                        <span class="teks-bantu">{{ item.mahasiswa?.nim ?? '-' }} | {{ item.mahasiswa?.user?.email ?? '-' }}</span>
                                    </td>
                                    <td>{{ item.mahasiswa?.prodi?.nama_prodi ?? '-' }}</td>
                                    <td>{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</td>
                                    <td>
                                        {{ item.kelas_kuliah?.mata_kuliah?.kode_matkul ?? '-' }} —
                                        {{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '' }}
                                    </td>
                                </tr>
                                <tr v-if="!props.krs.data.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">Belum ada mahasiswa terdaftar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="props.krs.links" :total="props.krs.total" />
            </div>
        </div>
    </AppLayout>
</template>
