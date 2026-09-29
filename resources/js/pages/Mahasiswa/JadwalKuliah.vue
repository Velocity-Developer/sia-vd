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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Jadwal Kuliah</h1>
                        <p class="deskripsi-halaman">Jadwal kuliah dari kelas yang Anda ambil.</p>
                    </div>
                </div>
                <template v-if="jadwalGroups.length">
                    <div class="kartu relative overflow-x-auto p-2">
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
                                        : 'text-[#615d59] hover:bg-[#f6f5f4] hover:text-black dark:text-muted-foreground dark:hover:bg-accent dark:hover:text-foreground'
                                "
                                @click="hariAktif = group.hari"
                            >
                                <span class="block text-sm font-semibold">{{ group.hari }}</span>
                                <span class="mt-1 block text-xs" :class="hariAktif === group.hari ? 'text-blue-100' : 'text-[#a39e98]'">
                                    {{ group.hari === hariIni ? 'Hari ini' : `${group.entries.length} kelas` }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <section class="kartu overflow-hidden">
                        <div class="flex items-center justify-between border-b border-[#e6e6e6] px-6 py-4 dark:border-border">
                            <div>
                                <h2 class="judul-bagian">{{ hariAktif }}</h2>
                                <p v-if="hariAktif === hariIni" class="mt-1 text-sm text-[#0075de]">Jadwal hari ini</p>
                            </div>
                            <span class="rounded-full bg-[#f0f7ff] px-3 py-1 text-xs font-semibold text-[#0075de]">
                                {{ jadwalHariAktif.length }} kelas
                            </span>
                        </div>
                        <div class="grid gap-3 p-4 sm:p-6">
                            <Link
                                v-for="(entry, index) in jadwalHariAktif"
                                :key="`${entry.kelas.id}-${entry.item.jam_mulai}-${index}`"
                                :href="route('mahasiswa.jadwal-kuliah.show', entry.kelas.id)"
                                class="group block rounded-lg border border-[#e6e6e6] p-4 transition hover:border-[#0075de] hover:bg-[#f8fbff] hover:shadow-sm dark:border-border dark:hover:bg-accent/40"
                            >
                                <article>
                                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 rounded-lg bg-[#f0f7ff] px-3 py-2 text-center text-[#0075de]">
                                                <p class="text-sm font-bold">{{ jam(entry.item.jam_mulai) }}</p>
                                                <p class="text-xs">{{ jam(entry.item.jam_akhir) }}</p>
                                            </div>
                                            <div class="space-y-1">
                                                <h3 class="text-sm font-semibold text-black group-hover:text-[#0075de] dark:text-foreground">
                                                    {{ matkul(entry.kelas)?.nama_matkul ?? '-' }}
                                                </h3>
                                                <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                                                    Kelas {{ entry.kelas.kode_kelas }} · {{ matkul(entry.kelas)?.sks ?? '-' }} SKS
                                                </p>
                                            </div>
                                        </div>
                                        <p class="text-sm text-[#615d59] dark:text-muted-foreground sm:text-right">
                                            Ruang {{ entry.item.ruang?.kode_ruang ?? '-' }}
                                        </p>
                                    </div>
                                </article>
                            </Link>
                            <p v-if="!jadwalHariAktif.length" class="tabel-kosong">Tidak ada jadwal pada hari ini.</p>
                        </div>
                    </section>
                </template>
                <div v-else class="kartu tabel-kosong">Belum ada jadwal kuliah.</div>
            </div>
        </div>
    </AppLayout>
</template>
