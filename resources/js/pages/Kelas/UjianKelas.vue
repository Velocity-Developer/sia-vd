<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatJamDari, formatTanggal, jam } from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { JENIS_UJIAN, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FileUp, Paperclip, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type Syarat = { persen: number | null; memenuhi: boolean | null; dispensasi: { alasan: string } | null } | null;
type Peserta = {
    mahasiswa_id: number;
    nim: string;
    nama: string;
    syarat: Syarat;
    jawaban: {
        id: number;
        jumlah_berkas: number;
        nama_berkas: string[];
        dikumpulkan_at: string | null;
        nilai: string | null;
        catatan_dosen: string | null;
        penilai: string | null;
    } | null;
};

const props = defineProps<{
    peran: Peran;
    kelasKuliah: { id: number; kode_kelas: string; mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null };
    ujian: {
        id: number;
        jenis: JenisUjian;
        mode: ModeUjian;
        tanggal: string;
        jam_mulai: string;
        jam_akhir: string;
        label_mode: string;
        ruang: string | null;
        pengawas: string | null;
        petunjuk: string | null;
        status: 'draf' | 'terbit';
        nilai_dirilis: boolean;
        soal: string[];
    };
    peserta: Peserta[];
    syaratAktif: boolean;
    sudahMulai: boolean;
    sudahSelesai: boolean;
    terkunci: boolean;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const rute = rutePeran(props.peran);
const berkasMode = computed(() => props.ujian.mode === 'online_berkas');
const terkumpul = computed(() => props.peserta.filter((p) => p.jawaban).length);

// Unggah soal (sebelum ujian dimulai).
const soalForm = useForm({ soal: [] as File[] });
const inputSoal = ref<HTMLInputElement | null>(null);
const pilihSoal = (event: Event) => (soalForm.soal = Array.from((event.target as HTMLInputElement).files ?? []));
const unggahSoal = () =>
    soalForm.post(rute('ujian.soal.unggah', props.ujian.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            soalForm.reset();
            if (inputSoal.value) inputSoal.value.value = '';
        },
    });
const hapusSoal = (index: number) => router.delete(rute('ujian.soal.hapus', [props.ujian.id, index]), { preserveScroll: true });
const errorSoal = computed(() => Object.entries(soalForm.errors).find(([k]) => k.startsWith('soal'))?.[1]);

// Nilai per jawaban.
const nilai = reactive<Record<number, { nilai: string; catatan: string }>>(
    Object.fromEntries(
        props.peserta
            .filter((p) => p.jawaban)
            .map((p) => [p.jawaban!.id, { nilai: p.jawaban!.nilai ?? '', catatan: p.jawaban!.catatan_dosen ?? '' }]),
    ),
);
const menyimpan = ref<number | null>(null);
const simpanNilai = (jawabanId: number) => {
    menyimpan.value = jawabanId;
    router.put(
        rute('ujian.jawaban.nilai', [props.ujian.id, jawabanId]),
        { nilai: nilai[jawabanId].nilai === '' ? null : nilai[jawabanId].nilai, catatan_dosen: nilai[jawabanId].catatan || null },
        { preserveScroll: true, onFinish: () => (menyimpan.value = null) },
    );
};
const rilis = (nilaiDirilis: boolean) =>
    router.put(rute('ujian.rilis-nilai', props.ujian.id), { nilai_dirilis: nilaiDirilis }, { preserveScroll: true });

const labelSyarat = (s: Syarat) => (s?.dispensasi ? 'Dispensasi' : s?.memenuhi === false ? 'Tidak memenuhi' : s?.memenuhi ? 'Memenuhi' : '-');
const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
const kartu = 'rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm';
</script>

<template>
    <Head :title="`${JENIS_UJIAN[props.ujian.jenis]} ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            {
                title: props.peran === 'admin' ? 'Jadwal Ujian' : 'Ujian',
                href: props.peran === 'admin' ? route('admin.ujian.index') : route('dosen.ujian.index'),
            },
            { title: `${JENIS_UJIAN[props.ujian.jenis]} ${props.kelasKuliah.kode_kelas}`, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <p class="text-sm text-[#615d59]">{{ props.kelasKuliah.mata_kuliah?.nama_matkul }} · {{ props.kelasKuliah.kode_kelas }}</p>
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">
                        {{ JENIS_UJIAN[props.ujian.jenis] }} — {{ props.ujian.label_mode }}
                    </h1>
                    <p class="text-sm text-[#31302e]">
                        {{ formatTanggal(props.ujian.tanggal) }}, {{ jam(props.ujian.jam_mulai) }}–{{ jam(props.ujian.jam_akhir) }} ·
                        {{ props.ujian.mode === 'tatap_muka' ? (props.ujian.ruang ?? '-') : 'Online di SIA' }}
                        <span v-if="props.ujian.status === 'draf'" class="ml-1 rounded bg-[#f6f5f4] px-1.5 text-xs text-[#615d59]"
                            >Draf – belum tampil ke mahasiswa</span
                        >
                    </p>
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

                <p
                    v-if="props.ujian.petunjuk"
                    class="whitespace-pre-line rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#31302e]"
                >
                    {{ props.ujian.petunjuk }}
                </p>

                <!-- Soal (mode unggah berkas) -->
                <section v-if="berkasMode" :class="kartu">
                    <h2 class="text-lg font-semibold text-black">Berkas soal</h2>
                    <p class="mt-1 text-sm text-[#615d59]">
                        Mahasiswa baru bisa mengunduh soal saat ujian dimulai. Soal hanya bisa diubah sebelum jam mulai.
                    </p>
                    <ul v-if="props.ujian.soal.length" class="mt-3 divide-y divide-[#e6e6e6] rounded-lg border border-[#e6e6e6]">
                        <li v-for="(nama, i) in props.ujian.soal" :key="nama" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <a
                                :href="route('berkas.ujian-soal', [props.ujian.id, i])"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 text-[#0075de] hover:underline"
                            >
                                <Paperclip class="size-4" /> {{ nama }}
                            </a>
                            <Button
                                v-if="!props.sudahMulai"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-[#dd5b00]"
                                :aria-label="`Hapus ${nama}`"
                                @click="hapusSoal(i)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-[#dd5b00]">Belum ada berkas soal.</p>
                    <form v-if="!props.sudahMulai" class="mt-4 flex flex-wrap items-center gap-3" @submit.prevent="unggahSoal">
                        <input ref="inputSoal" type="file" multiple class="text-sm" aria-label="Pilih berkas soal" @change="pilihSoal" />
                        <Button
                            type="submit"
                            class="bg-[#0075de] text-white hover:bg-[#005bab]"
                            :disabled="soalForm.processing || !soalForm.soal.length"
                        >
                            <FileUp class="mr-1 size-4" /> Unggah soal
                        </Button>
                        <InputError class="w-full" :message="errorSoal" />
                    </form>
                </section>

                <!-- Pengumpulan & nilai -->
                <section v-if="berkasMode" :class="kartu">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-black">Jawaban mahasiswa</h2>
                            <p class="mt-1 text-sm text-[#615d59]">
                                {{ terkumpul }} dari {{ props.peserta.length }} mahasiswa mengumpulkan.
                                <template v-if="!props.sudahSelesai">Nilai diisi setelah ujian selesai.</template>
                            </p>
                        </div>
                        <div v-if="props.sudahSelesai && !props.terkunci" class="flex items-center gap-2 text-sm">
                            <span :class="props.ujian.nilai_dirilis ? 'text-[#1a7f37]' : 'text-[#615d59]'">
                                {{ props.ujian.nilai_dirilis ? 'Nilai terlihat oleh mahasiswa' : 'Nilai belum dirilis' }}
                            </span>
                            <Button variant="outline" size="sm" @click="rilis(!props.ujian.nilai_dirilis)">
                                {{ props.ujian.nilai_dirilis ? 'Sembunyikan' : 'Rilis nilai' }}
                            </Button>
                        </div>
                    </div>
                    <div class="mt-4 overflow-hidden rounded-lg border border-[#e6e6e6]">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[820px] text-left text-sm">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th :class="th">Mahasiswa</th>
                                        <th v-if="props.syaratAktif" :class="th">Syarat</th>
                                        <th :class="th">Jawaban</th>
                                        <th :class="th">Nilai (0–100)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="p in props.peserta" :key="p.mahasiswa_id">
                                        <td class="px-4 py-2.5">
                                            <span class="block font-medium text-black">{{ p.nama }}</span>
                                            <span class="block text-xs text-[#a39e98]">{{ p.nim }}</span>
                                        </td>
                                        <td
                                            v-if="props.syaratAktif"
                                            class="px-4 py-2.5"
                                            :class="p.syarat?.memenuhi === false && !p.syarat?.dispensasi ? 'text-[#b42318]' : ''"
                                        >
                                            {{ labelSyarat(p.syarat) }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <template v-if="p.jawaban">
                                                <a
                                                    v-for="(nama, i) in p.jawaban.nama_berkas"
                                                    :key="nama"
                                                    :href="route('berkas.ujian-jawaban', [p.jawaban.id, i])"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="block text-[#0075de] hover:underline"
                                                    >{{ nama }}</a
                                                >
                                                <span class="text-xs text-[#a39e98]">Dikumpulkan {{ formatJamDari(p.jawaban.dikumpulkan_at) }}</span>
                                            </template>
                                            <span v-else class="text-[#a39e98]">Belum mengumpulkan</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <form
                                                v-if="p.jawaban && props.sudahSelesai && !props.terkunci"
                                                class="flex flex-wrap items-center gap-2"
                                                @submit.prevent="simpanNilai(p.jawaban.id)"
                                            >
                                                <Input
                                                    v-model="nilai[p.jawaban.id].nilai"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    class="h-9 w-24"
                                                    :aria-label="`Nilai ${p.nama}`"
                                                />
                                                <Input
                                                    v-model="nilai[p.jawaban.id].catatan"
                                                    maxlength="1000"
                                                    placeholder="Catatan (opsional)"
                                                    class="h-9 w-48"
                                                />
                                                <Button type="submit" size="sm" variant="outline" :disabled="menyimpan === p.jawaban.id"
                                                    >Simpan</Button
                                                >
                                            </form>
                                            <span v-else-if="p.jawaban?.nilai">{{ p.jawaban.nilai }}</span>
                                            <span v-else class="text-[#a39e98]">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section v-if="props.ujian.mode === 'tatap_muka'" :class="kartu">
                    <p class="text-sm text-[#615d59]">
                        Ujian tatap muka: daftar hadir dicetak dari halaman
                        <Link :href="rute('presensi.kelas', props.kelasKuliah.id)" class="text-[#0075de] hover:underline">Presensi kelas</Link>
                        (tab Peserta Ujian).
                    </p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
