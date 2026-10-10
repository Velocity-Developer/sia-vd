<script setup lang="ts">
import { computed } from 'vue';

export type GrafikKampusData = {
    status: { nama: string; jumlah: number }[];
    sebaran: { judul: string; data: { nama: string; jumlah: number }[] };
};

const props = defineProps<{ grafik: GrafikKampusData }>();

const angka = new Intl.NumberFormat('id-ID');

const WARNA_STATUS: Record<string, string> = {
    Aktif: '#1aae39',
    Pindahan: '#0d9488',
    Cuti: '#ca8a04',
    Lulus: '#4f46e5',
    Nonaktif: '#a39e98',
    Dropout: '#dc2626',
    'Mengundurkan Diri': '#dd5b00',
    Meninggal: '#57534e',
};
const PALET_BATANG = ['#0075de', '#7c3aed', '#db2777', '#0d9488', '#dd5b00', '#4f46e5', '#ca8a04', '#1aae39'];

const totalStatus = computed(() => props.grafik.status.reduce((n, s) => n + s.jumlah, 0));

// Donat SVG: tiap status jadi busur lingkaran (keliling 100) yang disambung lewat stroke-dashoffset.
const busur = computed(() => {
    let mulai = 0;
    return props.grafik.status.map((s) => {
        const panjang = totalStatus.value ? (s.jumlah / totalStatus.value) * 100 : 0;
        const b = { ...s, warna: WARNA_STATUS[s.nama] ?? '#a39e98', panjang, offset: -mulai, persen: Math.round(panjang) };
        mulai += panjang;
        return b;
    });
});

const terbanyak = computed(() => Math.max(1, ...props.grafik.sebaran.data.map((d) => d.jumlah)));
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="kartu p-6">
            <h2 class="judul-bagian">Komposisi status mahasiswa</h2>
            <div v-if="totalStatus" class="mt-4 flex flex-col items-center gap-6 sm:flex-row">
                <div class="relative size-40 shrink-0">
                    <svg viewBox="0 0 42 42" class="size-full -rotate-90" role="img" aria-label="Grafik donat status mahasiswa">
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="6" class="stroke-[#f6f5f4] dark:stroke-muted" />
                        <circle
                            v-for="b in busur"
                            :key="b.nama"
                            cx="21"
                            cy="21"
                            r="15.915"
                            fill="none"
                            stroke-width="6"
                            :stroke="b.warna"
                            :stroke-dasharray="`${b.panjang} ${100 - b.panjang}`"
                            :stroke-dashoffset="b.offset"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ angka.format(totalStatus) }}</span>
                        <span class="teks-bantu">mahasiswa</span>
                    </div>
                </div>
                <ul class="flex w-full flex-col gap-2 text-sm">
                    <li v-for="b in busur" :key="b.nama" class="flex items-center gap-2">
                        <span class="size-3 shrink-0 rounded-full" :style="{ backgroundColor: b.warna }" />
                        <span class="flex-1 text-[#31302e] dark:text-foreground">{{ b.nama }}</span>
                        <span class="font-medium tabular-nums text-black dark:text-foreground">{{ angka.format(b.jumlah) }}</span>
                        <span class="teks-bantu w-10 text-right tabular-nums">{{ b.persen }}%</span>
                    </li>
                </ul>
            </div>
            <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada data mahasiswa.</p>
        </section>

        <section class="kartu p-6">
            <h2 class="judul-bagian">{{ props.grafik.sebaran.judul }}</h2>
            <ul v-if="props.grafik.sebaran.data.length" class="mt-4 flex flex-col gap-3">
                <li
                    v-for="(d, i) in props.grafik.sebaran.data"
                    :key="d.nama"
                    class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 text-sm"
                >
                    <span class="truncate text-[#31302e] dark:text-foreground" :title="d.nama">{{ d.nama }}</span>
                    <span class="h-4 overflow-hidden rounded-md bg-[#f6f5f4] dark:bg-muted">
                        <span
                            class="block h-full rounded-md"
                            :style="{ width: `${Math.round((d.jumlah / terbanyak) * 100)}%`, backgroundColor: PALET_BATANG[i % PALET_BATANG.length] }"
                        />
                    </span>
                    <span class="w-10 text-right font-medium tabular-nums text-black dark:text-foreground">{{ angka.format(d.jumlah) }}</span>
                </li>
            </ul>
            <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada data.</p>
        </section>
    </div>
</template>
