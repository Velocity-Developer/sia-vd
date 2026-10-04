<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import TabPresensiDosen from '@/components/TabPresensiDosen.vue';
import TimePicker from '@/components/TimePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, infoVerifikasi, JENIS_PERTEMUAN, kelasStatusDosen, type JenisPertemuan, type VerifikasiPresensi } from '@/lib/presensi';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Pencil, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Opsi = { id: number | string; name: string };
type Riwayat = {
    id: number;
    aksi: string;
    perubahan: Record<string, [string | null, string | null]>;
    alasan: string | null;
    oleh: string | null;
    waktu: string | null;
};
type Baris = {
    id: number;
    pertemuan_ke: number;
    jenis: JenisPertemuan;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    status: 'dijadwalkan' | 'berlangsung' | 'selesai';
    terlewat: boolean;
    kelas: { id: number; kode: string; mata_kuliah: string | null; prodi: string | null };
    pengampu: { id: number; nama: string | null };
    pengajar: { id: number | null; nama: string | null };
    status_dosen: string | null;
    status_dosen_label: string;
    jam_masuk: string | null;
    jam_keluar: string | null;
    menit_terlambat: number | null;
    topik: string | null;
    verifikasi: VerifikasiPresensi;
    catatan_verifikasi: string | null;
    riwayat: Riwayat[];
};
type Filter = {
    tahun_akademik_id: number | null;
    prodi_id: number | null;
    dosen_id: number | null;
    search: string;
    status: string | null;
    verifikasi: string | null;
    dari: string | null;
    sampai: string | null;
};

const props = defineProps<{
    pertemuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: Filter;
    toleransi: number;
    statusDosen: Opsi[];
    dosenOptions: { id: number; name: string }[];
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    dosenFilterOptions: Opsi[];
}>();
// Akun Prodi tidak mengisi presensi dosen (hanya Admin); rekap & BAP tetap terbuka.
const { prodi } = usePermissions();

const page = usePage<{ flash: { success?: string; error?: string } }>();

const search = ref(props.filter.search);
const saring = (perubahan: Partial<Filter>) =>
    router.get(
        route('admin.presensi-dosen.index'),
        Object.fromEntries(Object.entries({ ...props.filter, ...perubahan }).filter(([, nilai]) => nilai !== null && nilai !== '')),
        { preserveState: true, preserveScroll: true },
    );
let tunda: ReturnType<typeof setTimeout> | undefined;
watch(search, (nilai) => {
    clearTimeout(tunda);
    tunda = setTimeout(() => saring({ search: nilai }), 350);
});

// Koreksi presensi dosen
const dipilih = ref<Baris | null>(null);
const form = useForm({ status_dosen: 'hadir', dosen_id: null as number | null, jam_masuk: '', jam_keluar: '', topik: '', alasan: '' });
const hadir = computed(() => ['hadir', 'digantikan'].includes(form.status_dosen));

const bukaKoreksi = (b: Baris) => {
    dipilih.value = b;
    form.clearErrors();
    form.status_dosen = b.status_dosen ?? 'hadir';
    form.dosen_id = b.pengajar.id ?? b.pengampu.id;
    form.jam_masuk = b.jam_masuk ?? b.jam_mulai;
    form.jam_keluar = b.jam_keluar ?? b.jam_akhir;
    form.topik = b.topik ?? '';
    form.alasan = '';
};
// Hadir = dosen pengampu; Digantikan = pilih dosen lain.
watch(
    () => form.status_dosen,
    (status) => {
        if (status === 'hadir' && dipilih.value) form.dosen_id = dipilih.value.pengampu.id;
    },
);
const simpan = () => {
    if (!dipilih.value) return;
    form.put(route('admin.presensi-dosen.update', dipilih.value.id), { preserveScroll: true, onSuccess: () => (dipilih.value = null) });
};

const bisaKoreksi = (b: Baris) => b.verifikasi !== 'disetujui' && b.status !== 'berlangsung';
</script>

<template>
    <Head title="Presensi Dosen" />
    <AppLayout :breadcrumbs="[{ title: 'Presensi Dosen', href: route('admin.presensi-dosen.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Presensi Dosen</h1>
                        <p class="deskripsi-halaman">
                            Diambil dari pertemuan yang dibuka dan ditutup dosen. Koreksi wajib beralasan dan tercatat di riwayat; pertemuan yang
                            sudah diverifikasi terkunci.
                        </p>
                    </div>
                </div>

                <TabPresensiDosen aktif="pertemuan" />

                <div class="bilah-filter flex-wrap">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kelas atau mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter
                        :model-value="props.filter.tahun_akademik_id ?? ''"
                        label="Tahun akademik"
                        @update:model-value="(v) => saring({ tahun_akademik_id: Number(v) })"
                    >
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <SelectFilter
                        :model-value="props.filter.prodi_id ?? ''"
                        label="Program studi"
                        @update:model-value="(v) => saring({ prodi_id: v ? Number(v) : null })"
                    >
                        <option value="">Semua program studi</option>
                        <option v-for="p in props.prodiOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectFilter>
                    <SelectFilter
                        :model-value="props.filter.dosen_id ?? ''"
                        label="Dosen"
                        @update:model-value="(v) => saring({ dosen_id: v ? Number(v) : null })"
                    >
                        <option value="">Semua dosen</option>
                        <option v-for="d in props.dosenFilterOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </SelectFilter>
                    <SelectFilter
                        :model-value="props.filter.status ?? ''"
                        label="Status pertemuan"
                        @update:model-value="(v) => saring({ status: (v as string) || null })"
                    >
                        <option value="">Semua status</option>
                        <option value="terlaksana">Terlaksana</option>
                        <option value="tidak_terlaksana">Tidak terlaksana</option>
                        <option value="terlewat">Terlewat (belum ada keterangan)</option>
                    </SelectFilter>
                    <SelectFilter
                        :model-value="props.filter.verifikasi ?? ''"
                        label="Verifikasi"
                        @update:model-value="(v) => saring({ verifikasi: (v as string) || null })"
                    >
                        <option value="">Semua verifikasi</option>
                        <option value="menunggu">Menunggu verifikasi</option>
                        <option value="disetujui">Terverifikasi</option>
                        <option value="ditolak">Ditolak</option>
                    </SelectFilter>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="teks-bantu">Dari</span>
                        <input
                            type="date"
                            class="isian w-auto"
                            :value="props.filter.dari ?? ''"
                            @change="saring({ dari: ($event.target as HTMLInputElement).value || null })"
                        />
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="teks-bantu">s.d.</span>
                        <input
                            type="date"
                            class="isian w-auto"
                            :value="props.filter.sampai ?? ''"
                            @change="saring({ sampai: ($event.target as HTMLInputElement).value || null })"
                        />
                    </label>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.pertemuan.total }}</span> pertemuan
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1300px]">
                            <thead>
                                <tr>
                                    <th>Tanggal &amp; Jadwal</th>
                                    <th>Kelas</th>
                                    <th>Dosen</th>
                                    <th>Kehadiran</th>
                                    <th>Masuk–Keluar</th>
                                    <th>Topik</th>
                                    <th>Verifikasi</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in props.pertemuan.data" :key="b.id">
                                    <td class="whitespace-nowrap">
                                        <p class="font-medium text-black dark:text-foreground">{{ formatTanggal(b.tanggal) }}</p>
                                        <p class="teks-bantu">{{ b.jam_mulai }}–{{ b.jam_akhir }}</p>
                                    </td>
                                    <td>
                                        <Link :href="route('admin.presensi.pertemuan.show', b.id)" class="text-[#0075de] hover:underline">
                                            {{ b.kelas.kode }} · P{{ b.pertemuan_ke }}{{ b.jenis !== 'kuliah' ? ` ${JENIS_PERTEMUAN[b.jenis]}` : '' }}
                                        </Link>
                                        <p class="teks-bantu">{{ b.kelas.mata_kuliah }}</p>
                                    </td>
                                    <td>
                                        <p>{{ b.pengajar.nama ?? b.pengampu.nama ?? '-' }}</p>
                                        <p v-if="b.status_dosen === 'digantikan'" class="teks-bantu">Pengampu: {{ b.pengampu.nama }}</p>
                                    </td>
                                    <td>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="kelasStatusDosen(b.status_dosen)">{{
                                            b.status_dosen_label
                                        }}</span>
                                    </td>
                                    <td class="whitespace-nowrap tabular-nums">
                                        {{ b.jam_masuk || b.jam_keluar ? `${b.jam_masuk ?? '-'}–${b.jam_keluar ?? '-'}` : '-' }}
                                        <p v-if="b.menit_terlambat" class="text-xs font-medium text-[#dd5b00]">
                                            Terlambat {{ b.menit_terlambat }} mnt
                                        </p>
                                    </td>
                                    <td class="max-w-[260px]">
                                        <p class="line-clamp-2" :title="b.topik ?? ''">{{ b.topik || '-' }}</p>
                                        <p v-if="b.riwayat.length" class="teks-bantu">
                                            Dikoreksi {{ b.riwayat.filter((r) => r.aksi === 'Koreksi').length }}×
                                        </p>
                                    </td>
                                    <td>
                                        <span
                                            v-if="b.status === 'selesai'"
                                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="infoVerifikasi(b.verifikasi).kelas"
                                        >
                                            {{ infoVerifikasi(b.verifikasi).label }}
                                        </span>
                                        <span v-else class="teks-bantu">-</span>
                                        <p v-if="b.verifikasi === 'ditolak' && b.catatan_verifikasi" class="teks-bantu mt-1">
                                            {{ b.catatan_verifikasi }}
                                        </p>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button v-if="!prodi && bisaKoreksi(b)" size="sm" variant="outline" @click="bukaKoreksi(b)"
                                                ><Pencil /> Koreksi</Button
                                            >
                                            <Button v-if="b.status === 'selesai'" as-child size="sm" variant="outline">
                                                <a :href="route('admin.presensi.pertemuan.bap', b.id)" target="_blank" rel="noopener"
                                                    ><FileText /> BAP</a
                                                >
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pertemuan.data.length">
                                    <td colspan="8" class="tabel-kosong">Tidak ada pertemuan sesuai filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="props.pertemuan.links" :total="props.pertemuan.total" />
            </div>
        </div>

        <div v-if="dipilih" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="dipilih = null">
            <form class="kartu max-h-[90vh] w-full max-w-xl overflow-y-auto p-6 shadow-xl" @submit.prevent="simpan">
                <h3 class="judul-bagian">Koreksi Presensi Dosen</h3>
                <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                    {{ dipilih.kelas.kode }} · Pertemuan {{ dipilih.pertemuan_ke }} · {{ formatTanggal(dipilih.tanggal) }} {{ dipilih.jam_mulai }}–{{
                        dipilih.jam_akhir
                    }}
                </p>
                <p v-if="dipilih.status !== 'selesai' && hadir" class="alert-info mt-3">
                    Pertemuan ini belum terlaksana. Menyimpan status Hadir/Digantikan mencatatnya sebagai pertemuan susulan; presensi mahasiswa diisi
                    di halaman pertemuan.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-2">
                        <span class="label-isian">Status kehadiran dosen</span>
                        <SelectFilter v-model="form.status_dosen" label="Status kehadiran dosen" penuh>
                            <option v-for="s in props.statusDosen" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </SelectFilter>
                        <InputError :message="form.errors.status_dosen" />
                    </label>
                    <div v-if="hadir" class="grid gap-2">
                        <span class="label-isian">Dosen pengajar</span>
                        <SearchSelect
                            id="dosen_id"
                            v-model="form.dosen_id"
                            :options="props.dosenOptions"
                            placeholder="Pilih dosen"
                            search-placeholder="Cari dosen"
                        />
                        <InputError :message="form.errors.dosen_id" />
                    </div>
                    <template v-if="hadir">
                        <label class="grid gap-2">
                            <span class="label-isian">Jam masuk</span>
                            <TimePicker v-model="form.jam_masuk" required />
                            <InputError :message="form.errors.jam_masuk" />
                        </label>
                        <label class="grid gap-2">
                            <span class="label-isian">Jam keluar</span>
                            <TimePicker v-model="form.jam_keluar" required />
                            <InputError :message="form.errors.jam_keluar" />
                        </label>
                        <label class="grid gap-2 sm:col-span-2">
                            <span class="label-isian">Topik / realisasi materi</span>
                            <textarea v-model="form.topik" rows="3" maxlength="5000" class="isian isian-area" required />
                            <InputError :message="form.errors.topik" />
                        </label>
                    </template>
                    <label class="grid gap-2 sm:col-span-2">
                        <span class="label-isian">Alasan koreksi</span>
                        <textarea
                            v-model="form.alasan"
                            rows="2"
                            maxlength="255"
                            class="isian isian-area"
                            required
                            placeholder="Mis. dosen lupa menekan Mulai, surat sakit"
                        />
                        <InputError :message="form.errors.alasan" />
                    </label>
                </div>

                <details v-if="dipilih.riwayat.length" class="mt-4 text-sm">
                    <summary class="cursor-pointer font-medium">Riwayat ({{ dipilih.riwayat.length }})</summary>
                    <ul class="mt-2 space-y-2">
                        <li v-for="r in dipilih.riwayat" :key="r.id" class="kartu px-3 py-2">
                            <p class="font-medium">{{ r.aksi }}</p>
                            <p v-for="(nilai, label) in r.perubahan" :key="label">{{ label }}: {{ nilai[0] ?? '-' }} → {{ nilai[1] ?? '-' }}</p>
                            <p v-if="r.alasan">Alasan: {{ r.alasan }}</p>
                            <p class="teks-bantu">{{ r.oleh ?? 'Sistem' }} · {{ r.waktu ? formatTanggal(r.waktu) : '' }}</p>
                        </li>
                    </ul>
                </details>

                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="dipilih = null">Batal</Button>
                    <Button type="submit" :disabled="form.processing">Simpan koreksi</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
