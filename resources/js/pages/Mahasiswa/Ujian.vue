<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Syarat = { persen: number | null; memenuhi: boolean | null; dispensasi: { alasan: string } | null } | null;
type Ujian = {
    id: number;
    jenis: JenisUjian;
    mode: ModeUjian;
    label_mode: string;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang: string | null;
    pengawas: string | null;
    petunjuk: string | null;
    kode_kelas: string;
    kode_matkul: string | null;
    nama_matkul: string | null;
    syarat: Syarat;
    terdaftar_susulan: boolean;
};

const props = defineProps<{ ujians: Ujian[]; tahunAkademikId: number | null; tahunAkademikOptions: { id: number; name: string }[] }>();
const tahun = ref<number | string>(props.tahunAkademikId ?? '');
const gantiTahun = () => router.get(route('mahasiswa.ujian'), { tahun_akademik_id: tahun.value }, { preserveScroll: true });
const perJenis = computed(() =>
    (['uts', 'uas', 'uts_susulan', 'uas_susulan', 'remidi'] as const)
        .map((j) => ({ jenis: j, ujians: props.ujians.filter((u) => u.jenis === j) }))
        .filter((g) => g.ujians.length),
);
const urlKartu = (jenis: JenisUjian) => route('mahasiswa.ujian.kartu', { jenis, tahun_akademik_id: props.tahunAkademikId });
</script>

<template>
    <Head title="Jadwal Ujian" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Ujian', href: route('mahasiswa.ujian') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Jadwal Ujian</h1>
                        <p class="deskripsi-halaman">
                            UTS, UAS, ujian susulan, dan remidi mata kuliah di KRS Anda. Cetak kartu ujian dan bawa saat ujian tatap muka.
                        </p>
                    </div>
                    <SelectFilter v-model="tahun" label="Tahun akademik" @change="gantiTahun">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                </div>

                <section v-for="g in perJenis" :key="g.jenis" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="judul-bagian">{{ JENIS_UJIAN[g.jenis] }}</h2>
                        <Button as-child variant="outline" size="sm">
                            <a :href="urlKartu(g.jenis)"><Download class="size-4" /> Kartu {{ JENIS_UJIAN[g.jenis] }} (PDF)</a>
                        </Button>
                    </div>
                    <div v-for="u in g.ujians" :key="u.id" class="kartu p-4 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-black dark:text-foreground">{{ u.nama_matkul }}</p>
                                <p class="teks-bantu">{{ u.kode_matkul }} · {{ u.kode_kelas }}</p>
                            </div>
                            <span v-if="u.terdaftar_susulan" class="rounded bg-[#fff6e0] px-2 py-0.5 text-xs text-[#8a5a00]"
                                >Ikut jadwal susulan</span
                            >
                            <span v-else-if="u.syarat?.dispensasi" class="rounded bg-[#eaf3fd] px-2 py-0.5 text-xs text-[#0b62b5]">Dispensasi</span>
                            <span
                                v-else-if="u.syarat?.memenuhi === false"
                                class="rounded bg-[#fdecea] px-2 py-0.5 text-xs font-medium text-[#b42318]"
                            >
                                Belum memenuhi syarat kehadiran ({{ u.syarat.persen }}%)
                            </span>
                            <span v-else-if="u.syarat?.memenuhi" class="rounded bg-[#e8f7ec] px-2 py-0.5 text-xs text-[#1a7f37]"
                                >Memenuhi syarat</span
                            >
                        </div>
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="teks-bantu">Waktu</dt>
                                <dd class="text-[#31302e] dark:text-foreground">
                                    {{ formatTanggal(u.tanggal) }}, {{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="teks-bantu">Mode</dt>
                                <dd class="text-[#31302e] dark:text-foreground">{{ u.label_mode }}</dd>
                            </div>
                            <div>
                                <dt class="teks-bantu">Tempat</dt>
                                <dd class="text-[#31302e] dark:text-foreground">
                                    {{ u.mode === 'tatap_muka' ? (u.ruang ?? '-') : 'Online di SIA' }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            v-if="u.petunjuk"
                            class="mt-3 whitespace-pre-line rounded-lg bg-[#f6f5f4] px-3 py-2 text-sm text-[#31302e] dark:bg-muted dark:text-foreground"
                        >
                            {{ u.petunjuk }}
                        </p>
                        <Link
                            :href="route('mahasiswa.ujian.show', u.id)"
                            class="mt-3 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >
                            {{ u.mode === 'tatap_muka' ? 'Lihat detail' : 'Buka ujian' }} →
                        </Link>
                    </div>
                </section>

                <div v-if="!props.ujians.length" class="kartu tabel-kosong">Belum ada jadwal ujian yang diterbitkan untuk tahun akademik ini.</div>
            </div>
        </div>
    </AppLayout>
</template>
