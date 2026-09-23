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
type Tugas = {
    id: number;
    judul_tugas: string;
    tenggat_waktu: string | null;
    pengumpulan_tugas_count: number;
    uploader?: { name: string } | null;
    kelas_kuliah?: KelasRingkas | null;
};

const props = defineProps<{
    tugas: { data: Tugas[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    peran: Peran;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
}>();

const page = usePage<{ flash?: { tugas_success?: string; tugas_error?: string } }>();
const rute = rutePeran(props.peran);

const tenggat = (value: string | null) =>
    value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Tanpa tenggat';
const lewat = (value: string | null) => value !== null && new Date(value).getTime() < Date.now();
</script>

<template>
    <Head title="Tugas" />
    <AppLayout :breadcrumbs="[{ title: 'Tugas', href: rute('tugas.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Tugas</h1>
                    <p class="text-sm text-[#615d59]">Tugas dari seluruh kelas beserta jumlah jawaban yang sudah masuk.</p>
                </div>

                <div v-if="page.props.flash?.tugas_success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.tugas_success }}
                </div>

                <FilterKonten
                    :url="rute('tugas.index')"
                    :filter="props.filter"
                    :tahun-akademik-options="props.tahunAkademikOptions"
                    :prodi-options="props.prodiOptions"
                    :mata-kuliah-options="props.mataKuliahOptions"
                    :kelas-options="props.kelasOptions"
                    :dosen-options="props.dosenOptions"
                    :total="props.tugas.total"
                    placeholder="Cari judul tugas atau kode kelas"
                />

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Judul</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tenggat</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jawaban</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.tugas.data" :key="item.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-[#615d59]">{{ (props.tugas.from ?? 0) + index }}</td>
                                    <td class="px-4 py-3 text-[15px] font-medium text-black">{{ item.judul_tugas }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block font-medium">{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.kelas_kuliah?.tahun_akademik?.tahun }} {{ item.kelas_kuliah?.tahun_akademik?.semester }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.kelas_kuliah?.dosen?.user?.name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px]" :class="lewat(item.tenggat_waktu) ? 'text-[#dd5b00]' : 'text-[#31302e]'">
                                        {{ tenggat(item.tenggat_waktu) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-[15px] font-semibold text-black">
                                        {{ item.pengumpulan_tugas_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            v-if="item.kelas_kuliah"
                                            :href="rute('kelas-kuliah.tugas.show', [item.kelas_kuliah.id, item.id])"
                                            class="whitespace-nowrap text-sm font-medium text-[#0075de] hover:underline"
                                        >
                                            Detail &amp; nilai
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!props.tugas.data.length">
                                    <td colspan="7" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tidak ada tugas yang cocok dengan filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.tugas.links" :total="props.tugas.total" />
            </div>
        </div>
    </AppLayout>
</template>
