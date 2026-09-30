<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import FilterKonten, { type NilaiFilter, type Opsi } from '@/components/FilterKonten.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type KelasRingkas = {
    id: number;
    kode_kelas: string;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    dosen?: { user?: { name: string } | null } | null;
    tahun_akademik?: { tahun: string; semester: string; status?: boolean } | null;
};
type Jadwal = {
    id: number;
    hari: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    kelas_kuliah?: KelasRingkas | null;
};

const props = defineProps<{
    jadwals: { data: Jadwal[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    peran: Peran;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
}>();

const page = usePage<{ flash?: { jadwal_success?: string; jadwal_error?: string } }>();
const rute = rutePeran(props.peran);
const isAdmin = computed(() => props.peran === 'admin');
const jam = (value: string) => value.slice(0, 5);
// Dosen hanya bisa mengubah jadwal kelas di tahun akademik aktif; admin selalu bisa.
const bolehUbah = (jadwal: Jadwal) => isAdmin.value || jadwal.kelas_kuliah?.tahun_akademik?.status === true;

const pendingHapus = ref<Jadwal | null>(null);
const hapus = () => {
    const jadwal = pendingHapus.value;
    if (!jadwal?.kelas_kuliah) return;

    router.delete(rute('kelas-kuliah.jadwal.destroy', [jadwal.kelas_kuliah.id, jadwal.id]) + '?dari=menu', {
        preserveScroll: true,
        onFinish: () => (pendingHapus.value = null),
    });
};
</script>

<template>
    <Head title="Jadwal Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Kelas', href: rute('jadwal.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ isAdmin ? 'Jadwal Kelas' : 'Jadwal Mengajar' }}</h1>
                        <p class="deskripsi-halaman">
                            {{
                                isAdmin
                                    ? 'Seluruh jadwal mingguan kelas kuliah.'
                                    : 'Jadwal mengajar dari kelas yang Anda ampu. Jadwal di tahun akademik aktif bisa Anda ubah.'
                            }}
                        </p>
                    </div>
                    <Button as-child>
                        <Link :href="rute('jadwal.create')"><Plus />Tambah Jadwal</Link>
                    </Button>
                </div>

                <div v-if="page.props.flash?.jadwal_success" class="alert-sukses" role="alert">{{ page.props.flash.jadwal_success }}</div>
                <div v-if="page.props.flash?.jadwal_error" class="alert-gagal" role="alert">{{ page.props.flash.jadwal_error }}</div>

                <FilterKonten
                    :url="rute('jadwal.index')"
                    :filter="props.filter"
                    :tahun-akademik-options="props.tahunAkademikOptions"
                    :prodi-options="props.prodiOptions"
                    :mata-kuliah-options="props.mataKuliahOptions"
                    :kelas-options="props.kelasOptions"
                    :dosen-options="props.dosenOptions"
                    :total="props.jadwals.total"
                    placeholder="Cari hari, ruang, atau kode kelas"
                />

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Hari &amp; Jam</th>
                                    <th>Kelas</th>
                                    <th>Mata Kuliah</th>
                                    <th v-if="isAdmin">Dosen</th>
                                    <th>Ruang</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.jadwals.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.jadwals.from ?? 1) + index }}</td>
                                    <td class="text-black">
                                        <span class="block font-medium">{{ item.hari }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }}</span>
                                    </td>
                                    <td>
                                        <span class="block font-medium">{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.kelas_kuliah?.tahun_akademik?.tahun }} {{ item.kelas_kuliah?.tahun_akademik?.semester }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="block">{{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.kelas_kuliah?.mata_kuliah?.kode_matkul }}</span>
                                    </td>
                                    <td v-if="isAdmin">{{ item.kelas_kuliah?.dosen?.user?.name ?? '-' }}</td>
                                    <td>
                                        <span class="block">{{ item.ruang?.kode_ruang ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.ruang?.nama_ruang }}</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Link
                                                :href="rute('kelas-kuliah.show', item.kelas_kuliah?.id ?? 0)"
                                                class="text-sm font-medium text-[#0075de] hover:underline"
                                            >
                                                Buka kelas
                                            </Link>
                                            <template v-if="item.kelas_kuliah && bolehUbah(item)">
                                                <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                    <Link
                                                        :href="rute('kelas-kuliah.jadwal.edit', [item.kelas_kuliah.id, item.id]) + '?dari=menu'"
                                                        title="Edit"
                                                        aria-label="Edit jadwal"
                                                        ><Pencil
                                                    /></Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="icon-sm"
                                                    class="text-[#dd5b00]"
                                                    title="Hapus"
                                                    aria-label="Hapus jadwal"
                                                    @click="pendingHapus = item"
                                                    ><Trash2
                                                /></Button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.jadwals.data.length" class="baris-kosong">
                                    <td :colspan="isAdmin ? 7 : 6" class="tabel-kosong">Tidak ada jadwal yang cocok dengan filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.jadwals.links" :total="props.jadwals.total" />

                <AlertModal
                    :open="pendingHapus !== null"
                    description="Hapus jadwal ini?"
                    confirm-text="Hapus"
                    cancel-text="Batal"
                    @update:open="!$event && (pendingHapus = null)"
                    @confirm="hapus"
                    @cancel="pendingHapus = null"
                />
            </div>
        </div>
    </AppLayout>
</template>
