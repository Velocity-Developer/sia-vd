<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Baris = {
    krs_id: number;
    kode_matkul: string | null;
    nama_matkul: string | null;
    sks: number;
    kode_kelas: string;
    kelas_id: number;
    menunggu_dosen: boolean;
    nilai_angka: number | null;
    huruf: string | null;
    divalidasi_at: string | null;
    divalidasi_oleh: string | null;
};

const props = defineProps<{
    mahasiswa: { id: number; nim: string | null; nama: string | null; prodi: string | null; angkatan: string | null };
    tahunAkademikId: number | null;
    tahunAkademikOptions: { id: number; name: string }[];
    nilai: Baris[];
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const tahunAkademikId = ref(props.tahunAkademikId);
const pilihTahun = () =>
    router.get(
        route('admin.validasi-nilai.show', { mahasiswa: props.mahasiswa.id, tahun_akademik_id: tahunAkademikId.value }),
        {},
        { preserveScroll: true },
    );

const tervalidasi = computed(() => props.nilai.filter((b) => b.divalidasi_at).length);
const siapValidasi = computed(() => props.nilai.filter((b) => b.huruf && !b.divalidasi_at && !b.menunggu_dosen).length);
const menungguDosen = computed(() => props.nilai.filter((b) => b.huruf && !b.divalidasi_at && b.menunggu_dosen).length);
const belumBernilai = computed(() => props.nilai.filter((b) => !b.huruf).length);

// Satu mata kuliah (baris) atau semua mata kuliah di tahun akademik ini.
const konfirmasi = ref<{ aksi: 'validasi' | 'batal'; baris: Baris | null } | null>(null);
const proses = ref(false);
const pesanKonfirmasi = computed(() => {
    const k = konfirmasi.value;
    if (!k) return '';
    const nama = props.mahasiswa.nama ?? '';
    if (k.baris) {
        return k.aksi === 'validasi'
            ? `Validasi nilai ${k.baris.nama_matkul} (${k.baris.huruf}) milik ${nama}? Nilainya akan terkunci.`
            : `Batalkan validasi nilai ${k.baris.nama_matkul} milik ${nama}? Nilainya bisa diubah lagi.`;
    }
    return k.aksi === 'validasi'
        ? `Validasi ${siapValidasi.value} nilai mata kuliah ${nama}? Nilainya akan terkunci.`
        : `Batalkan validasi ${tervalidasi.value} nilai mata kuliah ${nama}? Nilainya bisa diubah lagi.`;
});
const jalankan = () => {
    const k = konfirmasi.value;
    if (!k) return;
    router.post(
        route(k.aksi === 'validasi' ? 'admin.validasi-nilai.validasi' : 'admin.validasi-nilai.batal', props.mahasiswa.id),
        { tahun_akademik_id: props.tahunAkademikId, krs_id: k.baris?.krs_id ?? null },
        {
            preserveScroll: true,
            onStart: () => (proses.value = true),
            onFinish: () => {
                proses.value = false;
                konfirmasi.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Validasi Nilai ${props.mahasiswa.nama ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Validasi Nilai', href: route('admin.validasi-nilai.index', { tahun_akademik_id: props.tahunAkademikId }) },
            { title: props.mahasiswa.nim ?? '-', href: route('admin.validasi-nilai.show', props.mahasiswa.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.mahasiswa.nama }}</h1>
                        <p class="deskripsi-halaman">
                            NIM {{ props.mahasiswa.nim ?? '-' }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan ?? '-' }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="route('admin.validasi-nilai.index', { tahun_akademik_id: props.tahunAkademikId })">
                            <ArrowLeft class="size-4" /> Kembali
                        </Link>
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="bilah-filter">
                    <SelectFilter v-model="tahunAkademikId" label="Tahun akademik" @change="pilihTahun">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah">
                        <span class="font-medium text-black dark:text-foreground">{{ tervalidasi }}</span> dari {{ props.nilai.length }} mata kuliah
                        tervalidasi
                    </p>
                    <div v-if="props.nilai.length" class="flex flex-wrap gap-2 sm:ml-auto">
                        <Button v-if="tervalidasi" variant="outline" class="text-[#dd5b00]" @click="konfirmasi = { aksi: 'batal', baris: null }">
                            <Undo2 class="size-4" /> Batalkan Semua
                        </Button>
                        <Button :disabled="!siapValidasi" @click="konfirmasi = { aksi: 'validasi', baris: null }">
                            <BadgeCheck class="size-4" /> Validasi Semua{{ siapValidasi ? ` (${siapValidasi})` : '' }}
                        </Button>
                    </div>
                </div>

                <p v-if="belumBernilai || menungguDosen" class="alert-info" role="status">
                    <template v-if="belumBernilai">{{ belumBernilai }} mata kuliah belum bernilai sehingga belum bisa divalidasi. </template>
                    <template v-if="menungguDosen"
                        >{{ menungguDosen }} nilai belum dikirim dosen pengampu ke validasi; nilainya bisa divalidasi setelah dosen menekan Kirim ke
                        Validasi di halaman kelas.</template
                    >
                </p>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode MK</th>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th class="text-right">Nilai Akhir</th>
                                    <th class="text-center">Huruf</th>
                                    <th>Validasi Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.nilai" :key="b.krs_id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="whitespace-nowrap">{{ b.kode_matkul ?? '-' }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama_matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.sks }} SKS</span>
                                    </td>
                                    <td class="whitespace-nowrap">{{ b.kode_kelas }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ b.nilai_angka ?? '-' }}</td>
                                    <td class="text-center font-semibold">{{ b.huruf ?? '-' }}</td>
                                    <td>
                                        <div v-if="b.divalidasi_at" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <div>
                                                <span
                                                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#ecfdf3] px-2 py-0.5 text-xs font-medium text-[#067647]"
                                                    ><BadgeCheck class="size-3.5" /> Tervalidasi</span
                                                >
                                                <span class="mt-0.5 block text-xs text-[#a39e98]">
                                                    {{ formatTanggal(b.divalidasi_at)
                                                    }}<template v-if="b.divalidasi_oleh"> · {{ b.divalidasi_oleh }}</template>
                                                </span>
                                            </div>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="text-[#dd5b00]"
                                                @click="konfirmasi = { aksi: 'batal', baris: b }"
                                            >
                                                Batalkan
                                            </Button>
                                        </div>
                                        <span v-else-if="b.huruf && b.menunggu_dosen" class="text-xs text-[#615d59]"
                                            >Belum dikirim dosen ·
                                            <Link :href="route('admin.kelas-kuliah.show', b.kelas_id)" class="text-[#0075de] hover:underline"
                                                >buka kelas</Link
                                            ></span
                                        >
                                        <Button v-else-if="b.huruf" size="sm" variant="outline" @click="konfirmasi = { aksi: 'validasi', baris: b }">
                                            <BadgeCheck class="size-4" /> Validasi
                                        </Button>
                                        <span v-else class="whitespace-nowrap text-xs text-[#615d59]">Belum bernilai</span>
                                    </td>
                                </tr>
                                <tr v-if="!props.nilai.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Mahasiswa ini tidak punya KRS pada tahun akademik ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="teks-bantu">
                    Nilai yang divalidasi terkunci dan masuk KHS serta transkrip. Untuk koreksi, batalkan validasinya lalu kembalikan nilai kelas ke
                    dosen dari halaman kelas. Perbaikan lewat remidi membuka validasi otomatis; nilainya kembali menunggu validasi di sini.
                </p>

                <AlertModal
                    :open="konfirmasi !== null"
                    :description="pesanKonfirmasi"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    :loading="proses"
                    @update:open="(v: boolean) => !v && (konfirmasi = null)"
                    @confirm="jalankan"
                    @cancel="konfirmasi = null"
                />
            </div>
        </div>
    </AppLayout>
</template>
