<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatJamDari, formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CircleCheck, FileUp, Paperclip } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
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
        kode_kelas: string;
        nama_matkul: string;
        soal: string[];
    };
    bolehIkut: boolean;
    sudahMulai: boolean;
    sudahSelesai: boolean;
    detikSampaiMulai: number | null;
    detikSampaiSelesai: number | null;
    jawaban: { nama_berkas: string[]; dikumpulkan_at: string | null; nilai: string | null; catatan_dosen: string | null } | null;
    nilaiDirilis: boolean;
    lembarSoal: { id: number; siap: boolean; jumlah_soal: number; total_poin: number; waktu_pengerjaan: number | null } | null;
    pengerjaan: { selesai: boolean; selesai_at: string | null; skor: string | null; nilai: number | null } | null;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const berlangsung = computed(() => props.sudahMulai && !props.sudahSelesai);

// Hitung mundur dari jam server; saat habis, halaman meminta status terbaru ke server.
const sisa = ref<number | null>(props.detikSampaiMulai ?? props.detikSampaiSelesai);
let detak: number | undefined;
watch(
    () => [props.detikSampaiMulai, props.detikSampaiSelesai],
    () => {
        sisa.value = props.detikSampaiMulai ?? props.detikSampaiSelesai;
        window.clearInterval(detak);
        if (sisa.value === null) return;
        detak = window.setInterval(() => {
            if (sisa.value === null) return;
            sisa.value -= 1;
            if (sisa.value <= 0) {
                window.clearInterval(detak);
                router.reload();
            }
        }, 1000);
    },
    { immediate: true },
);
onUnmounted(() => window.clearInterval(detak));
const format = (total: number) => {
    const j = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const d = total % 60;
    return j > 48 ? `${Math.floor(j / 24)} hari lagi` : [j, m, d].map((n) => String(n).padStart(2, '0')).join(':');
};

const form = useForm({ jawaban: [] as File[] });
const input = ref<HTMLInputElement | null>(null);
const pilih = (event: Event) => (form.jawaban = Array.from((event.target as HTMLInputElement).files ?? []));
const kumpulkan = () =>
    form.post(route('mahasiswa.ujian.kumpulkan', props.ujian.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (input.value) input.value.value = '';
        },
    });
// Mode soal di sistem: mulai (atau lanjutkan) di halaman pengerjaan quiz.
const mulaiForm = useForm({});
const mulaiUjian = () => props.lembarSoal && mulaiForm.post(route('mahasiswa.quiz.start', props.lembarSoal.id));

const galat = computed(() => Object.entries(form.errors).find(([k]) => k.startsWith('jawaban'))?.[1]);
const kartu = 'rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm';
</script>

<template>
    <Head :title="`${JENIS_UJIAN[props.ujian.jenis]} ${props.ujian.nama_matkul}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Jadwal Ujian', href: route('mahasiswa.ujian') },
            { title: `${JENIS_UJIAN[props.ujian.jenis]} ${props.ujian.kode_kelas}`, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[760px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <p class="text-sm text-[#615d59]">{{ props.ujian.kode_kelas }} · {{ props.ujian.label_mode }}</p>
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">
                        {{ JENIS_UJIAN[props.ujian.jenis] }} {{ props.ujian.nama_matkul }}
                    </h1>
                    <p class="text-sm text-[#31302e]">
                        {{ formatTanggal(props.ujian.tanggal) }}, {{ jam(props.ujian.jam_mulai) }}–{{ jam(props.ujian.jam_akhir) }} ·
                        {{ props.ujian.mode === 'tatap_muka' ? (props.ujian.ruang ?? '-') : 'Online di SIA' }}
                    </p>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="flex items-center gap-2 rounded-xl border border-[#b7e4c2] bg-[#e8f7ec] px-4 py-3 text-sm text-[#1a7f37]"
                    role="alert"
                >
                    <CircleCheck class="size-4 shrink-0" /> {{ page.props.flash.success }}
                </div>

                <p
                    v-if="props.ujian.petunjuk"
                    class="whitespace-pre-line rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#31302e]"
                >
                    {{ props.ujian.petunjuk }}
                </p>

                <div v-if="!props.bolehIkut" class="rounded-xl border border-[#f4c3bd] bg-[#fdecea] px-4 py-3 text-sm text-[#b42318]">
                    Anda belum memenuhi syarat kehadiran untuk mengikuti ujian ini. Hubungi dosen atau kaprodi bila ada dispensasi.
                </div>

                <section v-if="props.ujian.mode === 'tatap_muka'" :class="kartu">
                    <p class="text-sm text-[#31302e]">Ujian dilaksanakan di ruang {{ props.ujian.ruang ?? '-' }}. Bawa kartu ujian.</p>
                    <div v-if="props.nilaiDirilis && props.jawaban" class="mt-3 rounded-lg bg-[#f6f5f4] px-4 py-3 text-sm">
                        <p class="font-medium text-black">Nilai: {{ props.jawaban.nilai ?? 'belum dinilai' }}</p>
                        <p v-if="props.jawaban.catatan_dosen" class="mt-1 text-[#615d59]">Catatan dosen: {{ props.jawaban.catatan_dosen }}</p>
                    </div>
                    <Link :href="route('mahasiswa.ujian')" class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Kembali ke jadwal ujian</Link
                    >
                </section>

                <template v-else-if="props.ujian.mode === 'online_berkas'">
                    <section :class="kartu">
                        <h2 class="text-lg font-semibold text-black">Soal</h2>
                        <p v-if="!props.sudahMulai" class="mt-2 text-sm text-[#615d59]">
                            Soal tersedia saat ujian dimulai<template v-if="sisa !== null"
                                >, dalam <span class="font-mono font-medium text-black">{{ format(sisa) }}</span></template
                            >.
                        </p>
                        <ul v-else-if="props.ujian.soal.length" class="mt-3 space-y-1.5">
                            <li v-for="(nama, i) in props.ujian.soal" :key="nama">
                                <a
                                    :href="route('berkas.ujian-soal', [props.ujian.id, i])"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1.5 text-sm text-[#0075de] hover:underline"
                                >
                                    <Paperclip class="size-4" /> {{ nama }}
                                </a>
                            </li>
                        </ul>
                        <p v-else-if="props.bolehIkut" class="mt-2 text-sm text-[#615d59]">Dosen belum mengunggah berkas soal.</p>
                    </section>

                    <section :class="kartu">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="text-lg font-semibold text-black">Jawaban Anda</h2>
                            <span v-if="berlangsung && sisa !== null" class="rounded-full bg-[#eaf3fd] px-3 py-1 font-mono text-sm text-[#0b62b5]">
                                Sisa waktu {{ format(sisa) }}
                            </span>
                        </div>
                        <div v-if="props.jawaban" class="mt-3 text-sm">
                            <p class="text-[#1a7f37]">Terkumpul pukul {{ formatJamDari(props.jawaban.dikumpulkan_at) }}:</p>
                            <ul class="mt-1 list-inside list-disc text-[#31302e]">
                                <li v-for="nama in props.jawaban.nama_berkas" :key="nama">{{ nama }}</li>
                            </ul>
                        </div>
                        <p v-else-if="props.sudahSelesai" class="mt-3 text-sm text-[#b42318]">Anda tidak mengumpulkan jawaban.</p>

                        <form v-if="berlangsung && props.bolehIkut" class="mt-4 flex flex-wrap items-center gap-3" @submit.prevent="kumpulkan">
                            <input ref="input" type="file" multiple class="text-sm" aria-label="Pilih berkas jawaban" @change="pilih" />
                            <Button
                                type="submit"
                                class="bg-[#0075de] text-white hover:bg-[#005bab]"
                                :disabled="form.processing || !form.jawaban.length"
                            >
                                <FileUp class="mr-1 size-4" /> {{ props.jawaban ? 'Ganti jawaban' : 'Kumpulkan' }}
                            </Button>
                            <p class="w-full text-xs text-[#a39e98]">
                                Maks. 5 berkas @20 MB. Jawaban bisa diganti selama waktu ujian berjalan; lewat jam selesai tidak bisa dikumpulkan
                                lagi.
                            </p>
                            <InputError class="w-full" :message="galat" />
                        </form>
                        <p v-else-if="props.sudahSelesai" class="mt-3 text-sm text-[#615d59]">Waktu ujian sudah habis.</p>

                        <div v-if="props.nilaiDirilis && props.jawaban" class="mt-4 rounded-lg bg-[#f6f5f4] px-4 py-3 text-sm">
                            <p class="font-medium text-black">Nilai: {{ props.jawaban.nilai ?? 'belum dinilai' }}</p>
                            <p v-if="props.jawaban.catatan_dosen" class="mt-1 text-[#615d59]">Catatan dosen: {{ props.jawaban.catatan_dosen }}</p>
                        </div>
                    </section>
                </template>

                <section v-else-if="props.ujian.mode === 'online_soal'" :class="kartu">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h2 class="text-lg font-semibold text-black">Soal ujian</h2>
                        <span v-if="berlangsung && sisa !== null" class="rounded-full bg-[#eaf3fd] px-3 py-1 font-mono text-sm text-[#0b62b5]">
                            Ujian ditutup dalam {{ format(sisa) }}
                        </span>
                    </div>
                    <p v-if="props.lembarSoal" class="mt-1 text-sm text-[#615d59]">
                        {{ props.lembarSoal.jumlah_soal }} soal ·
                        {{
                            props.lembarSoal.waktu_pengerjaan
                                ? `durasi ${props.lembarSoal.waktu_pengerjaan} menit sejak mulai`
                                : 'dikerjakan sampai jam selesai'
                        }}
                        · satu kali kesempatan, jawaban tersimpan otomatis.
                    </p>

                    <p v-if="!props.sudahMulai" class="mt-3 text-sm text-[#615d59]">
                        Ujian bisa dimulai pukul {{ jam(props.ujian.jam_mulai)
                        }}<template v-if="sisa !== null"
                            >, dalam <span class="font-mono font-medium text-black">{{ format(sisa) }}</span></template
                        >.
                    </p>
                    <template v-else-if="props.pengerjaan?.selesai">
                        <p class="mt-3 flex items-center gap-1.5 text-sm text-[#1a7f37]">
                            <CircleCheck class="size-4" /> Jawaban terkirim pukul {{ formatJamDari(props.pengerjaan.selesai_at) }}.
                        </p>
                        <p class="mt-2 text-sm text-[#31302e]">
                            {{
                                props.nilaiDirilis
                                    ? `Nilai: ${props.pengerjaan.nilai ?? '-'} (${props.pengerjaan.skor ?? '-'} dari ${props.lembarSoal?.total_poin} poin)`
                                    : 'Nilai akan terlihat setelah dosen merilisnya.'
                            }}
                        </p>
                    </template>
                    <template v-else-if="berlangsung && props.bolehIkut">
                        <Link
                            v-if="props.pengerjaan && props.lembarSoal"
                            :href="route('mahasiswa.quiz.show', props.lembarSoal.id)"
                            class="mt-4 inline-flex h-11 items-center rounded-full bg-[#0075de] px-6 text-sm font-medium text-white hover:bg-[#005bab]"
                            >Lanjutkan ujian</Link
                        >
                        <Button
                            v-else-if="props.lembarSoal?.siap"
                            class="mt-4 h-11 rounded-full bg-[#0075de] px-6 text-white hover:bg-[#005bab]"
                            :disabled="mulaiForm.processing"
                            @click="mulaiUjian"
                            >Mulai ujian</Button
                        >
                        <p v-else class="mt-3 text-sm text-[#dd5b00]">Soal belum tersedia. Hubungi dosen atau pengawas.</p>
                    </template>
                    <p v-else-if="props.sudahSelesai" class="mt-3 text-sm text-[#b42318]">
                        Waktu ujian sudah habis. Anda tidak mengerjakan ujian ini.
                    </p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
