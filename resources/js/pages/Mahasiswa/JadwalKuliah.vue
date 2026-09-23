<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
const hariAktif = ref(jadwalGroups.value.some((group) => group.hari === hariIni) ? hariIni : (jadwalGroups.value[0]?.hari ?? ''));
const jadwalHariAktif = computed(() => jadwalGroups.value.find((group) => group.hari === hariAktif.value)?.entries ?? []);
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
                    <div class="relative overflow-x-auto rounded-xl border border-[#e6e6e6] bg-white p-2 shadow-sm">
                        <div class="flex min-w-max gap-2" role="tablist" aria-label="Hari jadwal kuliah">
                            <button
                                v-for="group in jadwalGroups"
                                :key="group.hari"
                                type="button"
                                role="tab"
                                :aria-selected="hariAktif === group.hari"
                                class="min-w-[92px] rounded-lg px-4 py-3 text-left transition"
                                :class="
                                    hariAktif === group.hari
                                        ? 'bg-[#0075de] text-white shadow-sm'
                                        : 'text-[#615d59] hover:bg-[#f6f5f4] hover:text-black'
                                "
                                @click="hariAktif = group.hari"
                            >
                                <span class="block text-sm font-semibold">{{ group.hari }}</span>
                                <span class="mt-1 block text-xs" :class="hariAktif === group.hari ? 'text-blue-100' : 'text-[#8a8580]'">
                                    {{ group.hari === hariIni ? 'Hari ini' : `${group.entries.length} kelas` }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <section class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-[#e6e6e6] px-5 py-4">
                            <div>
                                <h2 class="text-lg font-semibold text-black">{{ hariAktif }}</h2>
                                <p v-if="hariAktif === hariIni" class="mt-1 text-sm text-[#0075de]">Jadwal hari ini</p>
                            </div>
                            <span class="rounded-full bg-[#f0f7ff] px-3 py-1 text-xs font-semibold text-[#0075de]">
                                {{ jadwalHariAktif.length }} kelas
                            </span>
                        </div>
                        <div class="grid gap-3 p-4">
                            <Link
                                v-for="(entry, index) in jadwalHariAktif"
                                :key="`${entry.kelas.id}-${entry.item.jam_mulai}-${index}`"
                                :href="route('mahasiswa.jadwal-kuliah.show', entry.kelas.id)"
                                class="group block rounded-lg border border-[#e6e6e6] bg-white p-4 transition hover:border-[#0075de] hover:bg-[#f8fbff] hover:shadow-sm"
                            >
                                <article>
                                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 rounded-lg bg-[#f0f7ff] px-3 py-2 text-center text-[#0075de]">
                                                <p class="text-sm font-bold">{{ jam(entry.item.jam_mulai) }}</p>
                                                <p class="text-[11px]">{{ jam(entry.item.jam_akhir) }}</p>
                                            </div>
                                            <div class="space-y-1">
                                                <h3 class="font-semibold text-black group-hover:text-[#0075de]">
                                                    {{ matkul(entry.kelas)?.nama_matkul ?? '-' }}
                                                </h3>
                                                <p class="text-sm text-[#615d59]">
                                                    Kelas {{ entry.kelas.kode_kelas }} · {{ matkul(entry.kelas)?.sks ?? '-' }} SKS
                                                </p>
                                            </div>
                                        </div>
                                        <p class="text-sm text-[#615d59] sm:text-right">Ruang {{ entry.item.ruang?.kode_ruang ?? '-' }}</p>
                                    </div>
                                </article>
                            </Link>
                            <p v-if="!jadwalHariAktif.length" class="px-2 py-10 text-center text-sm text-[#615d59]">
                                Tidak ada jadwal pada hari ini.
                            </p>
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
