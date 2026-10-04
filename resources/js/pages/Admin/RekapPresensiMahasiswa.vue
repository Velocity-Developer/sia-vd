<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Opsi = { id: number; name: string };
type Baris = {
    id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    kode_matkul: string | null;
    nama_matkul: string | null;
    kode_kelas: string | null;
    hadir: number;
    terlambat: number;
    izin: number;
    sakit: number;
    alpa: number;
    dihitung: number;
    persen: number | null;
    min: number;
    memenuhi: boolean | null;
};

const props = defineProps<{
    rekap: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; search: string };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const tahunAkademikId = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const prodiId = ref<number | string>(props.filter.prodi_id ?? '');
const search = ref(props.filter.search);
const parameter = computed(() => ({ tahun_akademik_id: tahunAkademikId.value, prodi_id: prodiId.value, search: search.value }));
const saring = () => router.get(route('admin.rekap-presensi.index'), parameter.value, { preserveState: true, preserveScroll: true, replace: true });
watch(search, saring);

const urlUnduh = computed(() => route('admin.rekap-presensi.unduh', parameter.value));
const persen = (b: Baris) => (b.persen === null ? '-' : `${b.persen.toLocaleString('id-ID')}%`);
</script>

<template>
    <Head title="Rekap Presensi Mahasiswa" />
    <AppLayout :breadcrumbs="[{ title: 'Rekap Presensi Mahasiswa', href: route('admin.rekap-presensi.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Rekap Presensi Mahasiswa</h1>
                        <p class="deskripsi-halaman">
                            Kehadiran tiap mahasiswa per mata kuliah dari pertemuan kuliah yang sudah selesai, dibandingkan syarat ujian prodinya.
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="urlUnduh"><Download /> Unduh Excel</a>
                    </Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari NIM atau nama mahasiswa" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="saring">
                        <option value="">Semua Tahun Akademik</option>
                        <option v-for="ta in props.tahunAkademikOptions" :key="ta.id" :value="ta.id">{{ ta.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-if="props.prodiOptions.length > 1" v-model="prodiId" label="Filter program studi" @change="saring">
                        <option value="">Semua Program Studi</option>
                        <option v-for="prodi in props.prodiOptions" :key="prodi.id" :value="prodi.id">{{ prodi.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.rekap.total }}</span> baris
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1100px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th class="text-center" title="Pertemuan kuliah selesai yang tercatat">Dihitung</th>
                                    <th class="text-center">H</th>
                                    <th class="text-center">T</th>
                                    <th class="text-center">I</th>
                                    <th class="text-center">S</th>
                                    <th class="text-center">A</th>
                                    <th class="text-center">Kehadiran</th>
                                    <th>Syarat Ujian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.rekap.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.rekap.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ b.nama ?? '-' }}</span>
                                        <span class="teks-bantu block">{{ b.nim ?? '-' }} · {{ b.prodi ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="block">{{ b.nama_matkul ?? '-' }}</span>
                                        <span class="teks-bantu block">{{ b.kode_matkul }}</span>
                                    </td>
                                    <td>{{ b.kode_kelas ?? '-' }}</td>
                                    <td class="text-center">{{ b.dihitung }}</td>
                                    <td class="text-center">{{ b.hadir }}</td>
                                    <td class="text-center">{{ b.terlambat }}</td>
                                    <td class="text-center">{{ b.izin }}</td>
                                    <td class="text-center">{{ b.sakit }}</td>
                                    <td class="text-center">{{ b.alpa }}</td>
                                    <td class="text-center font-medium" :class="b.memenuhi === false ? 'text-[#dd5b00]' : 'text-black'">
                                        {{ persen(b) }}
                                    </td>
                                    <td>
                                        <span v-if="b.memenuhi === null" class="teks-bantu">-</span>
                                        <span
                                            v-else
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="b.memenuhi ? 'bg-[#1aae39]/10 text-[#137a2a]' : 'bg-[#dd5b00]/10 text-[#b54a00]'"
                                            >{{ b.memenuhi ? 'Memenuhi' : `Di bawah ${b.min}%` }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!props.rekap.data.length" class="baris-kosong">
                                    <td colspan="12" class="tabel-kosong">Belum ada KRS pada filter ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="teks-bantu">
                    H = hadir, T = terlambat (dihitung hadir), I = izin, S = sakit, A = alpa. Status syarat belum memperhitungkan dispensasi.
                </p>

                <Pagination :links="props.rekap.links" :total="props.rekap.total" />
            </div>
        </div>
    </AppLayout>
</template>
