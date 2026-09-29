<script setup lang="ts">
import FilterKonten, { type NilaiFilter, type Opsi } from '@/components/FilterKonten.vue';
import Pagination from '@/components/Pagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, usePage } from '@inertiajs/vue3';

type KelasRingkas = {
    id: number;
    kode_kelas: string;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    dosen?: { user?: { name: string } | null } | null;
    tahun_akademik?: { tahun: string; semester: string } | null;
};
type Quiz = {
    id: number;
    nama_quiz: string;
    waktu_pengerjaan: number | null;
    tenggat_waktu: string | null;
    questions_count: number;
    attempts_count: number;
    uploader?: { name: string } | null;
    kelas_kuliah?: KelasRingkas | null;
};

const props = defineProps<{
    quizzes: { data: Quiz[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    peran: Peran;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
}>();

const page = usePage<{ flash?: { quiz_success?: string; quiz_error?: string } }>();
const rute = rutePeran(props.peran);

const tenggat = (value: string | null) =>
    value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Tanpa tenggat';
const lewat = (value: string | null) => value !== null && new Date(value).getTime() < Date.now();
</script>

<template>
    <Head title="Quiz" />
    <AppLayout :breadcrumbs="[{ title: 'Quiz', href: rute('quiz.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Quiz</h1>
                        <p class="deskripsi-halaman">Quiz dari seluruh kelas beserta jumlah soal dan mahasiswa yang mengerjakan.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.quiz_success" class="alert-sukses" role="alert">{{ page.props.flash.quiz_success }}</div>

                <FilterKonten
                    :url="rute('quiz.index')"
                    :filter="props.filter"
                    :tahun-akademik-options="props.tahunAkademikOptions"
                    :prodi-options="props.prodiOptions"
                    :mata-kuliah-options="props.mataKuliahOptions"
                    :kelas-options="props.kelasOptions"
                    :dosen-options="props.dosenOptions"
                    :total="props.quizzes.total"
                    placeholder="Cari nama quiz atau kode kelas"
                />

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[960px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Nama Quiz</th>
                                    <th>Kelas</th>
                                    <th>Mata Kuliah</th>
                                    <th>Tenggat</th>
                                    <th class="text-center">Soal</th>
                                    <th class="text-center">Dikerjakan</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.quizzes.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.quizzes.from ?? 1) + index }}</td>
                                    <td class="text-black">
                                        <span class="block font-medium">{{ item.nama_quiz }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.waktu_pengerjaan ? `${item.waktu_pengerjaan} menit` : 'Tanpa batas waktu' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="block font-medium">{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.kelas_kuliah?.tahun_akademik?.tahun }} {{ item.kelas_kuliah?.tahun_akademik?.semester }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="block">{{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.kelas_kuliah?.dosen?.user?.name }}</span>
                                    </td>
                                    <td :class="{ 'text-[#dd5b00]': lewat(item.tenggat_waktu) }">
                                        {{ tenggat(item.tenggat_waktu) }}
                                    </td>
                                    <td class="text-center tabular-nums">{{ item.questions_count }}</td>
                                    <td class="text-center font-semibold tabular-nums text-black">
                                        {{ item.attempts_count }}
                                    </td>
                                    <td class="kolom-aksi">
                                        <Link
                                            v-if="item.kelas_kuliah"
                                            :href="rute('kelas-kuliah.quiz.show', [item.kelas_kuliah.id, item.id])"
                                            class="whitespace-nowrap text-sm font-medium text-[#0075de] hover:underline"
                                        >
                                            Soal &amp; hasil
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!props.quizzes.data.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Tidak ada quiz yang cocok dengan filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.quizzes.links" :total="props.quizzes.total" />
            </div>
        </div>
    </AppLayout>
</template>
