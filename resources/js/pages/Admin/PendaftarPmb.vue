<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { labelStatusPmb } from '@/lib/pmb';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Item = {
    id: number;
    nomor_pendaftaran: string;
    nama: string;
    hp: string;
    nilai: number | null;
    status_pendaftaran: string | null;
    created_at: string;
    periode: { id: number; kode: string };
    program_studi: { id: number; nama_prodi: string; jenjang: string | null };
};
type Pagination = { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    pendaftar: Pagination;
    periode: { id: number; kode: string; tahun_angkatan: number }[];
    filter: { search: string; periode: number | null; status: string };
}>();

const filter = reactive({ search: props.filter.search, periode: props.filter.periode ?? '', status: props.filter.status });
watch(filter, (nilai) => router.get(route('admin.pendaftar-pmb.index'), nilai, { preserveState: true, preserveScroll: true, replace: true }));

const formatDate = (value: string) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
</script>

<template>
    <Head title="Data Pendaftar PMB" />
    <AppLayout :breadcrumbs="[{ title: 'Data Pendaftar PMB', href: route('admin.pendaftar-pmb.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Data Pendaftar PMB</h1>
                        <p class="deskripsi-halaman">Calon mahasiswa baru yang mendaftar lewat formulir PMB, beserta nilai dan status seleksinya.</p>
                    </div>
                    <Button as-child variant="outline"><a :href="route('pmb.daftar')" target="_blank" rel="noopener">Buka Formulir</a></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="filter.search" placeholder="Cari nama, nomor pendaftaran, atau NIK" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="filter.periode" label="Periode">
                        <option value="">Semua periode</option>
                        <option v-for="p in props.periode" :key="p.id" :value="p.id">{{ p.kode }} ({{ p.tahun_angkatan }})</option>
                    </SelectFilter>
                    <SelectFilter v-model="filter.status" label="Status">
                        <option value="">Semua status</option>
                        <option value="menunggu">Menunggu</option>
                        <option value="lulus">Lulus</option>
                        <option value="ditolak">Ditolak</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.pendaftar.total }}</span> pendaftar
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>No. Pendaftaran</th>
                                    <th>Nama</th>
                                    <th>Program Studi</th>
                                    <th>No. HP</th>
                                    <th>Tgl Daftar</th>
                                    <th>Nilai</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.pendaftar.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.pendaftar.from ?? 1) + index }}</td>
                                    <td class="whitespace-nowrap font-medium text-black">{{ item.nomor_pendaftaran }}</td>
                                    <td>{{ item.nama }}</td>
                                    <td>{{ [item.program_studi.jenjang, item.program_studi.nama_prodi].filter(Boolean).join(' ') }}</td>
                                    <td class="whitespace-nowrap">{{ item.hp }}</td>
                                    <td class="whitespace-nowrap">{{ formatDate(item.created_at) }}</td>
                                    <td>{{ item.nilai ?? '—' }}</td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="labelStatusPmb(item.status_pendaftaran).kelas"
                                            >{{ labelStatusPmb(item.status_pendaftaran).label }}</span
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.pendaftar-pmb.show', item.id)" title="Detail" aria-label="Detail"
                                                    ><Eye /></Link
                                            ></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pendaftar.data.length" class="baris-kosong">
                                    <td colspan="9" class="tabel-kosong">
                                        Pendaftar akan tampil di sini setelah calon mahasiswa mengisi formulir PMB.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pendaftar.links" :total="props.pendaftar.total" />
            </div>
        </div>
    </AppLayout>
</template>
