<script setup lang="ts">
import FilterKonten, { type NilaiFilter, type Opsi } from '@/components/FilterKonten.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type KelasRingkas = {
    id: number;
    kode_kelas: string;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    dosen?: { user?: { name: string } | null } | null;
    tahun_akademik?: { tahun: string; semester: string } | null;
};
type Materi = {
    id: number;
    judul_materi: string;
    pertemuan_ke: number;
    jenis: string;
    file: string[] | string | null;
    created_at: string;
    uploader?: { name: string } | null;
    kelas_kuliah?: KelasRingkas | null;
};

const props = defineProps<{
    materis: { data: Materi[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    jenis: string;
    peran: Peran;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
}>();

const page = usePage<{ flash?: { materi_success?: string; materi_error?: string } }>();
const rute = rutePeran(props.peran);
const filterRef = ref<InstanceType<typeof FilterKonten> | null>(null);
const jenis = ref(props.jenis || 'all');

watch(
    () => props.jenis,
    (baru) => (jenis.value = baru || 'all'),
);

const jumlahBerkas = (file: Materi['file']): number => {
    if (Array.isArray(file)) return file.filter((item) => typeof item === 'string' && item !== '').length;

    return file ? 1 : 0;
};

const tanggal = (value: string) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value));
</script>

<template>
    <Head title="Materi" />
    <AppLayout :breadcrumbs="[{ title: 'Materi', href: rute('materi.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Materi</h1>
                        <p class="deskripsi-halaman">Materi dan pengumuman dari seluruh kelas. Materi baru ditambahkan dari halaman kelas.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.materi_success" class="alert-sukses" role="alert">{{ page.props.flash.materi_success }}</div>

                <FilterKonten
                    ref="filterRef"
                    :url="rute('materi.index')"
                    :filter="props.filter"
                    :tahun-akademik-options="props.tahunAkademikOptions"
                    :prodi-options="props.prodiOptions"
                    :mata-kuliah-options="props.mataKuliahOptions"
                    :kelas-options="props.kelasOptions"
                    :dosen-options="props.dosenOptions"
                    :total="props.materis.total"
                    placeholder="Cari judul materi atau kode kelas"
                    :tambahan="{ jenis: jenis === 'all' ? null : jenis }"
                >
                    <template #tambahan>
                        <SelectFilter v-model="jenis" label="Filter jenis" @change="filterRef?.kirim()">
                            <option value="all">Semua Jenis</option>
                            <option value="Materi">Materi</option>
                            <option value="Pengumuman">Pengumuman</option>
                        </SelectFilter>
                    </template>
                </FilterKonten>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Judul</th>
                                    <th>Kelas</th>
                                    <th>Mata Kuliah</th>
                                    <th class="text-center">Berkas</th>
                                    <th>Diunggah</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.materis.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.materis.from ?? 1) + index }}</td>
                                    <td class="text-black">
                                        <span class="block font-medium">{{ item.judul_materi }}</span>
                                        <span class="block text-xs text-[#a39e98]">Pertemuan {{ item.pertemuan_ke }} · {{ item.jenis }}</span>
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
                                    <td class="text-center tabular-nums">{{ jumlahBerkas(item.file) }}</td>
                                    <td>
                                        <span class="block">{{ tanggal(item.created_at) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.uploader?.name }}</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Link
                                                :href="rute('kelas-kuliah.show', item.kelas_kuliah?.id ?? 0)"
                                                class="text-sm font-medium text-[#0075de] hover:underline"
                                            >
                                                Buka kelas
                                            </Link>
                                            <Button v-if="item.kelas_kuliah" as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link
                                                    :href="rute('kelas-kuliah.materi.edit', [item.kelas_kuliah.id, item.id])"
                                                    title="Edit"
                                                    aria-label="Edit materi"
                                                    ><Pencil
                                                /></Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.materis.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Tidak ada materi yang cocok dengan filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.materis.links" :total="props.materis.total" />
            </div>
        </div>
    </AppLayout>
</template>
