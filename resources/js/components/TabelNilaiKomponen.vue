<script setup lang="ts">
/**
 * Tabel nilai per komponen satu kelas (Nilai Semester admin dan halaman kelas dosen/admin). Nilai akhir dan huruf
 * dihitung langsung di layar dengan aturan yang sama dengan App\NilaiSemester; yang tersimpan tetap hitungan server.
 * Komponen bersumber kehadiran diambil dari presensi dan tidak bisa diisi.
 */
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

export type KomponenNilai = { id: number; nama: string; persen: number; sumber: 'manual' | 'kehadiran' };
export type SkalaAngka = { huruf: string; angka_minimal: number; lulus: boolean };
export type BarisNilai = {
    krs_id: number;
    mahasiswa_id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    nilai: Record<string, number>;
    kehadiran: number | null;
    nilai_angka: number | null;
    huruf: string | null;
    terkunci_remidi: boolean;
    tervalidasi: boolean;
};

const props = withDefaults(
    defineProps<{
        komponen: KomponenNilai[];
        skala: SkalaAngka[];
        mahasiswa: BarisNilai[];
        url: string;
        bisaUbah: boolean;
        cari?: string;
        tampilProdi?: boolean;
    }>(),
    { cari: '', tampilProdi: false },
);
const slots = defineSlots<{ aksi?(props: { baris: BarisNilai }): unknown }>();

const otomatis = (k: KomponenNilai) => k.sumber === 'kehadiran';
// Baris yang tidak bisa diisi di sini: sudah divalidasi, atau peserta remidi yang hanya berubah lewat remidi.
const terkunci = (m: BarisNilai) => m.tervalidasi || m.terkunci_remidi;
const form = useForm({
    nilai: Object.fromEntries(
        props.mahasiswa.map((m) => [
            m.krs_id,
            Object.fromEntries(props.komponen.filter((k) => !otomatis(k)).map((k) => [k.id, m.nilai[k.id] ?? ''] as [number, number | string])),
        ]),
    ) as Record<number, Record<number, number | string>>,
});

const tampil = computed(() => {
    const q = props.cari.trim().toLowerCase();
    return props.mahasiswa.filter((m) => !q || (m.nama ?? '').toLowerCase().includes(q) || (m.nim ?? '').toLowerCase().includes(q));
});

const angka = (m: BarisNilai, k: KomponenNilai): number | null => {
    if (otomatis(k)) return m.kehadiran;
    const isi = form.nilai[m.krs_id]?.[k.id];
    return isi === '' || isi === null || isi === undefined || Number.isNaN(Number(isi)) ? null : Number(isi);
};
const nilaiAkhir = (m: BarisNilai): number | null => {
    if (terkunci(m)) return m.nilai_angka;
    let total = 0;
    for (const k of props.komponen) {
        const a = angka(m, k);
        if (a === null) return null;
        total += (a * k.persen) / 100;
    }
    return props.komponen.length ? Math.round(total * 100) / 100 : null;
};
const hurufDari = (nilai: number | null) => (nilai === null ? null : (props.skala.find((s) => nilai >= s.angka_minimal) ?? null));
const errorOf = (key: string) => (form.errors as Record<string, string | undefined>)[key];

const simpan = () =>
    form
        .transform((data) => ({
            nilai: Object.fromEntries(
                Object.entries(data.nilai).map(([krs, baris]) => [
                    krs,
                    Object.fromEntries(Object.entries(baris).map(([k, v]) => [k, v === '' ? null : v])),
                ]),
            ),
        }))
        .put(props.url, { preserveScroll: true, preserveState: true });
</script>

<template>
    <form @submit.prevent="simpan">
        <div class="tabel-wadah">
            <div class="tabel-gulir">
                <table class="tabel" :style="{ minWidth: `${(props.tampilProdi ? 560 : 420) + props.komponen.length * 120}px` }">
                    <thead>
                        <tr>
                            <th class="kolom-no">No</th>
                            <th>Mahasiswa</th>
                            <th v-if="props.tampilProdi">Program Studi</th>
                            <th v-for="k in props.komponen" :key="k.id" class="text-center">
                                {{ k.nama }}
                                <span class="block text-xs font-normal normal-case text-[#a39e98]"
                                    >{{ k.persen }}%<template v-if="otomatis(k)"> · otomatis</template></span
                                >
                            </th>
                            <th class="text-right">Nilai Akhir</th>
                            <th class="text-center">Huruf</th>
                            <th v-if="slots.aksi" class="kolom-aksi">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(m, index) in tampil" :key="m.krs_id">
                            <td class="kolom-no">{{ index + 1 }}</td>
                            <td class="font-medium text-black dark:text-foreground">
                                {{ m.nama ?? '-' }}
                                <span class="block text-xs font-normal text-[#615d59]">{{ m.nim ?? '-' }}</span>
                            </td>
                            <td v-if="props.tampilProdi">{{ m.prodi ?? '-' }}</td>
                            <td v-for="k in props.komponen" :key="k.id" class="text-center">
                                <span
                                    v-if="otomatis(k)"
                                    class="tabular-nums"
                                    :title="m.kehadiran === null ? 'Belum ada presensi di pertemuan kuliah yang selesai' : 'Dari presensi'"
                                    >{{ m.kehadiran ?? '-' }}</span
                                >
                                <span v-else-if="terkunci(m)" class="tabular-nums">{{ m.nilai[k.id] ?? '-' }}</span>
                                <template v-else>
                                    <Input
                                        v-model="form.nilai[m.krs_id][k.id]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="mx-auto w-24 text-right"
                                        :disabled="!props.bisaUbah"
                                        :aria-label="`${k.nama} ${m.nama}`"
                                    />
                                    <InputError :message="errorOf(`nilai.${m.krs_id}.${k.id}`)" />
                                </template>
                            </td>
                            <td class="text-right font-medium tabular-nums">
                                {{ nilaiAkhir(m) ?? '-' }}
                                <InputError :message="errorOf(`nilai.${m.krs_id}`)" />
                            </td>
                            <td class="text-center">
                                <template v-if="terkunci(m)">
                                    <span class="font-semibold">{{ m.huruf ?? '-' }}</span>
                                    <span class="block text-xs text-[#a39e98]">{{ m.tervalidasi ? 'tervalidasi' : 'lewat remidi' }}</span>
                                </template>
                                <span
                                    v-else-if="hurufDari(nilaiAkhir(m))"
                                    class="font-semibold"
                                    :class="hurufDari(nilaiAkhir(m))!.lulus ? 'text-black dark:text-foreground' : 'text-[#b42318]'"
                                    >{{ hurufDari(nilaiAkhir(m))!.huruf }}</span
                                >
                                <template v-else-if="m.huruf && m.nilai_angka === null">
                                    {{ m.huruf }} <span class="block text-xs text-[#a39e98]">manual</span>
                                </template>
                                <span v-else class="text-[#a39e98]">-</span>
                            </td>
                            <td v-if="slots.aksi" class="kolom-aksi"><slot name="aksi" :baris="m" /></td>
                        </tr>
                        <tr v-if="!tampil.length" class="baris-kosong">
                            <td :colspan="(props.tampilProdi ? 6 : 5) + props.komponen.length + (slots.aksi ? 1 : 0)" class="tabel-kosong">
                                Tidak ada mahasiswa yang cocok.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="props.bisaUbah && props.mahasiswa.length" class="mt-4 flex justify-end">
            <Button type="submit" :disabled="form.processing">Simpan Nilai</Button>
        </div>
    </form>
</template>
