<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Printer, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Baris = {
    id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    angkatan: string | null;
    sks: number;
    syarat_kurang: string[];
};
type Opsi = { id: number; name: string };

/** Satu halaman untuk dua menu: Cetak KST (mode kst) dan Cetak Kartu Ujian (mode kartu). */
const props = defineProps<{
    mode: 'kst' | 'kartu';
    mahasiswa: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; search: string; jenis: 'uts' | 'uas' | null };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    syaratUjian: number | null;
}>();

const kartu = computed(() => props.mode === 'kartu');
const judul = computed(() => (kartu.value ? 'Cetak Kartu Ujian' : 'Cetak KST'));
const ruteDaftar = computed(() => (kartu.value ? 'admin.kartu-ujian.index' : 'admin.cetak-kst.index'));

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const jenis = ref(props.filter.jenis ?? 'uts');
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route(ruteDaftar.value),
        {
            tahun_akademik_id: tahunAkademikId.value,
            prodi_id: prodiId.value === semua ? null : prodiId.value,
            jenis: kartu.value ? jenis.value : null,
            search: search.value || null,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const urlCetak = (b: Baris) =>
    kartu.value
        ? route('admin.kartu-ujian.cetak', { mahasiswa: b.id, tahun_akademik_id: props.filter.tahun_akademik_id, jenis: props.filter.jenis })
        : route('admin.cetak-kst.cetak', { mahasiswa: b.id, tahun_akademik_id: props.filter.tahun_akademik_id });
</script>

<template>
    <Head :title="judul" />
    <AppLayout :breadcrumbs="[{ title: judul, href: route(ruteDaftar) }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ judul }}</h1>
                        <p class="deskripsi-halaman">
                            <template v-if="kartu">
                                Kartu UTS dan UAS dicetak terpisah per mahasiswa, berisi mata kuliah dari KRS yang sudah disetujui.
                                <template v-if="props.syaratUjian !== null"
                                    >Kartu tidak bisa dicetak bila kehadiran di bawah {{ props.syaratUjian }}% (kecuali ada dispensasi).</template
                                >
                            </template>
                            <template v-else>Kartu Studi Tetap per mahasiswa, berisi KRS yang sudah disetujui.</template>
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim()">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="kirim()">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-if="kartu" v-model="jenis" label="Jenis ujian" @change="kirim()">
                        <option value="uts">Kartu UTS</option>
                        <option value="uas">Kartu UAS</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.mahasiswa.total }}</span> mahasiswa
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[720px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Angkatan</th>
                                    <th>SKS</th>
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
                                        <span v-if="b.syarat_kurang.length" class="mt-1 block max-w-[360px] text-xs text-[#b42318]"
                                            >Kehadiran kurang: {{ b.syarat_kurang.join(', ') }}</span
                                        >
                                    </td>
                                    <td>{{ b.angkatan ?? '-' }}</td>
                                    <td>{{ b.sks }}</td>
                                    <td class="kolom-aksi">
                                        <Button v-if="b.syarat_kurang.length" size="sm" variant="outline" disabled>
                                            <Printer class="size-4" /> Cetak
                                        </Button>
                                        <Button v-else as-child size="sm" variant="outline">
                                            <a :href="urlCetak(b)" target="_blank" rel="noopener"><Printer class="size-4" /> Cetak</a>
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.mahasiswa.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada mahasiswa dengan KRS yang disetujui.</td>
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
