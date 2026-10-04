<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Opsi = { id: number; name: string };
type Kelas = { id: number; kode_kelas: string; mata_kuliah: string; dosen: string | null; ketua_kelas_id: number | null; peserta: Opsi[] };

const props = defineProps<{
    kelas: { data: Kelas[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; search: string };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const page = usePage<{ flash: { success?: string; error?: string }; errors: Record<string, string> }>();

const tahunAkademikId = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const prodiId = ref<number | string>(props.filter.prodi_id ?? '');
const search = ref(props.filter.search);
const saring = () =>
    router.get(
        route('admin.ketua-kelas.index'),
        { tahun_akademik_id: tahunAkademikId.value, prodi_id: prodiId.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, saring);

const menyimpan = ref<number | null>(null);
const simpan = (item: Kelas, nilai: string) => {
    menyimpan.value = item.id;
    router.put(
        route('admin.ketua-kelas.update', item.id),
        { ketua_kelas_id: nilai === '' ? null : Number(nilai) },
        { preserveScroll: true, onFinish: () => (menyimpan.value = null) },
    );
};
</script>

<template>
    <Head title="Ketua Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Ketua Kelas', href: route('admin.ketua-kelas.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Ketua Kelas</h1>
                        <p class="deskripsi-halaman">
                            Tetapkan satu mahasiswa peserta sebagai ketua di tiap kelas kuliah. Pilihan langsung tersimpan.
                        </p>
                    </div>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode kelas atau mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="saring">
                        <option value="">Semua Tahun Akademik</option>
                        <option v-for="ta in props.tahunAkademikOptions" :key="ta.id" :value="ta.id">{{ ta.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-if="props.prodiOptions.length > 1" v-model="prodiId" label="Filter program studi" @change="saring">
                        <option value="">Semua Program Studi</option>
                        <option v-for="prodi in props.prodiOptions" :key="prodi.id" :value="prodi.id">{{ prodi.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.kelas.total }}</span> kelas
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>
                <div v-if="page.props.errors?.ketua_kelas_id" class="alert-gagal" role="alert">{{ page.props.errors.ketua_kelas_id }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kelas</th>
                                    <th>Mata Kuliah</th>
                                    <th>Dosen</th>
                                    <th class="text-center">Peserta</th>
                                    <th class="w-72">Ketua Kelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.kelas.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.kelas.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode_kelas }}</td>
                                    <td>{{ item.mata_kuliah }}</td>
                                    <td>{{ item.dosen ?? '-' }}</td>
                                    <td class="text-center">{{ item.peserta.length }}</td>
                                    <td>
                                        <select
                                            :value="item.ketua_kelas_id ?? ''"
                                            class="isian isian-pilih"
                                            :aria-label="`Ketua kelas ${item.kode_kelas}`"
                                            :disabled="menyimpan === item.id || !item.peserta.length"
                                            @change="simpan(item, ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">{{ item.peserta.length ? 'Belum ditetapkan' : 'Belum ada peserta' }}</option>
                                            <option v-for="mhs in item.peserta" :key="mhs.id" :value="mhs.id">{{ mhs.name }}</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr v-if="!props.kelas.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Tidak ada kelas kuliah pada filter ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.kelas.links" :total="props.kelas.total" />
            </div>
        </div>
    </AppLayout>
</template>
