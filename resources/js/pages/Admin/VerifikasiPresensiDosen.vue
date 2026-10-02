<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    formatJamDari,
    formatTanggal,
    infoVerifikasi,
    JENIS_PERTEMUAN,
    kelasStatusDosen,
    type JenisPertemuan,
    type VerifikasiPresensi,
} from '@/lib/presensi';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, FileText, RotateCcw, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Opsi = { id: number | string; name: string };
type Baris = {
    id: number;
    pertemuan_ke: number;
    jenis: JenisPertemuan;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    kelas: { id: number; kode: string; mata_kuliah: string | null };
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
    diverifikasi_oleh: string | null;
    diverifikasi_at: string | null;
    jumlah_peserta: number;
    jumlah_hadir: number;
};
type Filter = {
    tahun_akademik_id: number | null;
    prodi_id: number | null;
    dosen_id: number | null;
    search: string;
    status: 'menunggu' | 'disetujui' | 'ditolak';
};

const props = defineProps<{
    pertemuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: Filter;
    jumlahMenunggu: number;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    dosenFilterOptions: Opsi[];
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();

const search = ref(props.filter.search);
const saring = (perubahan: Partial<Filter>) =>
    router.get(
        route('admin.verifikasi-presensi-dosen.index'),
        Object.fromEntries(Object.entries({ ...props.filter, ...perubahan }).filter(([, nilai]) => nilai !== null && nilai !== '')),
        { preserveState: true, preserveScroll: true },
    );
let tunda: ReturnType<typeof setTimeout> | undefined;
watch(search, (nilai) => {
    clearTimeout(tunda);
    tunda = setTimeout(() => saring({ search: nilai }), 350);
});

const pilih = ref<number[]>([]);
watch(
    () => props.pertemuan.data,
    () => (pilih.value = pilih.value.filter((id) => props.pertemuan.data.some((b) => b.id === id))),
);
const semuaDipilih = computed(() => props.pertemuan.data.length > 0 && pilih.value.length === props.pertemuan.data.length);
const pilihSemua = (centang: boolean) => (pilih.value = centang ? props.pertemuan.data.map((b) => b.id) : []);

const proses = useForm({ ids: [] as number[] });
const setujui = (ids: number[]) =>
    proses
        .transform(() => ({ ids }))
        .post(route('admin.verifikasi-presensi-dosen.setujui'), { preserveScroll: true, onSuccess: () => (pilih.value = []) });

// Dialog tolak / batal: keduanya butuh teks.
const dialog = ref<null | { jenis: 'tolak' | 'batal'; ids: number[] }>(null);
const formDialog = useForm({ ids: [] as number[], catatan: '', alasan: '' });
const bukaDialog = (jenis: 'tolak' | 'batal', ids: number[]) => {
    formDialog.reset();
    formDialog.clearErrors();
    dialog.value = { jenis, ids };
};
const kirimDialog = () => {
    if (!dialog.value) return;
    const { jenis, ids } = dialog.value;
    formDialog
        .transform((data) => (jenis === 'tolak' ? { ids, catatan: data.catatan } : { ids, alasan: data.alasan }))
        .post(route(`admin.verifikasi-presensi-dosen.${jenis}`), {
            preserveScroll: true,
            onSuccess: () => {
                dialog.value = null;
                pilih.value = [];
            },
        });
};

const TAB = [
    { id: 'menunggu', label: 'Menunggu' },
    { id: 'ditolak', label: 'Ditolak' },
    { id: 'disetujui', label: 'Terverifikasi' },
] as const;
</script>

<template>
    <Head title="Verifikasi Presensi Dosen" />
    <AppLayout :breadcrumbs="[{ title: 'Verifikasi Presensi Dosen', href: route('admin.verifikasi-presensi-dosen.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Verifikasi Presensi Dosen</h1>
                        <p class="deskripsi-halaman">
                            Pertemuan yang sudah selesai. Disetujui = presensi dan jurnal terkunci dan BAP sah dicetak; ditolak = dikembalikan ke
                            dosen dan kembali ke antrean setelah diperbaiki.
                        </p>
                    </div>
                </div>

                <nav class="flex flex-wrap gap-1 border-b border-[#e6e6e6] dark:border-border" aria-label="Status verifikasi">
                    <button
                        v-for="t in TAB"
                        :key="t.id"
                        type="button"
                        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                        :class="
                            props.filter.status === t.id
                                ? 'border-[#0075de] text-black dark:text-foreground'
                                : 'border-transparent text-[#615d59] hover:text-black dark:text-muted-foreground'
                        "
                        @click="saring({ status: t.id })"
                    >
                        {{ t.label }}<span v-if="t.id === 'menunggu'" class="teks-bantu"> ({{ props.jumlahMenunggu }})</span>
                    </button>
                </nav>

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
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div v-if="pilih.length" class="kartu flex flex-wrap items-center gap-2 px-4 py-3">
                    <span class="text-sm font-medium">{{ pilih.length }} dipilih</span>
                    <template v-if="props.filter.status !== 'disetujui'">
                        <Button size="sm" :disabled="proses.processing" @click="setujui(pilih)"><Check /> Setujui</Button>
                        <Button v-if="props.filter.status === 'menunggu'" size="sm" variant="outline" @click="bukaDialog('tolak', pilih)"
                            ><X /> Tolak</Button
                        >
                    </template>
                    <Button v-if="props.filter.status !== 'menunggu'" size="sm" variant="outline" @click="bukaDialog('batal', pilih)">
                        <RotateCcw /> Batalkan verifikasi
                    </Button>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1400px]">
                            <thead>
                                <tr>
                                    <th class="w-10">
                                        <input
                                            type="checkbox"
                                            :checked="semuaDipilih"
                                            aria-label="Pilih semua"
                                            @change="pilihSemua(($event.target as HTMLInputElement).checked)"
                                        />
                                    </th>
                                    <th>Tanggal</th>
                                    <th>Kelas</th>
                                    <th>Dosen</th>
                                    <th>Masuk–Keluar</th>
                                    <th>Topik</th>
                                    <th class="text-center">Hadir mhs</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="b in props.pertemuan.data" :key="b.id">
                                    <td>
                                        <input
                                            v-model="pilih"
                                            type="checkbox"
                                            :value="b.id"
                                            :aria-label="`Pilih ${b.kelas.kode} pertemuan ${b.pertemuan_ke}`"
                                        />
                                    </td>
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
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="kelasStatusDosen(b.status_dosen)">{{
                                            b.status_dosen_label
                                        }}</span>
                                    </td>
                                    <td class="whitespace-nowrap tabular-nums">
                                        {{ b.jam_masuk ?? '-' }}–{{ b.jam_keluar ?? '-' }}
                                        <p v-if="b.menit_terlambat" class="text-xs font-medium text-[#dd5b00]">
                                            Terlambat {{ b.menit_terlambat }} mnt
                                        </p>
                                    </td>
                                    <td class="max-w-[280px]">
                                        <p v-if="b.topik" class="line-clamp-3" :title="b.topik">{{ b.topik }}</p>
                                        <p v-else class="font-medium text-[#b42318]">Jurnal kosong</p>
                                    </td>
                                    <td class="text-center tabular-nums">{{ b.jumlah_hadir }}/{{ b.jumlah_peserta }}</td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="infoVerifikasi(b.verifikasi).kelas"
                                            >{{ infoVerifikasi(b.verifikasi).label }}</span
                                        >
                                        <p v-if="b.catatan_verifikasi" class="teks-bantu mt-1">{{ b.catatan_verifikasi }}</p>
                                        <p v-if="b.diverifikasi_oleh" class="teks-bantu mt-1">
                                            {{ b.diverifikasi_oleh }} · {{ formatTanggal(b.diverifikasi_at, false) }}
                                            {{ formatJamDari(b.diverifikasi_at) }}
                                        </p>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <template v-if="b.verifikasi !== 'disetujui'">
                                                <Button size="sm" :disabled="proses.processing || !b.topik" @click="setujui([b.id])"
                                                    ><Check /> Setujui</Button
                                                >
                                                <Button v-if="!b.verifikasi" size="sm" variant="outline" @click="bukaDialog('tolak', [b.id])"
                                                    ><X /> Tolak</Button
                                                >
                                            </template>
                                            <Button v-if="b.verifikasi" size="sm" variant="outline" @click="bukaDialog('batal', [b.id])"
                                                ><RotateCcw /> Batal</Button
                                            >
                                            <Button as-child size="sm" variant="outline">
                                                <a :href="route('admin.presensi.pertemuan.bap', b.id)" target="_blank" rel="noopener"
                                                    ><FileText /> BAP</a
                                                >
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pertemuan.data.length">
                                    <td colspan="9" class="tabel-kosong">Tidak ada pertemuan pada daftar ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="props.pertemuan.links" :total="props.pertemuan.total" />
            </div>
        </div>

        <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="dialog = null">
            <form class="kartu w-full max-w-md p-6 shadow-xl" @submit.prevent="kirimDialog">
                <h3 class="judul-bagian">
                    {{ dialog.jenis === 'tolak' ? 'Tolak presensi' : 'Batalkan verifikasi' }} ({{ dialog.ids.length }} pertemuan)
                </h3>
                <p class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                    {{
                        dialog.jenis === 'tolak'
                            ? 'Catatan ditampilkan ke dosen di halaman pertemuan agar diperbaiki.'
                            : 'Pertemuan kembali menunggu verifikasi dan presensinya bisa diubah lagi.'
                    }}
                </p>
                <label v-if="dialog.jenis === 'tolak'" class="mt-4 grid gap-2">
                    <span class="label-isian">Catatan penolakan</span>
                    <textarea
                        v-model="formDialog.catatan"
                        rows="3"
                        maxlength="255"
                        class="isian isian-area"
                        required
                        placeholder="Mis. topik belum diisi lengkap"
                    />
                    <InputError :message="formDialog.errors.catatan" />
                </label>
                <label v-else class="mt-4 grid gap-2">
                    <span class="label-isian">Alasan pembatalan</span>
                    <textarea v-model="formDialog.alasan" rows="3" maxlength="255" class="isian isian-area" required />
                    <InputError :message="formDialog.errors.alasan" />
                </label>
                <InputError :message="formDialog.errors.ids" />
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="dialog = null">Batal</Button>
                    <Button type="submit" :disabled="formDialog.processing">{{ dialog.jenis === 'tolak' ? 'Tolak' : 'Batalkan verifikasi' }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
