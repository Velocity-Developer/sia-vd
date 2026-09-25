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
};

const props = defineProps<{ ujians: Ujian[]; tahunAkademikId: number | null; tahunAkademikOptions: { id: number; name: string }[] }>();
const tahun = ref<number | string>(props.tahunAkademikId ?? '');
const gantiTahun = () => router.get(route('mahasiswa.ujian'), { tahun_akademik_id: tahun.value }, { preserveScroll: true });
const perJenis = computed(() =>
    (['uts', 'uas'] as const).map((j) => ({ jenis: j, ujians: props.ujians.filter((u) => u.jenis === j) })).filter((g) => g.ujians.length),
);
const urlKartu = (jenis: JenisUjian) => route('mahasiswa.ujian.kartu', { jenis, tahun_akademik_id: props.tahunAkademikId });
</script>

<template>
    <Head title="Jadwal Ujian" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Ujian', href: route('mahasiswa.ujian') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Jadwal Ujian</h1>
                        <p class="text-sm text-[#615d59]">UTS dan UAS mata kuliah di KRS Anda. Cetak kartu ujian dan bawa saat ujian tatap muka.</p>
                    </div>
                    <SelectFilter v-model="tahun" label="Tahun akademik" @change="gantiTahun">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                </div>

                <section v-for="g in perJenis" :key="g.jenis" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-black">{{ JENIS_UJIAN[g.jenis] }}</h2>
                        <a :href="urlKartu(g.jenis)">
                            <Button variant="outline" size="sm" class="bg-white"
                                ><Download class="mr-1 size-4" /> Kartu {{ JENIS_UJIAN[g.jenis] }} (PDF)</Button
                            >
                        </a>
                    </div>
                    <div v-for="u in g.ujians" :key="u.id" class="rounded-xl border border-[#e6e6e6] bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-medium text-black">{{ u.nama_matkul }}</p>
                                <p class="text-xs text-[#a39e98]">{{ u.kode_matkul }} · {{ u.kode_kelas }}</p>
                            </div>
                            <span v-if="u.syarat?.dispensasi" class="rounded bg-[#eaf3fd] px-2 py-0.5 text-xs text-[#0b62b5]">Dispensasi</span>
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
                                <dt class="text-xs text-[#a39e98]">Waktu</dt>
                                <dd class="text-[#31302e]">{{ formatTanggal(u.tanggal) }}, {{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#a39e98]">Mode</dt>
                                <dd class="text-[#31302e]">{{ u.label_mode }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#a39e98]">Tempat</dt>
                                <dd class="text-[#31302e]">{{ u.mode === 'tatap_muka' ? (u.ruang ?? '-') : 'Online di SIA' }}</dd>
                            </div>
                        </dl>
                        <p v-if="u.petunjuk" class="mt-3 whitespace-pre-line rounded-lg bg-[#f6f5f4] px-3 py-2 text-sm text-[#31302e]">
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

                <p
                    v-if="!props.ujians.length"
                    class="rounded-xl border border-dashed border-[#e6e6e6] bg-white px-4 py-10 text-center text-sm text-[#615d59]"
                >
                    Belum ada jadwal ujian yang diterbitkan untuk tahun akademik ini.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
