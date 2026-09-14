<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Jadwal = { hari: string; jam_mulai: string; jam_akhir: string; ruang?: { kode_ruang: string } | null };
type KelasKuliah = {
    id: number;
    kode_kelas: string;
    mata_kuliah?: { nama_matkul: string; sks?: number } | null;
    mataKuliah?: { nama_matkul: string; sks?: number } | null;
    jadwals: Jadwal[];
};
type JadwalEntry = { kelas: KelasKuliah; item: Jadwal };
type JadwalGroup = { hari: string; entries: JadwalEntry[] };

const props = defineProps<{ kelasKuliahs: KelasKuliah[] }>();
const hariOrder: Record<string, number> = { Senin: 1, Selasa: 2, Rabu: 3, Kamis: 4, Jumat: 5, Sabtu: 6, Minggu: 7 };
const jam = (value: string) => value.slice(0, 5);
const matkul = (kelas: KelasKuliah) => kelas.mataKuliah ?? kelas.mata_kuliah;
const jadwalGroups = computed<JadwalGroup[]>(() => {
    const groups = new Map<string, JadwalEntry[]>();

    for (const kelas of props.kelasKuliahs) {
        for (const item of kelas.jadwals ?? []) {
            const entries = groups.get(item.hari) ?? [];
            entries.push({ kelas, item });
            groups.set(item.hari, entries);
        }
    }

    return [...groups.entries()]
        .sort(([first], [second]) => (hariOrder[first] ?? 99) - (hariOrder[second] ?? 99))
        .map(([hari, entries]) => ({
            hari,
            entries: entries.sort((first, second) => first.item.jam_mulai.localeCompare(second.item.jam_mulai)),
        }));
});
const hariIni = new Intl.DateTimeFormat('id-ID', { weekday: 'long' }).format(new Date());
</script>

<template>
    <Head title="Jadwal Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Kuliah', href: route('mahasiswa.jadwal-kuliah') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1100px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Jadwal Kuliah</h1>
                    <p class="text-sm leading-5 text-[#615d59]">Jadwal kuliah dari kelas yang Anda ambil.</p>
                </div>
                <div v-if="jadwalGroups.length" class="flex flex-col gap-5">
                    <section
                        v-for="group in jadwalGroups"
                        :key="group.hari"
                        class="overflow-hidden rounded-xl border bg-white shadow-sm"
                        :class="group.hari === hariIni ? 'border-[#0075de]' : 'border-[#e6e6e6]'"
                    >
                        <div
                            class="border-b px-5 py-4"
                            :class="group.hari === hariIni ? 'border-[#0075de] bg-[#0075de] text-white' : 'border-[#e6e6e6] bg-white text-black'"
                        >
                            <h2 class="text-lg font-semibold">{{ group.hari }}</h2>
                            <p v-if="group.hari === hariIni" class="mt-1 text-sm text-blue-100">Hari ini</p>
                        </div>
                        <div class="grid gap-4 p-4">
                            <Link
                                v-for="(entry, index) in group.entries"
                                :href="route('mahasiswa.jadwal-kuliah.show', entry.kelas.id)"
                                class="block rounded-lg border border-[#e6e6e6] bg-white p-4 transition hover:border-[#0075de] hover:bg-[#f8fbff] hover:shadow-sm"
                            >
                                <article :key="`${entry.kelas.id}-${entry.item.jam_mulai}-${index}`" class="rounded-lg">
                                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                        <div class="space-y-1">
                                            <h3 class="font-semibold text-black">{{ matkul(entry.kelas)?.nama_matkul ?? '-' }}</h3>
                                            <p class="text-sm text-[#615d59]">
                                                Kelas {{ entry.kelas.kode_kelas }} · {{ matkul(entry.kelas)?.sks ?? '-' }} SKS
                                            </p>
                                        </div>
                                        <div class="space-y-1 text-sm text-[#615d59] sm:text-right">
                                            <p class="font-medium text-black">{{ jam(entry.item.jam_mulai) }} - {{ jam(entry.item.jam_akhir) }}</p>
                                            <p>Ruang {{ entry.item.ruang?.kode_ruang ?? '-' }}</p>
                                        </div>
                                    </div>
                                </article>
                            </Link>
                        </div>
                    </section>
                </div>
                <div v-else class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-16 text-center text-sm text-[#615d59] shadow-sm">
                    Belum ada jadwal kuliah.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
