<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_KRS, type StatusKrs } from '@/lib/krs';
import { Head, router } from '@inertiajs/vue3';
import { Download, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type BarisKelas = {
    id: number;
    kode_matkul: string | null;
    nama_matkul: string | null;
    prodi: string | null;
    semester: number | null;
    sks: number;
    kode_kelas: string;
    dosen: string | null;
    kapasitas: number;
    peserta: number;
    disetujui: number;
};
type BarisMahasiswa = {
    id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    angkatan: string | null;
    mk: number;
    sks: number;
    status: StatusKrs | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    baris: (BarisKelas | BarisMahasiswa)[];
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; tampilan: 'kelas' | 'mahasiswa'; search: string };
    ringkasan: { mahasiswa: number; disetujui: number; diajukan: number; perlu_revisi: number; belum_disimpan: number };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const search = ref(props.filter.search);
const parameter = (ubah: Record<string, unknown> = {}) => ({
    tahun_akademik_id: tahunAkademikId.value,
    prodi_id: prodiId.value === semua ? null : prodiId.value,
    tampilan: props.filter.tampilan,
    search: search.value || null,
    ...ubah,
});
const kirim = (ubah: Record<string, unknown> = {}) =>
    router.get(route('admin.rekap-krs.index'), parameter(ubah), { preserveState: true, preserveScroll: true, replace: true });
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});
const pilihTampilan = (tampilan: string) => {
    search.value = '';
    kirim({ tampilan, search: null });
};
const urlCsv = computed(() => route('admin.rekap-krs.index', { ...parameter(), format: 'csv' }));

const perKelas = computed(() => (props.filter.tampilan === 'kelas' ? (props.baris as BarisKelas[]) : []));
const perMahasiswa = computed(() => (props.filter.tampilan === 'mahasiswa' ? (props.baris as BarisMahasiswa[]) : []));
const totalPeserta = computed(() => perKelas.value.reduce((n, b) => n + b.peserta, 0));

const kartuRingkasan = computed(() => [
    { label: 'Mahasiswa ber-KRS', nilai: props.ringkasan.mahasiswa },
    { label: 'Disetujui', nilai: props.ringkasan.disetujui },
    { label: 'Menunggu verifikasi', nilai: props.ringkasan.diajukan },
    { label: 'Perlu revisi', nilai: props.ringkasan.perlu_revisi },
    { label: 'Belum disimpan', nilai: props.ringkasan.belum_disimpan },
]);
const tabTampilan = [
    { nilai: 'kelas', label: 'Per Kelas' },
    { nilai: 'mahasiswa', label: 'Per Mahasiswa' },
];
</script>

<template>
    <Head title="Rekap KRS" />
    <AppLayout :breadcrumbs="[{ title: 'Rekap KRS', href: route('admin.rekap-krs.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Rekap KRS</h1>
                        <p class="deskripsi-halaman">
                            Jumlah peserta KRS per kelas, serta SKS dan status KRS per mahasiswa dalam satu tahun akademik.
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="urlCsv"><Download class="size-4" /> Unduh CSV</a>
                    </Button>
                </div>

                <section class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div v-for="k in kartuRingkasan" :key="k.label" class="kartu p-4">
                        <p class="teks-bantu">{{ k.label }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ k.nilai }}</p>
                    </div>
                </section>

                <nav class="flex gap-1 overflow-x-auto border-b border-[#e6e6e6] dark:border-border" aria-label="Tampilan rekap">
                    <button
                        v-for="tab in tabTampilan"
                        :key="tab.nilai"
                        type="button"
                        class="-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium"
                        :class="
                            tab.nilai === props.filter.tampilan
                                ? 'border-[#0075de] text-[#0075de]'
                                : 'border-transparent text-[#615d59] hover:text-black'
                        "
                        @click="pilihTampilan(tab.nilai)"
                    >
                        {{ tab.label }}
                    </button>
                </nav>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input
                            v-model="search"
                            :placeholder="props.filter.tampilan === 'kelas' ? 'Cari kode atau nama mata kuliah' : 'Cari nama atau NIM'"
                            aria-label="Cari"
                            class="pl-9"
                        />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim()">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="kirim()">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.baris.length }}</span>
                        {{ props.filter.tampilan === 'kelas' ? 'kelas' : 'mahasiswa' }}
                    </p>
                </div>

                <div v-if="props.filter.tampilan === 'kelas'" class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Smt</th>
                                    <th>SKS</th>
                                    <th>Kelas</th>
                                    <th>Dosen</th>
                                    <th class="text-right">Kapasitas</th>
                                    <th class="text-right">Peserta</th>
                                    <th class="text-right">Disetujui</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in perKelas" :key="b.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground"
                                            >{{ b.kode_matkul }} — {{ b.nama_matkul }}</span
                                        >
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td>{{ b.semester ?? '-' }}</td>
                                    <td>{{ b.sks }}</td>
                                    <td>{{ b.kode_kelas }}</td>
                                    <td>{{ b.dosen ?? '-' }}</td>
                                    <td class="text-right tabular-nums">{{ b.kapasitas }}</td>
                                    <td class="text-right tabular-nums" :class="b.peserta > b.kapasitas ? 'text-[#b42318]' : ''">{{ b.peserta }}</td>
                                    <td class="text-right tabular-nums">{{ b.disetujui }}</td>
                                </tr>
                                <tr v-if="!perKelas.length" class="baris-kosong">
                                    <td colspan="9" class="tabel-kosong">Belum ada kelas kuliah pada tahun akademik ini.</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="perKelas.length">
                                <tr>
                                    <td colspan="7" class="text-right font-medium">Total peserta</td>
                                    <td class="text-right font-medium tabular-nums">{{ totalPeserta }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ perKelas.reduce((n, b) => n + b.disetujui, 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div v-else class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Angkatan</th>
                                    <th class="text-right">Jumlah MK</th>
                                    <th class="text-right">SKS</th>
                                    <th>Status KRS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in perMahasiswa" :key="b.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="whitespace-nowrap">{{ b.nim ?? '-' }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td>{{ b.angkatan ?? '-' }}</td>
                                    <td class="text-right tabular-nums">{{ b.mk }}</td>
                                    <td class="text-right tabular-nums">{{ b.sks }}</td>
                                    <td>
                                        <span
                                            v-if="b.status"
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_KRS[b.status].kelas"
                                            >{{ STATUS_KRS[b.status].label }}</span
                                        >
                                        <span v-else class="text-xs text-[#615d59]">Belum disimpan</span>
                                    </td>
                                </tr>
                                <tr v-if="!perMahasiswa.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada mahasiswa yang mengisi KRS.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
