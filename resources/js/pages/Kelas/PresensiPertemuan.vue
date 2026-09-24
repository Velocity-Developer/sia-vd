<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LayarPresensiMandiri from '@/components/LayarPresensiMandiri.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    JENIS_PERTEMUAN,
    STATUS_PERTEMUAN,
    STATUS_PRESENSI,
    formatJamDari,
    formatTanggal,
    jam,
    type JenisPertemuan,
    type StatusPertemuan,
    type StatusPresensi,
} from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCheck, Play, QrCode, Square, TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Pertemuan = {
    id: number;
    pertemuan_ke: number;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang_id: number | null;
    jenis: JenisPertemuan;
    status: StatusPertemuan;
    dosen_id: number | null;
    dosen_masuk_at: string | null;
    dosen_keluar_at: string | null;
    topik: string | null;
    catatan: string | null;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    dosen?: { nidn: string; user?: { name: string } | null } | null;
};
type Baris = {
    mahasiswa_id: number;
    nim: string;
    nama: string;
    status: StatusPresensi;
    keterangan: string | null;
    metode: string;
    waktu_presensi: string | null;
    diubah_oleh: string | null;
    perangkat_bersama: boolean;
    memenuhi_syarat_ujian: boolean | null;
    pengajuan: { id: number; jenis: string; status: 'menunggu' | 'disetujui' | 'ditolak' } | null;
};

const props = defineProps<{
    peran: Peran;
    kelasKuliah: {
        id: number;
        kode_kelas: string;
        dosen_id: number;
        mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
        dosen?: { user?: { name: string } | null } | null;
        tahun_akademik?: { tahun: string; semester: string } | null;
    };
    pertemuan: Pertemuan;
    presensi: Baris[];
    jumlahPeserta: number;
    bisaKelola: boolean;
    bisaAturJadwal: boolean;
    bisaDimulai: boolean;
    mandiriTerbuka: boolean;
    durasiMandiri: number;
    terkunci: boolean;
    dosenOptions: { id: number; name: string }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const rute = rutePeran(props.peran);
const isAdmin = computed(() => props.peran === 'admin');
const status = computed(() => props.pertemuan.status);
const bisaIsi = computed(() => props.bisaKelola && !props.terkunci && (status.value === 'berlangsung' || status.value === 'selesai'));
// Dosen pengganti hanya boleh membuka pertemuan ini, bukan halaman kelasnya.
const pengganti = computed(() => props.bisaKelola && !props.bisaAturJadwal);

// Salinan lokal daftar hadir; diperbarui setiap kali data dari server berubah.
const baris = ref<{ mahasiswa_id: number; status: StatusPresensi; keterangan: string }[]>([]);
watch(
    () => props.presensi,
    (data) => (baris.value = data.map((item) => ({ mahasiswa_id: item.mahasiswa_id, status: item.status, keterangan: item.keterangan ?? '' }))),
    { immediate: true },
);
const asli = computed(() => new Map(props.presensi.map((item) => [item.mahasiswa_id, item])));
const berubah = computed(() =>
    baris.value.filter((item) => {
        const awal = asli.value.get(item.mahasiswa_id);
        return awal && (awal.status !== item.status || (awal.keterangan ?? '') !== item.keterangan.trim());
    }),
);
const ringkasan = computed(() => STATUS_PRESENSI.map((s) => ({ ...s, jumlah: baris.value.filter((item) => item.status === s.value).length })));

const tandaiSemuaHadir = () =>
    baris.value.forEach((item) => {
        if (item.status === 'alpa') item.status = 'hadir';
    });

const menyimpan = ref(false);
const simpanPresensi = () =>
    router.put(
        rute('presensi.pertemuan.mahasiswa', props.pertemuan.id),
        { presensi: berubah.value.map((item) => ({ ...item, keterangan: item.keterangan.trim() || null })) },
        { preserveScroll: true, onStart: () => (menyimpan.value = true), onFinish: () => (menyimpan.value = false) },
    );

const mulai = () => router.post(rute('presensi.pertemuan.mulai', props.pertemuan.id), {}, { preserveScroll: true });

const jurnalForm = useForm({ topik: props.pertemuan.topik ?? '' });
watch(
    () => props.pertemuan.topik,
    (topik) => {
        if (!jurnalForm.isDirty) jurnalForm.defaults({ topik: topik ?? '' }).reset();
    },
);
const simpanJurnal = () => jurnalForm.put(rute('presensi.pertemuan.jurnal', props.pertemuan.id), { preserveScroll: true });
const selesaikan = () => {
    // Perubahan kehadiran yang belum disimpan ikut dikirim dulu agar tidak hilang saat pertemuan ditutup.
    const tutup = () => jurnalForm.post(rute('presensi.pertemuan.selesai', props.pertemuan.id), { preserveScroll: true });
    if (berubah.value.length) {
        router.put(
            rute('presensi.pertemuan.mahasiswa', props.pertemuan.id),
            { presensi: berubah.value.map((item) => ({ ...item, keterangan: item.keterangan.trim() || null })) },
            { preserveScroll: true, onSuccess: tutup },
        );
    } else {
        tutup();
    }
};

// Admin: tunjuk dosen pengganti sebelum pertemuan dimulai.
const penggantiForm = useForm({ dosen_id: props.pertemuan.dosen_id ?? ('' as number | string) });
const simpanPengganti = () =>
    penggantiForm
        .transform((data) => ({
            tanggal: props.pertemuan.tanggal.slice(0, 10),
            jam_mulai: jam(props.pertemuan.jam_mulai),
            jam_akhir: jam(props.pertemuan.jam_akhir),
            ruang_id: props.pertemuan.ruang_id,
            jenis: props.pertemuan.jenis,
            catatan: props.pertemuan.catatan,
            dosen_id: data.dosen_id || null,
        }))
        .put(rute('presensi.pertemuan.update', props.pertemuan.id), { preserveScroll: true });

// Presensi mandiri (QR/PIN).
const mandiriForm = useForm({ menit: props.durasiMandiri });
const bukaMandiri = () => mandiriForm.post(rute('presensi.pertemuan.mandiri.buka', props.pertemuan.id), { preserveScroll: true });
const tutupMandiri = () => router.delete(rute('presensi.pertemuan.mandiri.tutup', props.pertemuan.id), { preserveScroll: true });
// Muat ulang daftar hadir saat ada yang presensi, kecuali dosen sedang menyunting (perubahannya belum disimpan).
const muatUlangPresensi = () => {
    if (!berubah.value.length) router.reload({ only: ['presensi'] });
};
const presensiTertutup = () => router.reload({ only: ['mandiriTerbuka', 'presensi'] });
const judulLayar = computed(() => `${props.kelasKuliah.mata_kuliah?.nama_matkul ?? ''} · Pertemuan ${props.pertemuan.pertemuan_ke}`);

const infoMulai = computed(
    () =>
        `Pertemuan bisa dimulai pada ${formatTanggal(props.pertemuan.tanggal)}, sejak 15 menit sebelum ${jam(props.pertemuan.jam_mulai)} sampai ${jam(props.pertemuan.jam_akhir)}.`,
);

const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
const kartu = 'rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm';
</script>

<template>
    <Head :title="`Pertemuan ${props.pertemuan.pertemuan_ke} · ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi', href: rute('presensi.index') },
            { title: props.kelasKuliah.kode_kelas, href: rute('presensi.kelas', props.kelasKuliah.id) },
            { title: `Pertemuan ${props.pertemuan.pertemuan_ke}`, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm text-[#615d59]">{{ props.kelasKuliah.mata_kuliah?.nama_matkul }} · {{ props.kelasKuliah.kode_kelas }}</p>
                        <h1 class="flex flex-wrap items-center gap-2 text-[26px] font-bold leading-[1.23] text-black">
                            Pertemuan {{ props.pertemuan.pertemuan_ke }}
                            <span
                                v-if="props.pertemuan.jenis !== 'kuliah'"
                                class="rounded bg-[#fff6e0] px-2 py-0.5 text-sm font-semibold text-[#8a5a00]"
                            >
                                {{ JENIS_PERTEMUAN[props.pertemuan.jenis] }}
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-sm font-medium" :class="STATUS_PERTEMUAN[status].kelas">
                                {{ STATUS_PERTEMUAN[status].label }}
                            </span>
                        </h1>
                        <p class="text-sm text-[#31302e]">
                            {{ formatTanggal(props.pertemuan.tanggal) }} · {{ jam(props.pertemuan.jam_mulai) }}–{{ jam(props.pertemuan.jam_akhir) }} ·
                            {{ props.pertemuan.ruang ? `${props.pertemuan.ruang.kode_ruang} — ${props.pertemuan.ruang.nama_ruang}` : 'Tanpa ruang' }}
                        </p>
                        <p v-if="props.pertemuan.catatan" class="text-sm text-[#615d59]">Catatan: {{ props.pertemuan.catatan }}</p>
                    </div>
                    <Link v-if="!pengganti" :href="rute('presensi.kelas', props.kelasKuliah.id)">
                        <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black">Semua pertemuan</Button>
                    </Link>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="props.terkunci" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#615d59]">
                    Tahun akademik kelas ini sudah tidak aktif. Presensi hanya bisa diubah admin.
                </div>

                <!-- Presensi dosen -->
                <section :class="kartu">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Presensi dosen &amp; jurnal</h2>

                    <dl class="mt-3 grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-[#a39e98]">Dosen</dt>
                            <dd class="font-medium text-black">
                                {{ props.pertemuan.dosen?.user?.name ?? props.kelasKuliah.dosen?.user?.name ?? '-' }}
                                <span
                                    v-if="props.pertemuan.dosen_id && props.pertemuan.dosen_id !== props.kelasKuliah.dosen_id"
                                    class="text-xs text-[#a39e98]"
                                    >(pengganti)</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Jam masuk</dt>
                            <dd class="font-medium text-black">{{ formatJamDari(props.pertemuan.dosen_masuk_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Jam keluar</dt>
                            <dd class="font-medium text-black">{{ formatJamDari(props.pertemuan.dosen_keluar_at) }}</dd>
                        </div>
                    </dl>

                    <div v-if="status === 'dijadwalkan'" class="mt-4 flex flex-wrap items-center gap-3">
                        <Button v-if="props.bisaDimulai" class="h-11 rounded-full bg-[#0075de] px-6 text-white hover:bg-[#005bab]" @click="mulai">
                            <Play class="mr-1 size-4" /> {{ isAdmin ? 'Buka pertemuan' : 'Mulai kuliah' }}
                        </Button>
                        <p v-else-if="!props.terkunci && props.bisaKelola" class="text-sm text-[#615d59]">{{ infoMulai }}</p>
                        <p v-if="isAdmin && props.bisaDimulai" class="text-xs text-[#a39e98]">
                            Admin bisa membuka pertemuan di luar jadwal untuk mencatat presensi susulan; jam masuk dosen tidak tercatat.
                        </p>
                    </div>

                    <form
                        v-if="isAdmin && status === 'dijadwalkan'"
                        class="mt-4 flex flex-wrap items-end gap-3 border-t border-[#e6e6e6] pt-4"
                        @submit.prevent="simpanPengganti"
                    >
                        <div class="grid min-w-[260px] flex-1 gap-1.5">
                            <Label for="dosen_id" class="text-sm">Dosen yang mengajar</Label>
                            <select
                                id="dosen_id"
                                v-model="penggantiForm.dosen_id"
                                class="h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px]"
                            >
                                <option v-for="d in props.dosenOptions" :key="d.id" :value="d.id">
                                    {{ d.name }}{{ d.id === props.kelasKuliah.dosen_id ? ' (pengampu)' : '' }}
                                </option>
                            </select>
                        </div>
                        <Button type="submit" variant="outline" :disabled="penggantiForm.processing || !penggantiForm.isDirty">Simpan dosen</Button>
                        <InputError
                            class="w-full"
                            :message="penggantiForm.errors.dosen_id ?? (penggantiForm.errors as Record<string, string | undefined>).tanggal"
                        />
                    </form>

                    <form v-if="status === 'berlangsung' || status === 'selesai'" class="mt-4 grid gap-2" @submit.prevent="simpanJurnal">
                        <Label for="topik">Topik / realisasi materi</Label>
                        <textarea
                            id="topik"
                            v-model="jurnalForm.topik"
                            rows="3"
                            :disabled="props.terkunci || !props.bisaKelola"
                            placeholder="Materi yang disampaikan pada pertemuan ini"
                            class="rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] disabled:bg-[#f6f5f4]"
                        />
                        <InputError :message="jurnalForm.errors.topik" />
                        <div v-if="!props.terkunci && props.bisaKelola" class="flex flex-wrap justify-end gap-2">
                            <Button type="submit" variant="outline" :disabled="jurnalForm.processing || !jurnalForm.isDirty">Simpan jurnal</Button>
                            <Button
                                v-if="status === 'berlangsung'"
                                type="button"
                                class="bg-[#1a7f37] text-white hover:bg-[#146c2e]"
                                :disabled="jurnalForm.processing"
                                @click="selesaikan"
                            >
                                <Square class="mr-1 size-4" /> Selesaikan pertemuan
                            </Button>
                        </div>
                    </form>
                </section>

                <!-- Presensi mandiri -->
                <section v-if="status === 'berlangsung' && !props.terkunci && props.bisaKelola" :class="kartu">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Presensi mandiri (QR / PIN)</h2>
                            <p class="mt-1 max-w-xl text-sm text-[#615d59]">
                                Mahasiswa memindai QR atau mengetik PIN di menu Presensi. Kode berganti tiap 30 detik, jadi foto QR yang dikirim ke
                                teman cepat kedaluwarsa. Anda tetap bisa mengoreksi status secara manual.
                            </p>
                        </div>
                        <Button v-if="props.mandiriTerbuka" type="button" variant="outline" class="text-[#dd5b00]" @click="tutupMandiri"
                            >Tutup</Button
                        >
                    </div>

                    <form v-if="!props.mandiriTerbuka" class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="bukaMandiri">
                        <div class="grid gap-1.5">
                            <Label for="menit" class="text-sm">Dibuka selama (menit)</Label>
                            <Input
                                id="menit"
                                v-model="mandiriForm.menit"
                                type="number"
                                min="1"
                                max="180"
                                class="h-10 w-28 rounded-[4px] border-[#dddddd]"
                            />
                        </div>
                        <Button type="submit" class="h-10 bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="mandiriForm.processing">
                            <QrCode class="mr-1 size-4" /> Buka presensi mandiri
                        </Button>
                        <InputError class="w-full" :message="mandiriForm.errors.menit" />
                    </form>

                    <div v-else class="mt-4 grid gap-4 md:grid-cols-[auto,1fr] md:items-start">
                        <LayarPresensiMandiri
                            :url-kode="rute('presensi.pertemuan.kode', props.pertemuan.id)"
                            :judul="judulLayar"
                            @hadir-berubah="muatUlangPresensi"
                            @tertutup="presensiTertutup"
                        />
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="bukaMandiri">
                            <div class="grid gap-1.5">
                                <Label for="menit" class="text-sm">Perpanjang dari sekarang (menit)</Label>
                                <Input
                                    id="menit"
                                    v-model="mandiriForm.menit"
                                    type="number"
                                    min="1"
                                    max="180"
                                    class="h-10 w-28 rounded-[4px] border-[#dddddd]"
                                />
                            </div>
                            <Button type="submit" variant="outline" class="h-10" :disabled="mandiriForm.processing">Perpanjang</Button>
                        </form>
                    </div>
                </section>

                <!-- Presensi mahasiswa -->
                <section :class="kartu">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Presensi mahasiswa</h2>
                            <p v-if="status === 'dijadwalkan'" class="mt-1 text-sm text-[#615d59]">
                                Daftar hadir {{ props.jumlahPeserta }} mahasiswa muncul setelah pertemuan dimulai.
                            </p>
                            <p v-else-if="status === 'dibatalkan'" class="mt-1 text-sm text-[#615d59]">Pertemuan dibatalkan, tidak ada presensi.</p>
                            <div v-else class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span v-for="s in ringkasan" :key="s.value" class="rounded border px-2 py-0.5" :class="s.kelas"
                                    >{{ s.label }}: {{ s.jumlah }}</span
                                >
                            </div>
                        </div>
                        <Button v-if="bisaIsi && baris.length" type="button" variant="outline" size="sm" @click="tandaiSemuaHadir">
                            <CheckCheck class="mr-1 size-4" /> Alpa → Hadir semua
                        </Button>
                    </div>

                    <div v-if="baris.length" class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[720px] text-left">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th :class="th">Mahasiswa</th>
                                        <th :class="th">Status</th>
                                        <th :class="th">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="(item, index) in baris" :key="item.mahasiswa_id">
                                        <td class="px-4 py-2.5">
                                            <span class="block text-[15px] font-medium text-black">{{ props.presensi[index]?.nama }}</span>
                                            <span class="block text-xs text-[#a39e98]">
                                                {{ props.presensi[index]?.nim }}
                                                <template v-if="['qr', 'pin'].includes(props.presensi[index]?.metode ?? '')">
                                                    · {{ props.presensi[index]?.metode.toUpperCase() }}
                                                    {{ formatJamDari(props.presensi[index]?.waktu_presensi) }}</template
                                                >
                                                <template v-else-if="props.presensi[index]?.diubah_oleh">
                                                    · diubah {{ props.presensi[index]?.diubah_oleh }}</template
                                                >
                                            </span>
                                            <span
                                                v-if="props.presensi[index]?.perangkat_bersama"
                                                class="mt-1 inline-flex items-center gap-1 rounded bg-[#fff6e0] px-1.5 py-0.5 text-xs text-[#8a5a00]"
                                                title="Perangkat yang sama dipakai presensi oleh mahasiswa lain di pertemuan ini"
                                            >
                                                <TriangleAlert class="size-3" /> Perangkat sama dengan mahasiswa lain
                                            </span>
                                            <span
                                                v-if="props.presensi[index]?.memenuhi_syarat_ujian === false"
                                                class="mt-1 inline-flex rounded bg-[#fdecea] px-1.5 py-0.5 text-xs text-[#b42318]"
                                                >Tidak memenuhi syarat kehadiran ujian</span
                                            >
                                            <span
                                                v-if="props.presensi[index]?.pengajuan"
                                                class="mt-1 inline-flex rounded bg-[#f6f5f4] px-1.5 py-0.5 text-xs text-[#615d59]"
                                                >Pengajuan {{ props.presensi[index]?.pengajuan?.jenis }}:
                                                {{ props.presensi[index]?.pengajuan?.status }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex gap-1" role="radiogroup" :aria-label="`Status ${props.presensi[index]?.nama}`">
                                                <button
                                                    v-for="s in STATUS_PRESENSI"
                                                    :key="s.value"
                                                    type="button"
                                                    role="radio"
                                                    :aria-checked="item.status === s.value"
                                                    :title="s.label"
                                                    :disabled="!bisaIsi"
                                                    class="size-9 rounded-md border text-sm font-semibold transition-colors disabled:cursor-not-allowed"
                                                    :class="
                                                        item.status === s.value
                                                            ? s.kelas
                                                            : 'border-[#e6e6e6] bg-white text-[#a39e98] hover:bg-[#f6f5f4]'
                                                    "
                                                    @click="item.status = s.value"
                                                >
                                                    {{ s.singkat }}
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <Input
                                                v-model="item.keterangan"
                                                maxlength="255"
                                                :disabled="!bisaIsi"
                                                :placeholder="item.status === 'izin' || item.status === 'sakit' ? 'Alasan izin/sakit' : ''"
                                                class="h-9 rounded-[4px] border-[#dddddd] text-sm"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="bisaIsi && baris.length" class="mt-4 flex flex-wrap items-center justify-end gap-3">
                        <span v-if="berubah.length" class="text-sm text-[#dd5b00]">{{ berubah.length }} perubahan belum disimpan</span>
                        <Button class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="menyimpan || !berubah.length" @click="simpanPresensi">
                            Simpan presensi
                        </Button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
