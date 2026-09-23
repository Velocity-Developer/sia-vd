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
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Quiz</h1>
                    <p class="text-sm text-[#615d59]">Quiz dari seluruh kelas beserta jumlah soal dan mahasiswa yang mengerjakan.</p>
                </div>

                <div v-if="page.props.flash?.quiz_success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.quiz_success }}
                </div>

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

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="tabel-responsif w-full text-left md:min-w-[960px]">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama Quiz</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tenggat</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Soal</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Dikerjakan</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.quizzes.data" :key="item.id" class="hover:bg-[#f6f5f4]/60">
                                    <td data-label="No." class="px-4 py-3 text-[15px] text-[#615d59]">{{ (props.quizzes.from ?? 0) + index }}</td>
                                    <td data-label="Nama Quiz" class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ item.nama_quiz }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.waktu_pengerjaan ? `${item.waktu_pengerjaan} menit` : 'Tanpa batas waktu' }}
                                        </span>
                                    </td>
                                    <td data-label="Kelas" class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block font-medium">{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.kelas_kuliah?.tahun_akademik?.tahun }} {{ item.kelas_kuliah?.tahun_akademik?.semester }}
                                        </span>
                                    </td>
                                    <td data-label="Mata Kuliah" class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.kelas_kuliah?.dosen?.user?.name }}</span>
                                    </td>
                                    <td
                                        data-label="Tenggat"
                                        class="px-4 py-3 text-[15px]"
                                        :class="lewat(item.tenggat_waktu) ? 'text-[#dd5b00]' : 'text-[#31302e]'"
                                    >
                                        {{ tenggat(item.tenggat_waktu) }}
                                    </td>
                                    <td data-label="Soal" class="px-4 py-3 text-center text-[15px] text-[#31302e]">{{ item.questions_count }}</td>
                                    <td data-label="Dikerjakan" class="px-4 py-3 text-center text-[15px] font-semibold text-black">
                                        {{ item.attempts_count }}
                                    </td>
                                    <td data-label="Aksi" class="px-4 py-3 text-right">
                                        <Link
                                            v-if="item.kelas_kuliah"
                                            :href="rute('kelas-kuliah.quiz.show', [item.kelas_kuliah.id, item.id])"
                                            class="whitespace-nowrap text-sm font-medium text-[#0075de] hover:underline"
                                        >
                                            Soal &amp; hasil
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!props.quizzes.data.length">
                                    <td colspan="8" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tidak ada quiz yang cocok dengan filter.
                                    </td>
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
