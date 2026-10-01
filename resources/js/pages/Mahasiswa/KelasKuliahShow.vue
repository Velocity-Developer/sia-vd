<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useFitur } from '@/composables/useFitur';
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

// Bagian konten hanya untuk fitur per klien yang aktif.
const fitur = useFitur();
const bagianKonten = [
    { title: 'Materi', fitur: 'materi' as const, items: props.kelasKuliah.materis ?? [] },
    { title: 'Tugas', fitur: 'tugas' as const, items: props.kelasKuliah.tugas ?? [] },
    { title: 'Quiz', fitur: 'quiz' as const, items: props.kelasKuliah.quizzes ?? [] },
].filter((b) => fitur.aktif(b.fitur));
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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Kelas Kuliah</h1>
                        <p class="deskripsi-halaman">Informasi kelas dan materi perkuliahan.</p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="route('mahasiswa.jadwal-kuliah')">Kembali</Link>
                    </Button>
                </div>
                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Kelas</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="teks-bantu">Kode Kelas</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ v(props.kelasKuliah.kode_kelas) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Mata Kuliah</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ v(mataKuliah()?.nama_matkul) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Dosen</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ v(props.kelasKuliah.dosen?.user?.name) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Tahun Ajaran</dt>
                            <dd class="font-medium text-black dark:text-foreground">
                                {{ tahunAkademik() ? `${tahunAkademik()?.tahun} ${tahunAkademik()?.semester}` : '-' }}
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="kartu p-6">
                    <h2 class="judul-bagian">Jadwal</h2>
                    <div v-if="props.kelasKuliah.jadwals?.length" class="mt-4 grid gap-3">
                        <div
                            v-for="(item, index) in props.kelasKuliah.jadwals"
                            :key="index"
                            class="rounded-lg border border-[#e6e6e6] px-4 py-3 text-sm text-[#31302e] transition hover:border-[#0075de] hover:bg-[#f8fbff] dark:border-border dark:text-foreground dark:hover:bg-accent/40"
                        >
                            <p class="font-medium">{{ item.hari }}</p>
                            <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                                {{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }} · Ruang {{ item.ruang?.kode_ruang ?? '-' }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada jadwal.</p>
                </section>
                <section v-for="section in bagianKonten" :key="section.title" class="kartu p-6">
                    <h2 class="judul-bagian">
                        {{ section.title }}
                    </h2>
                    <div v-if="section.items.length" class="mt-4 grid gap-3">
                        <div
                            v-for="item in section.items"
                            :key="item.id"
                            class="rounded-lg border border-[#e6e6e6] px-4 py-3 text-sm text-[#31302e] transition hover:border-[#0075de] hover:bg-[#f8fbff] dark:border-border dark:text-foreground dark:hover:bg-accent/40"
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
                                class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-[#615d59] dark:text-muted-foreground"
                            >
                                <span v-if="item.pertemuan_ke">Pertemuan {{ item.pertemuan_ke }}</span>
                                <span v-if="item.tenggat_waktu">Tenggat {{ formatTenggat(item.tenggat_waktu) }}</span>
                            </div>
                            <span v-if="item.catatan" class="block text-sm text-[#615d59] dark:text-muted-foreground">
                                {{ item.catatan }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada {{ section.title.toLowerCase() }}.</p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
