<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { BadgeCheck, Plus, Save, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    angkatan: string | null;
    jenis_kkm: string | null;
    judul: string | null;
    krs_id: number | null;
    mata_kuliah: string | null;
    nilai_angka: number | null;
    huruf: string | null;
    tervalidasi: boolean;
};

const props = defineProps<{
    mahasiswa: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { prodi_id: number | null; search: string };
    prodiOptions: { id: number; name: string }[];
}>();

const page = usePage<{ flash: { success?: string; error?: string }; errors: Record<string, string> }>();
const semua = 'semua';
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route('admin.nilai-kkm.index'),
        { prodi_id: prodiId.value === semua ? null : prodiId.value, search: search.value || null },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

// Isian angka per mahasiswa; diisi ulang tiap data dari server berubah (sesudah simpan).
const isian = ref<Record<number, string>>({});
watch(
    () => props.mahasiswa.data,
    (data) => (isian.value = Object.fromEntries(data.map((b) => [b.id, b.nilai_angka === null ? '' : String(b.nilai_angka)]))),
    { immediate: true },
);
const berubah = (b: Baris) => (isian.value[b.id] ?? '') !== (b.nilai_angka === null ? '' : String(b.nilai_angka));

const proses = ref<number | null>(null);
const simpan = (b: Baris) => {
    if (!b.krs_id) return;
    proses.value = b.id;
    const nilai = isian.value[b.id];
    router.put(
        route('admin.nilai-kkm.update', b.krs_id),
        { nilai: nilai === '' ? null : nilai },
        { preserveScroll: true, onFinish: () => (proses.value = null) },
    );
};
const tambahKrs = (b: Baris) => {
    proses.value = b.id;
    router.post(route('admin.nilai-kkm.tambah-krs', b.id), {}, { preserveScroll: true, onFinish: () => (proses.value = null) });
};
</script>

<template>
    <Head title="Nilai KKM" />
    <AppLayout :breadcrumbs="[{ title: 'Nilai KKM', href: route('admin.nilai-kkm.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Nilai KKM</h1>
                        <p class="deskripsi-halaman">
                            Nilai KKM/PKL/KKN mahasiswa yang pengajuan Kuliah Kerja Mahasiswa-nya disetujui. Isi angka 0–100; hurufnya dihitung dari
                            Bobot Nilai prodi dan masuk ke KHS serta transkrip.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>
                <div v-if="page.props.errors?.nilai" class="alert-gagal" role="alert">{{ page.props.errors.nilai }}</div>

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
                        <span class="font-medium text-black dark:text-foreground">{{ props.mahasiswa.total }}</span> mahasiswa
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1040px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Angkatan</th>
                                    <th>Kegiatan</th>
                                    <th>Mata Kuliah KKM</th>
                                    <th class="w-32">Nilai</th>
                                    <th class="text-center">Huruf</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.mahasiswa.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.mahasiswa.from ?? 1) + index }}</td>
                                    <td class="whitespace-nowrap">{{ b.nim ?? '-' }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td>{{ b.angkatan ?? '-' }}</td>
                                    <td class="max-w-[280px]">
                                        <span v-if="b.jenis_kkm" class="text-xs font-semibold text-[#0075de]">{{ b.jenis_kkm }}</span>
                                        <span class="block text-sm">{{ b.judul ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span v-if="b.mata_kuliah">{{ b.mata_kuliah }}</span>
                                        <span v-else class="text-xs text-[#dd5b00]">Belum ada di KRS</span>
                                    </td>
                                    <td>
                                        <Input
                                            v-if="b.krs_id"
                                            v-model="isian[b.id]"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            :aria-label="`Nilai KKM ${b.nama}`"
                                            :disabled="b.tervalidasi"
                                            class="tabular-nums"
                                        />
                                        <span v-else class="text-[#a39e98]">-</span>
                                    </td>
                                    <td class="text-center font-semibold">{{ b.huruf ?? '-' }}</td>
                                    <td class="kolom-aksi">
                                        <span
                                            v-if="b.tervalidasi"
                                            class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#ecfdf3] px-2 py-0.5 text-xs font-medium text-[#067647]"
                                            title="Batalkan validasinya dulu di Penilaian → Validasi Nilai"
                                            ><BadgeCheck class="size-3.5" /> Tervalidasi</span
                                        >
                                        <Button v-else-if="b.krs_id" size="sm" :disabled="!berubah(b) || proses !== null" @click="simpan(b)">
                                            <Save class="size-4" /> Simpan
                                        </Button>
                                        <Button v-else size="sm" variant="outline" :disabled="proses !== null" @click="tambahKrs(b)">
                                            <Plus class="size-4" /> Masukkan ke KRS
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.mahasiswa.data.length" class="baris-kosong">
                                    <td colspan="9" class="tabel-kosong">Belum ada pengajuan KKM yang disetujui.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.mahasiswa.links" :total="props.mahasiswa.total" />

                <p class="teks-bantu">
                    Kosongkan nilai lalu Simpan untuk menghapusnya. Mahasiswa yang belum punya KRS KKM (misalnya kelasnya belum dibuat saat pengajuan
                    disetujui) dimasukkan lewat tombol Masukkan ke KRS.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
