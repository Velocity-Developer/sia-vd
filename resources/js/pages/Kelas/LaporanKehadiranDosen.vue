<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';

type Baris = {
    dosen: string;
    nidn: string | null;
    kelas_id: number;
    kode_kelas: string;
    mata_kuliah: string | null;
    rencana: number;
    terlaksana: number;
    dijadwal_ulang: number;
    terlewat: number;
    oleh_pengganti: number;
    terlambat: number;
    tanpa_jurnal: number;
    rata_kehadiran: number | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    baris: Baris[];
    toleransi: number;
    tahunAkademikId: number | null;
    prodiId: number | null;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const saring = (perubahan: Record<string, string | number | null>) =>
    router.get(
        route('admin.presensi.laporan-dosen'),
        { tahun_akademik_id: props.tahunAkademikId, prodi_id: props.prodiId, ...perubahan },
        { preserveScroll: true },
    );
const urlCsv = computed(() =>
    route('admin.presensi.laporan-dosen', { tahun_akademik_id: props.tahunAkademikId, prodi_id: props.prodiId, format: 'csv' }),
);

// Baris dikelompokkan per dosen dengan subtotal.
const perDosen = computed(() => {
    const grup = new Map<string, Baris[]>();
    props.baris.forEach((b) => grup.set(`${b.dosen}|${b.nidn}`, [...(grup.get(`${b.dosen}|${b.nidn}`) ?? []), b]));

    return [...grup.values()].map((kelas) => ({
        dosen: kelas[0].dosen,
        nidn: kelas[0].nidn,
        kelas,
        rencana: kelas.reduce((n, b) => n + b.rencana, 0),
        terlaksana: kelas.reduce((n, b) => n + b.terlaksana, 0),
        terlewat: kelas.reduce((n, b) => n + b.terlewat, 0),
        terlambat: kelas.reduce((n, b) => n + b.terlambat, 0),
        tanpa_jurnal: kelas.reduce((n, b) => n + b.tanpa_jurnal, 0),
    }));
});
</script>

<template>
    <Head title="Laporan Kehadiran Dosen" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi', href: route('admin.presensi.index') },
            { title: 'Laporan Kehadiran Dosen', href: route('admin.presensi.laporan-dosen') },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Laporan Kehadiran Dosen</h1>
                        <p class="deskripsi-halaman">
                            Pertemuan terlaksana dibanding rencana per kelas. Terlambat = jam masuk lewat {{ props.toleransi }} menit dari jam mulai;
                            tanpa jurnal = pertemuan selesai tanpa topik.
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="urlCsv"><Download /> Unduh CSV</a>
                    </Button>
                </div>

                <div class="bilah-filter">
                    <SelectFilter
                        :model-value="props.tahunAkademikId ?? ''"
                        label="Tahun akademik"
                        @update:model-value="(nilai) => saring({ tahun_akademik_id: nilai })"
                    >
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <SelectFilter
                        :model-value="props.prodiId ?? ''"
                        label="Program studi"
                        @update:model-value="(nilai) => saring({ prodi_id: nilai || null })"
                    >
                        <option value="">Semua program studi</option>
                        <option v-for="p in props.prodiOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectFilter>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <!-- Baris dikelompokkan per dosen: kolom No menomori dosen, baris kelas di bawahnya menjorok. -->
                        <table class="tabel min-w-[1040px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Dosen / Kelas</th>
                                    <th class="text-center">Terlaksana</th>
                                    <th class="text-center" title="Pertemuan yang pernah dipindah tanggal, jam, ruang, atau dosennya">
                                        Dijadwal ulang
                                    </th>
                                    <th class="text-center" title="Jadwalnya sudah lewat tetapi tidak pernah dimulai">Terlewat</th>
                                    <th class="text-center">Oleh pengganti</th>
                                    <th class="text-center">Masuk terlambat</th>
                                    <th class="text-center">Tanpa jurnal</th>
                                    <th class="text-center">Rata hadir mhs</th>
                                </tr>
                            </thead>
                            <tbody v-for="(d, index) in perDosen" :key="`${d.dosen}${d.nidn}`" class="border-t border-[#e6e6e6] dark:border-border">
                                <tr class="bg-[#fbfaf9] dark:bg-muted/40">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="font-medium text-black dark:text-foreground">
                                        {{ d.dosen }} <span class="teks-bantu font-normal">{{ d.nidn }}</span>
                                    </td>
                                    <td class="text-center font-medium tabular-nums">{{ d.terlaksana }} / {{ d.rencana }}</td>
                                    <td />
                                    <td class="text-center font-medium tabular-nums" :class="d.terlewat ? 'text-[#dd5b00]' : ''">{{ d.terlewat }}</td>
                                    <td />
                                    <td class="text-center font-medium tabular-nums" :class="d.terlambat ? 'text-[#dd5b00]' : ''">
                                        {{ d.terlambat }}
                                    </td>
                                    <td class="text-center font-medium tabular-nums" :class="d.tanpa_jurnal ? 'text-[#dd5b00]' : ''">
                                        {{ d.tanpa_jurnal }}
                                    </td>
                                    <td />
                                </tr>
                                <tr v-for="b in d.kelas" :key="b.kelas_id">
                                    <td class="kolom-no" />
                                    <td class="pl-7">
                                        <Link :href="route('admin.presensi.kelas', b.kelas_id)" class="text-[#0075de] hover:underline">{{
                                            b.kode_kelas
                                        }}</Link>
                                        <span class="teks-bantu"> · {{ b.mata_kuliah }}</span>
                                    </td>
                                    <td class="text-center tabular-nums">{{ b.terlaksana }} / {{ b.rencana }}</td>
                                    <td class="text-center tabular-nums">{{ b.dijadwal_ulang }}</td>
                                    <td class="text-center tabular-nums" :class="b.terlewat ? 'font-medium text-[#dd5b00]' : ''">{{ b.terlewat }}</td>
                                    <td class="text-center tabular-nums">{{ b.oleh_pengganti }}</td>
                                    <td class="text-center tabular-nums">{{ b.terlambat }}</td>
                                    <td class="text-center tabular-nums">{{ b.tanpa_jurnal }}</td>
                                    <td class="text-center tabular-nums">{{ b.rata_kehadiran === null ? '-' : `${b.rata_kehadiran}%` }}</td>
                                </tr>
                            </tbody>
                            <tbody v-if="!perDosen.length">
                                <tr class="baris-kosong">
                                    <td colspan="9" class="tabel-kosong">Tidak ada kelas pada tahun akademik ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
