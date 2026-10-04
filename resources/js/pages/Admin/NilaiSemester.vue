<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Kelas = {
    id: number;
    kode_matkul: string | null;
    nama_matkul: string | null;
    prodi: string | null;
    semester: number | null;
    sks: number;
    kode_kelas: string;
    dosen: string | null;
    peserta: number;
    dinilai: number;
    dinilai_di: 'pendadaran' | 'kkm' | null;
    final: boolean;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    kelas: Kelas[];
    komponen: { id: number; nama: string; persen: number }[];
    persenLengkap: boolean;
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; search: string };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route('admin.nilai-semester.index'),
        { tahun_akademik_id: tahunAkademikId.value, prodi_id: prodiId.value === semua ? null : prodiId.value, search: search.value || null },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});
</script>

<template>
    <Head title="Nilai Semester" />
    <AppLayout :breadcrumbs="[{ title: 'Nilai Semester', href: route('admin.nilai-semester.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Nilai Semester</h1>
                        <p class="deskripsi-halaman">Isi nilai tiap komponen per kelas; nilai akhir dan huruf dihitung otomatis.</p>
                    </div>
                </div>

                <p v-if="!props.persenLengkap" class="alert-info" role="status">
                    Komponen nilai belum diatur atau jumlah persennya belum 100%. Atur dulu di
                    <Link :href="route('admin.komponen-nilai.index')" class="font-medium underline">Tambah Komponen Nilai</Link>.
                </p>
                <p v-else class="teks-bantu">
                    Komponen:
                    <span v-for="(k, i) in props.komponen" :key="k.id">{{ i ? ', ' : '' }}{{ k.nama }} {{ k.persen }}%</span>
                </p>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode atau nama mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim()">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="kirim()">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.kelas.length }}</span> kelas
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[860px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Smt</th>
                                    <th>SKS</th>
                                    <th>Kelas</th>
                                    <th>Dosen</th>
                                    <th class="text-right">Sudah dinilai</th>
                                    <th class="kolom-aksi"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(k, index) in props.kelas" :key="k.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground"
                                            >{{ k.kode_matkul }} — {{ k.nama_matkul }}</span
                                        >
                                        <span class="block text-xs text-[#a39e98]">{{ k.prodi }}</span>
                                    </td>
                                    <td>{{ k.semester ?? '-' }}</td>
                                    <td>{{ k.sks }}</td>
                                    <td>{{ k.kode_kelas }}</td>
                                    <td>{{ k.dosen ?? '-' }}</td>
                                    <td class="text-right tabular-nums" :class="k.peserta && k.dinilai === k.peserta ? 'text-[#1aae39]' : ''">
                                        {{ k.dinilai }} / {{ k.peserta }}
                                    </td>
                                    <td class="kolom-aksi">
                                        <span v-if="k.dinilai_di === 'pendadaran'" class="whitespace-nowrap text-xs text-[#615d59]"
                                            >Dari pendadaran</span
                                        >
                                        <Button v-else-if="k.dinilai_di === 'kkm'" as-child size="sm" variant="outline">
                                            <Link :href="route('admin.nilai-kkm.index')">Nilai KKM</Link>
                                        </Button>
                                        <Button v-else as-child size="sm" variant="outline">
                                            <Link :href="route('admin.nilai-semester.show', k.id)">Isi Nilai</Link>
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.kelas.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Belum ada kelas kuliah pada tahun akademik ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
