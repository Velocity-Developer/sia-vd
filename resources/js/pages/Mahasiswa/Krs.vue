<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type KelasKuliah = {
    id: number;
    kode_kelas: string;
    kapasitas: number;
    krs_count: number;
    mata_kuliah?: {
        id: number;
        kode_matkul: string;
        nama_matkul: string;
        sks: number;
        jenis: string;
        semester: number;
        tugas_akhir?: boolean;
        prodi?: { nama_prodi: string } | null;
    } | null;
    mataKuliah?: {
        id: number;
        kode_matkul: string;
        nama_matkul: string;
        sks: number;
        jenis: string;
        semester: number;
        tugas_akhir?: boolean;
        prodi?: { nama_prodi: string } | null;
    } | null;
    tahun_akademik?: { tahun: string; semester: string } | null;
    dosen?: { user?: { name: string } | null } | null;
    jadwals?: { hari: string; jam_mulai: string; jam_akhir: string; ruang?: { kode_ruang: string } | null }[];
};

const props = defineProps<{
    kelasKuliahs: KelasKuliah[];
    mahasiswa: { semester: number | null; angkatan: string; prodi_id: number; status: string | null };
    kelasDiambil: number[];
    krsTahunIni: { id: number; kelas_id: number; nilai: string | null }[];
    labelMatkul: Record<number, string>;
    terkunciMatkul: Record<number, string>;
    sksDiambil: number;
    maksSks: number;
    ipsSebelumnya: { ips: number; tahun_akademik: string } | null;
    bolehKrs: boolean;
    tahunAkademik: { tahun: string; semester: string; tanggal_krs_awal: string; tanggal_krs_akhir: string } | null;
    periodeKrsAktif: boolean;
    krsTersimpan: boolean;
    krsDisimpanPada: string | null;
}>();

const formatTanggal = (value?: string) =>
    value ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value)) : '-';

const page = usePage<{ flash?: { krs_success?: string; krs_error?: string; krs_konfirmasi?: string } }>();
const resultModalOpen = ref(false);
const resultMessage = ref('');
const resultTitle = ref('Informasi');

const showResult = () => {
    const error = page.props.flash?.krs_error;
    const success = page.props.flash?.krs_success;

    if (error) {
        resultTitle.value = 'KRS Gagal Diproses';
        resultMessage.value = error;
    } else if (success) {
        resultTitle.value = 'KRS Berhasil Diperbarui';
        resultMessage.value = success;
    } else {
        return;
    }

    resultModalOpen.value = true;
};
const isTaken = (kelasId: number) => props.kelasDiambil.includes(kelasId);
const terkunci = (kelas: KelasKuliah) => props.terkunciMatkul[matkul(kelas)?.id ?? 0] ?? null;
const krsBisaDibatalkan = (kelasId: number) => props.krsTahunIni.find((krs) => krs.kelas_id === kelasId && !krs.nilai);
const selectedKelasId = ref<number | null>(null);
const modalOpen = ref(false);
const batalKrsId = ref<number | null>(null);
const batalModalOpen = ref(false);

const batalkanKelas = (kelasId: number) => {
    batalKrsId.value = krsBisaDibatalkan(kelasId)?.id ?? null;
    batalModalOpen.value = batalKrsId.value !== null;
};

const confirmBatalKelas = () => {
    if (batalKrsId.value === null) return;

    router.delete(route('mahasiswa.krs.destroy', batalKrsId.value), {
        preserveScroll: true,
        onFinish: () => {
            batalModalOpen.value = false;
            batalKrsId.value = null;
        },
    });
};

const ambilKelas = (kelasId: number) => {
    selectedKelasId.value = kelasId;
    modalOpen.value = true;
};

const confirmAmbilKelas = () => {
    if (selectedKelasId.value === null) return;

    router.post(
        route('mahasiswa.krs.store', selectedKelasId.value),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                modalOpen.value = false;
                selectedKelasId.value = null;
            },
        },
    );
};

onMounted(showResult);
watch(() => [page.props.flash?.krs_error, page.props.flash?.krs_success], showResult);

const simpanModalOpen = ref(false);
const pesanKonfirmasi = ref('');

const simpanKrs = (konfirmasi = false) => {
    router.post(
        route('mahasiswa.krs.simpan'),
        { konfirmasi },
        {
            preserveScroll: true,
            onSuccess: () => {
                const perlu = page.props.flash?.krs_konfirmasi;
                pesanKonfirmasi.value = perlu ?? '';
                simpanModalOpen.value = Boolean(perlu);
            },
        },
    );
};

const matkul = (kelas: KelasKuliah) => kelas.mataKuliah ?? kelas.mata_kuliah;

const groupedKelasKuliahs = computed(() => {
    const groups = new Map<string, { matkul: NonNullable<KelasKuliah['mataKuliah']>; kelas: KelasKuliah[] }>();

    const hariOrder: Record<string, number> = {
        Senin: 1,
        Selasa: 2,
        Rabu: 3,
        Kamis: 4,
        Jumat: 5,
        Sabtu: 6,
        Minggu: 7,
    };

    for (const kelas of [...props.kelasKuliahs].sort((first, second) => {
        const firstSchedule = first.jadwals?.[0];
        const secondSchedule = second.jadwals?.[0];
        const dayDifference = (hariOrder[firstSchedule?.hari ?? ''] ?? 99) - (hariOrder[secondSchedule?.hari ?? ''] ?? 99);

        return dayDifference || (firstSchedule?.jam_mulai ?? '99:99').localeCompare(secondSchedule?.jam_mulai ?? '99:99');
    })) {
        const mataKuliah = matkul(kelas);
        if (!mataKuliah) continue;
        const key = String(mataKuliah.id ?? mataKuliah.kode_matkul);
        const group = groups.get(key);
        if (group) group.kelas.push(kelas);
        else groups.set(key, { matkul: mataKuliah, kelas: [kelas] });
    }

    return [...groups.values()];
});

const jam = (value: string) => value.slice(0, 5);
const jadwal = (kelas: KelasKuliah) =>
    kelas.jadwals?.map(
        (item) => `${item.hari}, ${jam(item.jam_mulai)}-${jam(item.jam_akhir)}${item.ruang?.kode_ruang ? ` (${item.ruang.kode_ruang})` : ''}`,
    ) || [];
</script>

<template>
    <Head title="Rencana Studi (KRS)" />
    <AppLayout :breadcrumbs="[{ title: 'Rencana Studi (KRS)', href: route('mahasiswa.krs') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Rencana Studi (KRS)</h1>
                        <p class="deskripsi-halaman">
                            Kelas kuliah semester Anda di tahun akademik aktif, ditambah mata kuliah yang tertunda atau perlu diulang.
                        </p>
                    </div>
                    <Button v-if="krsTahunIni.length" as-child>
                        <a :href="route('mahasiswa.krs.download')">
                            <Download class="h-4 w-4" />
                            Download KRS
                        </a>
                    </Button>
                </div>

                <div class="rounded-xl border border-[#0075de] bg-[#0075de] p-6 text-white shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-blue-100">Periode Pengambilan KRS</p>
                    <p v-if="tahunAkademik" class="mt-2 text-lg font-semibold">{{ tahunAkademik.tahun }} — {{ tahunAkademik.semester }}</p>
                    <p v-if="tahunAkademik" class="mt-1 text-sm text-blue-100">
                        {{ formatTanggal(tahunAkademik.tanggal_krs_awal) }} — {{ formatTanggal(tahunAkademik.tanggal_krs_akhir) }}
                    </p>
                    <p v-if="!periodeKrsAktif" class="mt-3 text-sm font-medium">Periode pengambilan KRS sudah selesai.</p>
                    <p v-else class="mt-3 text-sm font-medium">Periode pengambilan KRS sedang berlangsung.</p>
                </div>

                <div v-if="periodeKrsAktif" class="kartu p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">Semester</p>
                            <p class="mt-1 text-sm font-medium text-black dark:text-foreground">{{ mahasiswa.semester ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">Angkatan</p>
                            <p class="mt-1 text-sm font-medium text-black dark:text-foreground">{{ mahasiswa.angkatan }}</p>
                        </div>
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">SKS Diambil</p>
                            <p class="mt-1 text-sm font-medium text-black dark:text-foreground">{{ sksDiambil }} / {{ maksSks }} SKS</p>
                            <p class="teks-bantu">
                                {{
                                    ipsSebelumnya
                                        ? `Berdasarkan IPS ${ipsSebelumnya.ips.toFixed(2)} (${ipsSebelumnya.tahun_akademik})`
                                        : 'Belum ada IPS semester sebelumnya'
                                }}
                            </p>
                        </div>
                    </div>
                    <div v-if="!bolehKrs" class="alert-gagal mt-4">
                        Status akademik Anda ({{ mahasiswa.status ?? 'belum diisi' }}) tidak memungkinkan pengisian KRS, jadi tidak ada kelas yang
                        ditawarkan. Silakan hubungi bagian akademik.
                    </div>

                    <div v-if="krsTersimpan" class="alert-sukses mt-4" role="status">
                        KRS sudah disimpan dan terkunci{{ krsDisimpanPada ? ` pada ${formatTanggal(krsDisimpanPada)}` : '' }}. Perubahan kelas hanya
                        bisa lewat form pindah kelas, atau minta admin membuka kuncinya.
                    </div>

                    <div
                        v-else-if="bolehKrs"
                        class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[#e6e6e6] pt-4 dark:border-border"
                    >
                        <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                            Setelah disimpan, KRS terkunci dan kelas tidak bisa ditambah atau dibatalkan sendiri.
                        </p>
                        <Button :disabled="sksDiambil === 0" @click="simpanKrs(false)">Simpan KRS</Button>
                    </div>
                </div>

                <div v-if="periodeKrsAktif" class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[720px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kelas</th>
                                    <th>Dosen</th>
                                    <th>Jadwal</th>
                                    <th>Kapasitas</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="(group, groupIndex) in groupedKelasKuliahs" :key="group.matkul.kode_matkul">
                                    <tr class="baris-kosong">
                                        <td colspan="6" class="bg-[#f6f5f4] font-semibold text-black dark:bg-muted dark:text-foreground">
                                            {{ group.matkul.kode_matkul }} — {{ group.matkul.nama_matkul }}
                                            <span class="font-normal text-[#615d59] dark:text-muted-foreground"
                                                >({{ group.matkul.sks }} SKS - {{ group.matkul.jenis }})</span
                                            >
                                            <span
                                                v-if="labelMatkul[group.matkul.id]"
                                                class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="
                                                    labelMatkul[group.matkul.id] === 'Mengulang'
                                                        ? 'bg-[#fff4e5] text-[#dd5b00]'
                                                        : 'bg-[#e8f2fc] text-[#0075de]'
                                                "
                                                >{{ labelMatkul[group.matkul.id] }}</span
                                            >
                                            <p v-if="terkunciMatkul[group.matkul.id]" class="mt-1 text-xs font-normal text-[#dd5b00]">
                                                {{ terkunciMatkul[group.matkul.id] }}. Mata kuliah ini belum bisa diambil.
                                            </p>
                                        </td>
                                    </tr>
                                    <tr v-for="(kelas, index) in group.kelas" :key="kelas.id">
                                        <!-- Nomor berlanjut lintas kelompok mata kuliah -->
                                        <td class="kolom-no">
                                            {{
                                                groupedKelasKuliahs.slice(0, groupIndex).reduce((jumlah, g) => jumlah + g.kelas.length, 0) + index + 1
                                            }}
                                        </td>
                                        <td class="font-medium text-black dark:text-foreground">{{ kelas.kode_kelas }}</td>
                                        <td>{{ kelas.dosen?.user?.name ?? '-' }}</td>
                                        <td>
                                            <div v-if="jadwal(kelas).length">
                                                <div v-for="(item, jadwalIndex) in jadwal(kelas)" :key="jadwalIndex">{{ item }}</div>
                                            </div>
                                            <span v-else>-</span>
                                        </td>
                                        <td v-if="matkul(kelas)?.tugas_akhir">Tanpa batas</td>
                                        <td v-else>{{ kelas.kapasitas }} total · {{ Math.max(kelas.kapasitas - kelas.krs_count, 0) }} tersisa</td>
                                        <td class="kolom-aksi">
                                            <Button
                                                v-if="isTaken(kelas.id) && krsBisaDibatalkan(kelas.id) && !krsTersimpan"
                                                size="sm"
                                                variant="destructive"
                                                @click="batalkanKelas(kelas.id)"
                                            >
                                                Batalkan
                                            </Button>
                                            <Button
                                                v-else
                                                size="sm"
                                                :variant="isTaken(kelas.id) ? 'outline' : 'default'"
                                                :disabled="isTaken(kelas.id) || !bolehKrs || krsTersimpan || Boolean(terkunci(kelas))"
                                                @click="ambilKelas(kelas.id)"
                                            >
                                                {{
                                                    isTaken(kelas.id)
                                                        ? 'Sudah Diambil'
                                                        : krsTersimpan
                                                          ? 'KRS Terkunci'
                                                          : terkunci(kelas)
                                                            ? 'Terkunci'
                                                            : 'Ambil'
                                                }}
                                            </Button>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!kelasKuliahs.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada kelas kuliah yang sesuai dengan data akademik Anda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <AlertModal
            v-model:open="resultModalOpen"
            :title="resultTitle"
            :description="resultMessage"
            confirm-text="Tutup"
            cancel-text=""
            @confirm="resultModalOpen = false"
        />
        <AlertModal
            v-model:open="modalOpen"
            title="Apakah Anda yakin ingin mengambil kelas ini?"
            description="Kelas yang sudah diambil masih dapat dibatalkan selama periode KRS berlangsung dan belum ada nilai."
            confirm-text="Ambil"
            cancel-text="Batal"
            @confirm="confirmAmbilKelas"
        />
        <AlertModal
            v-model:open="simpanModalOpen"
            title="Simpan KRS dengan SKS di bawah jatah?"
            :description="pesanKonfirmasi"
            confirm-text="Ya, Simpan KRS"
            cancel-text="Tambah Kelas Dulu"
            @confirm="
                simpanModalOpen = false;
                simpanKrs(true);
            "
        />
        <AlertModal
            v-model:open="batalModalOpen"
            title="Batalkan kelas ini?"
            description="Kelas akan dihapus dari KRS Anda dan kursinya dilepas untuk mahasiswa lain."
            confirm-text="Batalkan Kelas"
            cancel-text="Kembali"
            @confirm="confirmBatalKelas"
        />
    </AppLayout>
</template>
