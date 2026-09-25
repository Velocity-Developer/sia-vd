<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, STATUS_UJIAN, labelMode, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Ujian = {
    id: number;
    jenis: JenisUjian;
    mode: ModeUjian;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    status: 'draf' | 'terbit';
    pengawas: string | null;
    petunjuk: string | null;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    kelas_kuliah?: { kode_kelas: string; krs_count: number; mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null } | null;
};

const props = defineProps<{ ujians: Ujian[]; tahunAkademikId: number | null; tahunAkademikOptions: { id: number; name: string }[] }>();
const tahun = ref<number | string>(props.tahunAkademikId ?? '');
const gantiTahun = () => router.get(route('dosen.ujian.index'), { tahun_akademik_id: tahun.value }, { preserveScroll: true });
</script>

<template>
    <Head title="Ujian" />
    <AppLayout :breadcrumbs="[{ title: 'Ujian', href: route('dosen.ujian.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Ujian</h1>
                        <p class="max-w-2xl text-sm text-[#615d59]">
                            Jadwal UTS/UAS kelas yang Anda ampu. Jadwal dan mode ujian ditentukan bagian akademik; jadwal berstatus draf belum
                            terlihat oleh mahasiswa.
                        </p>
                    </div>
                    <SelectFilter v-model="tahun" label="Tahun akademik" @change="gantiTahun">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                </div>

                <div v-for="u in props.ujians" :key="u.id" class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-black">{{ u.kelas_kuliah?.mata_kuliah?.nama_matkul }}</p>
                            <p class="text-xs text-[#a39e98]">{{ u.kelas_kuliah?.kode_kelas }} · {{ u.kelas_kuliah?.krs_count }} mahasiswa</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-[#fff6e0] px-2 py-0.5 text-xs font-semibold text-[#8a5a00]">{{ JENIS_UJIAN[u.jenis] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_UJIAN[u.status].kelas">{{
                                STATUS_UJIAN[u.status].label
                            }}</span>
                        </div>
                    </div>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-[#a39e98]">Waktu</dt>
                            <dd class="text-[#31302e]">{{ formatTanggal(u.tanggal) }}, {{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Mode</dt>
                            <dd class="text-[#31302e]">{{ labelMode(u.mode) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Tempat</dt>
                            <dd class="text-[#31302e]">
                                {{ u.mode === 'tatap_muka' ? (u.ruang ? `${u.ruang.kode_ruang} — ${u.ruang.nama_ruang}` : '-') : 'Online di SIA' }}
                                <span v-if="u.pengawas" class="block text-xs text-[#a39e98]">Pengawas: {{ u.pengawas }}</span>
                            </dd>
                        </div>
                    </dl>
                    <p v-if="u.petunjuk" class="mt-3 whitespace-pre-line rounded-lg bg-[#f6f5f4] px-3 py-2 text-sm text-[#31302e]">
                        {{ u.petunjuk }}
                    </p>
                    <Link :href="route('dosen.ujian.show', u.id)" class="mt-3 inline-block text-sm font-medium text-[#0075de] hover:underline">
                        {{ u.mode === 'online_berkas' ? 'Siapkan soal & lihat jawaban' : 'Buka detail' }} →
                    </Link>
                </div>

                <p
                    v-if="!props.ujians.length"
                    class="rounded-xl border border-dashed border-[#e6e6e6] bg-white px-4 py-10 text-center text-sm text-[#615d59]"
                >
                    Belum ada jadwal ujian untuk kelas Anda di tahun akademik ini.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
