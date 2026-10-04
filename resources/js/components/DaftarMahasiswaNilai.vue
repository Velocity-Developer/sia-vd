<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Link, router } from '@inertiajs/vue3';
import { Download, Eye, Search } from 'lucide-vue-next';
import { type Component, ref, watch } from 'vue';

type Baris = { id: number; nim: string | null; nama: string | null; prodi: string | null; angkatan: string | null };
type Opsi = { id: number; name: string };

export type DaftarMahasiswaNilaiProps = {
    mahasiswa: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id?: number | null; prodi_id: number | null; search: string };
    // Tanpa opsi tahun akademik: daftar semua mahasiswa ber-KRS (Pendataan Nilai Akhir).
    tahunAkademikOptions?: Opsi[];
    prodiOptions: Opsi[];
};

// Daftar mahasiswa ber-KRS untuk menu Penilaian dan Hasil Studi; `rute` = awalan nama rute (…index, dan …show kecuali
// `ruteAksi` diisi). `ruteUnduh` menambah tombol Download (PDF) di samping tombol aksi.
const props = defineProps<
    DaftarMahasiswaNilaiProps & {
        rute: string;
        ruteAksi?: string;
        ruteUnduh?: string;
        judul: string;
        deskripsi: string;
        labelAksi: string;
        ikonAksi?: Component;
    }
>();
const parameterBaris = (id: number) => ({ mahasiswa: id, tahun_akademik_id: props.filter.tahun_akademik_id ?? undefined });

const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id ?? null);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route(`${props.rute}.index`),
        {
            tahun_akademik_id: tahunAkademikId.value ?? undefined,
            prodi_id: prodiId.value === semua ? null : prodiId.value,
            search: search.value || null,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});
</script>

<template>
    <div class="konten">
        <div class="kepala-halaman">
            <div>
                <h1 class="judul-halaman">{{ props.judul }}</h1>
                <p class="deskripsi-halaman">{{ props.deskripsi }}</p>
            </div>
        </div>

        <div class="bilah-filter">
            <div class="kolom-cari">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari" class="pl-9" />
            </div>
            <SelectFilter v-if="props.tahunAkademikOptions" v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim()">
                <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
            </SelectFilter>
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
                <table class="tabel min-w-[600px]">
                    <thead>
                        <tr>
                            <th class="kolom-no">No</th>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Angkatan</th>
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
                            <td class="kolom-aksi">
                                <div class="aksi-tabel">
                                    <Button v-if="props.ruteUnduh" as-child size="sm" variant="outline">
                                        <a :href="route(props.ruteUnduh, parameterBaris(b.id))"><Download class="size-4" /> Download</a>
                                    </Button>
                                    <Button as-child size="sm" variant="outline">
                                        <Link :href="route(props.ruteAksi ?? `${props.rute}.show`, parameterBaris(b.id))">
                                            <component :is="props.ikonAksi ?? Eye" class="size-4" /> {{ props.labelAksi }}
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.mahasiswa.data.length" class="baris-kosong">
                            <td colspan="5" class="tabel-kosong">
                                Belum ada mahasiswa dengan KRS{{ props.tahunAkademikOptions ? ' pada tahun akademik ini' : '' }}.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination :links="props.mahasiswa.links" :total="props.mahasiswa.total" />
    </div>
</template>
