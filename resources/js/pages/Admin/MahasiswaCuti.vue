<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    user_id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    angkatan: string | null;
    tahun_akademik: string | null;
    alasan: string | null;
    disetujui_at: string | null;
    jumlah_cuti: number;
    aktif_kembali_menunggu: boolean;
};

const props = defineProps<{
    mahasiswa: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { prodi_id: number | null; search: string };
    prodiOptions: { id: number; name: string }[];
}>();

const { can } = usePermissions();
const semua = 'semua';
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route('admin.mahasiswa-cuti.index'),
        { prodi_id: prodiId.value === semua ? null : prodiId.value, search: search.value || null },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});
</script>

<template>
    <Head title="Mahasiswa Cuti" />
    <AppLayout :breadcrumbs="[{ title: 'Mahasiswa Cuti', href: route('admin.mahasiswa-cuti.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Mahasiswa Cuti</h1>
                        <p class="deskripsi-halaman">
                            Mahasiswa yang saat ini berstatus Cuti. Status berubah lewat
                            <Link :href="route('admin.pengajuan-cuti.index')" class="font-medium text-[#0075de] hover:underline">Pengajuan Cuti</Link>
                            (cuti dan aktif kembali).
                        </p>
                    </div>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="kirim()">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.mahasiswa.total }}</span> mahasiswa cuti
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Angkatan</th>
                                    <th>Semester Cuti</th>
                                    <th>Alasan</th>
                                    <th class="text-center">Jumlah Cuti</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.mahasiswa.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.mahasiswa.from ?? 1) + index }}</td>
                                    <td class="whitespace-nowrap">{{ b.nim ?? '-' }}</td>
                                    <td>
                                        <Link
                                            v-if="can('admin.users.mahasiswa')"
                                            :href="route('admin.users.mahasiswa.show', b.user_id)"
                                            class="block font-medium text-black hover:underline dark:text-foreground"
                                            >{{ b.nama }}</Link
                                        >
                                        <span v-else class="block font-medium text-black dark:text-foreground">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td>{{ b.angkatan ?? '-' }}</td>
                                    <td class="whitespace-nowrap">
                                        <span class="block">{{ b.tahun_akademik ?? '-' }}</span>
                                        <span v-if="b.disetujui_at" class="block text-xs text-[#a39e98]"
                                            >Disetujui {{ formatTanggal(b.disetujui_at, false) }}</span
                                        >
                                    </td>
                                    <td class="max-w-[320px] whitespace-pre-line text-sm">{{ b.alasan ?? '-' }}</td>
                                    <td class="text-center tabular-nums">{{ b.jumlah_cuti }}</td>
                                    <td>
                                        <span
                                            v-if="b.aktif_kembali_menunggu"
                                            class="whitespace-nowrap rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-medium text-[#0075de]"
                                            >Mengajukan aktif kembali</span
                                        >
                                        <span v-else-if="!b.tahun_akademik" class="text-xs text-[#a39e98]">Diubah langsung di data mahasiswa</span>
                                        <span v-else class="text-[#a39e98]">-</span>
                                    </td>
                                </tr>
                                <tr v-if="!props.mahasiswa.data.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Tidak ada mahasiswa yang berstatus Cuti.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.mahasiswa.links" :total="props.mahasiswa.total" />
            </div>
        </div>
    </AppLayout>
</template>
