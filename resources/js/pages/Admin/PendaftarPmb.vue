<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SalinCalonMaba from '@/components/SalinCalonMaba.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { labelStatusPmb } from '@/lib/pmb';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, GraduationCap, Search } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string }; errors: Record<string, string> }>();

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
    mahasiswa_url: string | null;
};
type Pagination = { data: Item[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    pendaftar: Pagination;
    periode: { id: number; kode: string; tahun_angkatan: number }[];
    filter: { search: string; periode: number | null; status: string };
    bolehSalin: boolean;
}>();

const filter = reactive({ search: props.filter.search, periode: props.filter.periode ?? '', status: props.filter.status });
watch(filter, (nilai) => router.get(route('admin.pendaftar-pmb.index'), nilai, { preserveState: true, preserveScroll: true, replace: true }));

// Nilai & status diubah langsung di tabel; tiap perubahan disimpan untuk baris itu saja.
const isian = reactive<Record<number, { nilai: string; status: string }>>({});
watch(
    () => props.pendaftar.data,
    (data) => {
        for (const item of data) isian[item.id] = { nilai: item.nilai === null ? '' : String(item.nilai), status: item.status_pendaftaran ?? '' };
    },
    { immediate: true },
);
const barisDisimpan = ref<number | null>(null);
const barisGalat = ref<number | null>(null);
const simpanBaris = (item: Item) => {
    const isi = isian[item.id];
    if (isi.nilai === (item.nilai === null ? '' : String(item.nilai)) && isi.status === (item.status_pendaftaran ?? '')) return;
    router.put(
        route('admin.pendaftar-pmb.update', item.id),
        { nilai: isi.nilai === '' ? null : isi.nilai, status_pendaftaran: isi.status || null },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (barisDisimpan.value = item.id),
            onSuccess: () => (barisGalat.value = null),
            onError: () => (barisGalat.value = item.id),
            onFinish: () => (barisDisimpan.value = null),
        },
    );
};

const formatDate = (value: string) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
</script>

<template>
    <Head title="Calon Maba" />
    <AppLayout :breadcrumbs="[{ title: 'Calon Maba', href: route('admin.pendaftar-pmb.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Calon Maba</h1>
                        <p class="deskripsi-halaman">
                            Calon mahasiswa baru dari formulir PMB. Ubah nilai dan status langsung di tabel; yang Lulus bisa disalin ke Data
                            Mahasiswa.
                        </p>
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
                        <span class="font-medium text-black">{{ props.pendaftar.total }}</span> calon maba
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
                                    <td class="w-[110px]">
                                        <Input
                                            v-model="isian[item.id].nilai"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="h-8"
                                            :aria-label="`Nilai ${item.nama}`"
                                            :disabled="barisDisimpan === item.id"
                                            @change="simpanBaris(item)"
                                            @keydown.enter.prevent="simpanBaris(item)"
                                        />
                                    </td>
                                    <td class="w-[150px]">
                                        <select
                                            v-model="isian[item.id].status"
                                            class="isian isian-pilih h-8 py-0"
                                            :class="labelStatusPmb(isian[item.id].status || null).kelas"
                                            :aria-label="`Status ${item.nama}`"
                                            :disabled="barisDisimpan === item.id || !!item.mahasiswa_url"
                                            :title="item.mahasiswa_url ? 'Sudah disalin ke Data Mahasiswa' : undefined"
                                            @change="simpanBaris(item)"
                                        >
                                            <option value="">Menunggu</option>
                                            <option value="lulus">Lulus</option>
                                            <option value="ditolak">Ditolak</option>
                                        </select>
                                        <p v-if="barisGalat === item.id" class="mt-1 text-xs text-[#b34700]">
                                            {{ page.props.errors.nilai ?? page.props.errors.status_pendaftaran }}
                                        </p>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button v-if="item.mahasiswa_url" as-child variant="outline" size="icon-sm" class="text-[#1aae39]"
                                                ><Link :href="item.mahasiswa_url" title="Sudah di Data Mahasiswa" aria-label="Lihat di Data Mahasiswa"
                                                    ><GraduationCap /></Link
                                            ></Button>
                                            <SalinCalonMaba
                                                v-else-if="props.bolehSalin && item.status_pendaftaran === 'lulus'"
                                                :id="item.id"
                                                :nama="item.nama"
                                                ringkas
                                            />
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.pendaftar-pmb.show', item.id)" title="Detail" aria-label="Detail"
                                                    ><Eye /></Link
                                            ></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pendaftar.data.length" class="baris-kosong">
                                    <td colspan="9" class="tabel-kosong">Calon maba akan tampil di sini setelah mengisi formulir PMB.</td>
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
