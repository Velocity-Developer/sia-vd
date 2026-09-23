<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Jadwal = {
    hari: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang?: { kode_ruang: string } | null;
};

type Item = {
    id: number;
    judul_materi?: string;
    judul_tugas?: string;
    nama_quiz?: string;
    pertemuan_ke?: number;
    tenggat_waktu?: string | null;
    catatan?: string | null | undefined;
};

type TahunAkademik = {
    tahun: string;
    semester: string;
};

type MataKuliah = {
    kode_matkul: string;
    nama_matkul: string;
    sks: number;
};

type Kelas = {
    id: number;
    kode_kelas: string;
    kapasitas: number;
    tahunAkademik?: TahunAkademik | null;
    tahun_akademik?: TahunAkademik | null;
    mataKuliah?: MataKuliah | null;
    mata_kuliah?: MataKuliah | null;
    dosen?: { user?: { name: string } | null } | null;
    jadwals?: Jadwal[];
    materis?: Item[];
    tugas?: Item[];
    quizzes?: Item[];
};

const props = defineProps<{ kelasKuliah: Kelas }>();
const mataKuliah = () => props.kelasKuliah.mataKuliah ?? props.kelasKuliah.mata_kuliah;
const tahunAkademik = () => props.kelasKuliah.tahunAkademik ?? props.kelasKuliah.tahun_akademik;
const v = (value: unknown) => (value === null || value === undefined || value === '' ? '-' : String(value));
const jam = (value: string) => value.slice(0, 5);
const formatTenggat = (value: string | null | undefined): string => {
    if (!value) return '-';

    const [date, time] = value.replace('T', ' ').split(' ');

    if (!date) return String(value);

    const [year, month, day] = date.split('-');
    const [hour = '00', minute = '00'] = (time ?? '').split(':');

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return `${day} ${months[Number(month) - 1]} ${year}, ${hour}:${minute}`;
};
</script>

<template>
    <Head :title="`Detail ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Jadwal Kuliah',
                href: route('mahasiswa.jadwal-kuliah'),
            },
            { title: 'Detail Kelas', href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-bold text-black">Detail Kelas Kuliah</h1>
                        <p class="text-sm text-[#615d59]">Informasi kelas dan materi perkuliahan.</p>
                    </div>
                    <Link
                        :href="route('mahasiswa.jadwal-kuliah')"
                        class="rounded-lg border border-[#e6e6e6] bg-white px-4 py-2 text-sm font-medium text-black"
                    >
                        Kembali
                    </Link>
                </div>
                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Kelas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs text-[#a39e98]">Kode Kelas</dt>
                            <dd class="font-medium">{{ v(props.kelasKuliah.kode_kelas) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Mata Kuliah</dt>
                            <dd class="font-medium">{{ v(mataKuliah()?.nama_matkul) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Dosen</dt>
                            <dd class="font-medium">{{ v(props.kelasKuliah.dosen?.user?.name) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Tahun Ajaran</dt>
                            <dd class="font-medium">
                                {{ tahunAkademik() ? `${tahunAkademik()?.tahun} ${tahunAkademik()?.semester}` : '-' }}
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jadwal</h2>
                    <div v-if="props.kelasKuliah.jadwals?.length" class="mt-4 grid gap-3">
                        <div
                            v-for="(item, index) in props.kelasKuliah.jadwals"
                            :key="index"
                            class="rounded-lg border border-[#e6e6e6] px-4 py-3 text-[15px] text-[#31302e] transition hover:border-[#0075de] hover:bg-[#f8fbff]"
                        >
                            <p class="font-medium">{{ item.hari }}</p>
                            <p class="mt-1 text-sm text-[#615d59]">
                                {{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }} · Ruang {{ item.ruang?.kode_ruang ?? '-' }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-[#615d59]">Belum ada jadwal.</p>
                </section>
                <section
                    v-for="section in [
                        { title: 'Materi', items: props.kelasKuliah.materis ?? [] },
                        { title: 'Tugas', items: props.kelasKuliah.tugas ?? [] },
                        { title: 'Quiz', items: props.kelasKuliah.quizzes ?? [] },
                    ]"
                    :key="section.title"
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                        {{ section.title }}
                    </h2>
                    <div v-if="section.items.length" class="mt-4 grid gap-3">
                        <div
                            v-for="item in section.items"
                            :key="item.id"
                            class="rounded-lg border border-[#e6e6e6] px-4 py-3 text-[15px] text-[#31302e] transition hover:border-[#0075de] hover:bg-[#f8fbff]"
                        >
                            <Link
                                v-if="section.title === 'Materi'"
                                :href="route('mahasiswa.materi.show', item.id)"
                                class="font-medium text-[#0075de] hover:underline"
                            >
                                {{ item.judul_materi }}
                            </Link>
                            <Link
                                v-else-if="section.title === 'Tugas'"
                                :href="route('mahasiswa.tugas.show', item.id)"
                                class="font-medium text-[#0075de] hover:underline"
                            >
                                {{ item.judul_tugas }}
                            </Link>
                            <Link v-else :href="route('mahasiswa.quiz.show', item.id)" class="font-medium text-[#0075de] hover:underline">
                                {{ item.nama_quiz }}
                            </Link>
                            <div
                                v-if="item.pertemuan_ke || item.tenggat_waktu"
                                class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-[#615d59]"
                            >
                                <span v-if="item.pertemuan_ke">Pertemuan {{ item.pertemuan_ke }}</span>
                                <span v-if="item.tenggat_waktu">Tenggat {{ formatTenggat(item.tenggat_waktu) }}</span>
                            </div>
                            <span v-if="item.catatan" class="block text-sm text-[#615d59]">
                                {{ item.catatan }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-[#615d59]">Belum ada {{ section.title.toLowerCase() }}.</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
