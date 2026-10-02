<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import TabPresensiDosen from '@/components/TabPresensiDosen.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';

type Baris = {
    dosen_id: number;
    dosen: string;
    nidn: string | null;
    terlaksana: number;
    digantikan: number;
    jam: number;
    sks: number;
    terlambat: number;
    belum_verifikasi: number;
    tidak_hadir: number;
    kuliah_diganti: number;
    sakit: number;
    izin: number;
    alpa: number;
    terlewat: number;
};
type Opsi = { id: number | string; name: string };
type Filter = {
    tahun_akademik_id: number | null;
    prodi_id: number | null;
    dosen_id: number | null;
    search: string;
    bulan: string | null;
    semua: boolean;
};

const props = defineProps<{
    baris: Baris[];
    filter: Filter;
    toleransi: number;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    dosenFilterOptions: Opsi[];
}>();

const parameter = (perubahan: Partial<Filter> = {}) => {
    const isi = { ...props.filter, ...perubahan };

    return Object.fromEntries(
        Object.entries({ ...isi, semua: isi.semua ? 1 : null, search: null }).filter(([, nilai]) => nilai !== null && nilai !== ''),
    );
};
const saring = (perubahan: Partial<Filter>) => router.get(route('admin.presensi-dosen.rekap'), parameter(perubahan), { preserveScroll: true });
const urlCsv = computed(() => route('admin.presensi-dosen.rekap', { ...parameter(), format: 'csv' }));

const total = computed(() => ({
    terlaksana: props.baris.reduce((n, b) => n + b.terlaksana, 0),
    jam: Math.round(props.baris.reduce((n, b) => n + b.jam, 0) * 100) / 100,
    sks: props.baris.reduce((n, b) => n + b.sks, 0),
}));
const angka = (n: number) => n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Rekap Presensi Dosen" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi Dosen', href: route('admin.presensi-dosen.index') },
            { title: 'Rekap per Dosen', href: route('admin.presensi-dosen.rekap') },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Presensi Dosen</h1>
                        <p class="deskripsi-halaman">
                            Rekap per dosen pengajar untuk honor. Jam = durasi jadwal pertemuan terlaksana; SKS = SKS mata kuliah tiap pertemuan
                            terlaksana. Terlambat = masuk lewat {{ props.toleransi }} menit.
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="urlCsv"><Download /> Unduh CSV</a>
                    </Button>
                </div>

                <TabPresensiDosen aktif="rekap" />

                <div class="bilah-filter flex-wrap">
                    <SelectFilter
                        :model-value="props.filter.tahun_akademik_id ?? ''"
                        label="Tahun akademik"
                        @update:model-value="(v) => saring({ tahun_akademik_id: Number(v) })"
                    >
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="teks-bantu">Bulan</span>
                        <input
                            type="month"
                            class="isian w-auto"
                            :value="props.filter.bulan ?? ''"
                            aria-label="Bulan"
                            @change="saring({ bulan: ($event.target as HTMLInputElement).value || null })"
                        />
                    </label>
                    <SelectFilter
                        :model-value="props.filter.prodi_id ?? ''"
                        label="Program studi"
                        @update:model-value="(v) => saring({ prodi_id: v ? Number(v) : null })"
                    >
                        <option value="">Semua program studi</option>
                        <option v-for="p in props.prodiOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectFilter>
                    <SelectFilter
                        :model-value="props.filter.dosen_id ?? ''"
                        label="Dosen"
                        @update:model-value="(v) => saring({ dosen_id: v ? Number(v) : null })"
                    >
                        <option value="">Semua dosen</option>
                        <option v-for="d in props.dosenFilterOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </SelectFilter>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            :checked="props.filter.semua"
                            @change="saring({ semua: ($event.target as HTMLInputElement).checked })"
                        />
                        Hitung juga yang belum diverifikasi
                    </label>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1120px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Dosen</th>
                                    <th class="text-center">Terlaksana</th>
                                    <th class="text-center" title="Pertemuan yang diajar sebagai dosen pengganti">Sbg pengganti</th>
                                    <th class="text-center">Total jam</th>
                                    <th class="text-center">Total SKS</th>
                                    <th class="text-center">Terlambat</th>
                                    <th class="text-center">Belum diverifikasi</th>
                                    <th class="text-center">Tidak hadir</th>
                                    <th class="text-center">Kuliah diganti</th>
                                    <th class="text-center">Sakit</th>
                                    <th class="text-center">Izin</th>
                                    <th class="text-center">Alpa</th>
                                    <th class="text-center" title="Jadwal lewat tanpa dibuka dan tanpa keterangan">Terlewat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, i) in props.baris" :key="b.dosen_id">
                                    <td class="kolom-no">{{ i + 1 }}</td>
                                    <td>
                                        <Link
                                            :href="
                                                route('admin.presensi-dosen.index', {
                                                    tahun_akademik_id: props.filter.tahun_akademik_id,
                                                    dosen_id: b.dosen_id,
                                                })
                                            "
                                            class="text-[#0075de] hover:underline"
                                            >{{ b.dosen }}</Link
                                        >
                                        <p class="teks-bantu">{{ b.nidn }}</p>
                                    </td>
                                    <td class="text-center font-medium tabular-nums">{{ b.terlaksana }}</td>
                                    <td class="text-center tabular-nums">{{ b.digantikan }}</td>
                                    <td class="text-center tabular-nums">{{ angka(b.jam) }}</td>
                                    <td class="text-center tabular-nums">{{ b.sks }}</td>
                                    <td class="text-center tabular-nums" :class="b.terlambat ? 'font-medium text-[#dd5b00]' : ''">
                                        {{ b.terlambat }}
                                    </td>
                                    <td class="text-center tabular-nums" :class="b.belum_verifikasi ? 'font-medium text-[#8a5a00]' : ''">
                                        {{ b.belum_verifikasi }}
                                    </td>
                                    <td class="text-center tabular-nums">{{ b.tidak_hadir }}</td>
                                    <td class="text-center tabular-nums">{{ b.kuliah_diganti }}</td>
                                    <td class="text-center tabular-nums">{{ b.sakit }}</td>
                                    <td class="text-center tabular-nums">{{ b.izin }}</td>
                                    <td class="text-center tabular-nums" :class="b.alpa ? 'font-medium text-[#b42318]' : ''">{{ b.alpa }}</td>
                                    <td class="text-center tabular-nums" :class="b.terlewat ? 'font-medium text-[#dd5b00]' : ''">{{ b.terlewat }}</td>
                                </tr>
                                <tr v-if="!props.baris.length">
                                    <td colspan="14" class="tabel-kosong">Tidak ada pertemuan pada periode ini.</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="props.baris.length">
                                <tr class="font-medium">
                                    <td />
                                    <td>Total</td>
                                    <td class="text-center tabular-nums">{{ total.terlaksana }}</td>
                                    <td />
                                    <td class="text-center tabular-nums">{{ angka(total.jam) }}</td>
                                    <td class="text-center tabular-nums">{{ total.sks }}</td>
                                    <td colspan="8" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
