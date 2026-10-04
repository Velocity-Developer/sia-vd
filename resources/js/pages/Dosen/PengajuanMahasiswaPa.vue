<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { LABEL_LAMPIRAN, STATUS_PENGAJUAN, type StatusPengajuan } from '@/lib/tugasAkhir';
import { Head, router } from '@inertiajs/vue3';
import { Paperclip, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    jenis: string;
    ringkasan: string | null;
    keterangan: string | null;
    lampiran: string[];
    status: StatusPengajuan;
    catatan: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    diajukan_at: string | null;
};

const props = defineProps<{
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { jenis: string | null; status: string | null; search: string };
    jenisOptions: { id: string; name: string }[];
}>();

const semua = 'all';
const jenis = ref<string>(props.filter.jenis ?? semua);
const status = ref<string>(props.filter.status ?? semua);
const search = ref(props.filter.search);
let jeda: number | undefined;
const kirim = () =>
    router.get(
        route('dosen.pengajuan-pa.index'),
        { jenis: jenis.value === semua ? null : jenis.value, status: status.value === semua ? null : status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const terbuka = ref<number | null>(null);
</script>

<template>
    <Head title="Pengajuan Mahasiswa PA" />
    <AppLayout :breadcrumbs="[{ title: 'Pengajuan Mahasiswa PA', href: route('dosen.pengajuan-pa.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pengajuan Mahasiswa PA</h1>
                        <p class="deskripsi-halaman">
                            Pengajuan mahasiswa bimbingan akademik Anda (cuti, tugas akhir, KKM, PPL, ujian komprehensif, sidang, wisuda). Halaman ini
                            hanya untuk melihat; pengajuan diproses admin.
                        </p>
                    </div>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="jenis" label="Filter jenis" @change="kirim">
                        <option :value="semua">Semua jenis</option>
                        <option v-for="j in props.jenisOptions" :key="j.id" :value="j.id">{{ j.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="status" label="Filter status" @change="kirim">
                        <option :value="semua">Semua status</option>
                        <option v-for="(s, kunci) in STATUS_PENGAJUAN" :key="kunci" :value="kunci">{{ s.label }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Pengajuan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.pengajuan.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.pengajuan.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td class="max-w-[440px]">
                                        <span class="block font-medium text-black">{{ b.jenis }}</span>
                                        <span v-if="b.ringkasan" class="block text-sm">{{ b.ringkasan }}</span>
                                        <template v-if="b.keterangan">
                                            <button
                                                type="button"
                                                class="mt-1 text-xs font-medium text-[#0075de] hover:underline"
                                                @click="terbuka = terbuka === b.id ? null : b.id"
                                            >
                                                {{ terbuka === b.id ? 'Sembunyikan keterangan' : 'Lihat keterangan' }}
                                            </button>
                                            <span
                                                v-if="terbuka === b.id"
                                                class="mt-1 block whitespace-pre-line rounded-lg bg-[#f6f5f4] p-3 text-sm dark:bg-muted"
                                                >{{ b.keterangan }}</span
                                            >
                                        </template>
                                        <span class="mt-1 flex flex-wrap gap-3">
                                            <a
                                                v-for="k in b.lampiran"
                                                :key="k"
                                                :href="route('berkas.pengajuan-akademik', [b.id, k])"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> {{ LABEL_LAMPIRAN[k] ?? k }}</a
                                            >
                                        </span>
                                        <span v-if="b.diajukan_at" class="mt-1 block text-xs text-[#a39e98]"
                                            >Dikirim {{ formatTanggal(b.diajukan_at, false) }}</span
                                        >
                                    </td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_PENGAJUAN[b.status].kelas"
                                            >{{ STATUS_PENGAJUAN[b.status].label }}</span
                                        >
                                        <span v-if="b.catatan" class="mt-1 block max-w-[220px] text-xs text-[#615d59]">{{ b.catatan }}</span>
                                        <span v-if="b.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(b.diproses_at, false) }} · {{ b.diproses_oleh }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuan.data.length" class="baris-kosong">
                                    <td colspan="4" class="tabel-kosong">Belum ada pengajuan dari mahasiswa bimbingan akademik Anda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />
            </div>
        </div>
    </AppLayout>
</template>
