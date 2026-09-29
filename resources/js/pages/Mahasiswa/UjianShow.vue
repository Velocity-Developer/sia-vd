<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatJamDari, formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, STATUS_PENGAJUAN_SUSULAN, type JenisUjian, type ModeUjian, type StatusPengajuanSusulan } from '@/lib/ujian';
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
    alasanTidakIkut: string | null;
    sudahMulai: boolean;
    sudahSelesai: boolean;
    detikSampaiMulai: number | null;
    detikSampaiSelesai: number | null;
    jawaban: { nama_berkas: string[]; dikumpulkan_at: string | null; nilai: string | null; catatan_dosen: string | null } | null;
    nilaiDirilis: boolean;
    lembarSoal: { id: number; siap: boolean; jumlah_soal: number; total_poin: number; waktu_pengerjaan: number | null } | null;
    pengerjaan: { selesai: boolean; selesai_at: string | null; skor: string | null; nilai: number | null } | null;
    susulan: {
        pengajuan: {
            id: number;
            status: StatusPengajuanSusulan;
            alasan: string;
            jumlah_lampiran: number;
            catatan_admin: string | null;
            diajukan_at: string | null;
        } | null;
        boleh_ajukan: boolean;
        alasan_tidak_boleh: string | null;
        batas: string;
    } | null;
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

// Pengajuan ujian susulan: alasan + bukti (PDF/JPG/PNG).
const formSusulan = useForm<{ alasan: string; lampiran: File[] }>({ alasan: '', lampiran: [] });
const kunciLampiran = ref(0);
const bukaFormSusulan = ref(false);
const pilihLampiran = (event: Event) => (formSusulan.lampiran = Array.from((event.target as HTMLInputElement).files ?? []));
const ajukanSusulan = () =>
    formSusulan.post(route('mahasiswa.ujian.susulan', props.ujian.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            formSusulan.reset();
            kunciLampiran.value++;
            bukaFormSusulan.value = false;
        },
    });
const galatLampiran = computed(() => Object.entries(formSusulan.errors).find(([k]) => k.startsWith('lampiran'))?.[1]);
const batalkanSusulan = () =>
    props.susulan?.pengajuan &&
    confirm('Batalkan pengajuan ujian susulan ini?') &&
    router.delete(route('mahasiswa.ujian-susulan.batalkan', props.susulan.pengajuan.id), { preserveScroll: true });

const galat = computed(() => Object.entries(form.errors).find(([k]) => k.startsWith('jawaban'))?.[1]);
</script>

<template>
    <Head :title="`${JENIS_UJIAN[props.ujian.jenis]} ${props.ujian.nama_matkul}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Jadwal Ujian', href: route('mahasiswa.ujian') },
            { title: `${JENIS_UJIAN[props.ujian.jenis]} ${props.ujian.kode_kelas}`, href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ JENIS_UJIAN[props.ujian.jenis] }} {{ props.ujian.nama_matkul }}</h1>
                        <p class="deskripsi-halaman">
                            {{ props.ujian.kode_kelas }} · {{ props.ujian.label_mode }} · {{ formatTanggal(props.ujian.tanggal) }},
                            {{ jam(props.ujian.jam_mulai) }}–{{ jam(props.ujian.jam_akhir) }} ·
                            {{ props.ujian.mode === 'tatap_muka' ? (props.ujian.ruang ?? '-') : 'Online di SIA' }}
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses flex items-center gap-2" role="alert">
                    <CircleCheck class="size-4 shrink-0" /> {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div v-if="props.ujian.petunjuk" class="alert-info whitespace-pre-line">{{ props.ujian.petunjuk }}</div>

                <div v-if="!props.bolehIkut" class="alert-gagal">
                    {{ props.alasanTidakIkut }}
                    <template v-if="props.alasanTidakIkut?.includes('syarat kehadiran')">Hubungi dosen atau kaprodi bila ada dispensasi.</template>
                </div>

                <section v-if="props.ujian.mode === 'tatap_muka'" class="kartu p-6">
                    <p class="text-sm text-[#31302e] dark:text-foreground">
                        Ujian dilaksanakan di ruang {{ props.ujian.ruang ?? '-' }}. Bawa kartu ujian.
                    </p>
                    <div v-if="props.nilaiDirilis && props.jawaban" class="mt-3 rounded-lg bg-[#f6f5f4] px-4 py-3 text-sm dark:bg-muted">
                        <p class="font-medium text-black dark:text-foreground">Nilai: {{ props.jawaban.nilai ?? 'belum dinilai' }}</p>
                        <p v-if="props.jawaban.catatan_dosen" class="mt-1 text-[#615d59] dark:text-muted-foreground">
                            Catatan dosen: {{ props.jawaban.catatan_dosen }}
                        </p>
                    </div>
                    <Link :href="route('mahasiswa.ujian')" class="mt-2 inline-block text-sm font-medium text-[#0075de] hover:underline"
                        >Kembali ke jadwal ujian</Link
                    >
                </section>

                <template v-else-if="props.ujian.mode === 'online_berkas'">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Soal</h2>
                        <p v-if="!props.sudahMulai" class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
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
                        <p v-else-if="props.bolehIkut" class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                            Dosen belum mengunggah berkas soal.
                        </p>
                    </section>

                    <section class="kartu p-6">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="judul-bagian">Jawaban Anda</h2>
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
                            <Button type="submit" :disabled="form.processing || !form.jawaban.length">
                                <FileUp class="size-4" /> {{ props.jawaban ? 'Ganti jawaban' : 'Kumpulkan' }}
                            </Button>
                            <p class="teks-bantu w-full">
                                Maks. 5 berkas @20 MB. Jawaban bisa diganti selama waktu ujian berjalan; lewat jam selesai tidak bisa dikumpulkan
                                lagi.
                            </p>
                            <InputError class="w-full" :message="galat" />
                        </form>
                        <p v-else-if="props.sudahSelesai" class="mt-3 text-sm text-[#615d59] dark:text-muted-foreground">Waktu ujian sudah habis.</p>

                        <div v-if="props.nilaiDirilis && props.jawaban" class="mt-4 rounded-lg bg-[#f6f5f4] px-4 py-3 text-sm dark:bg-muted">
                            <p class="font-medium text-black dark:text-foreground">Nilai: {{ props.jawaban.nilai ?? 'belum dinilai' }}</p>
                            <p v-if="props.jawaban.catatan_dosen" class="mt-1 text-[#615d59] dark:text-muted-foreground">
                                Catatan dosen: {{ props.jawaban.catatan_dosen }}
                            </p>
                        </div>
                    </section>
                </template>

                <section v-else-if="props.ujian.mode === 'online_soal'" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h2 class="judul-bagian">Soal ujian</h2>
                        <span v-if="berlangsung && sisa !== null" class="rounded-full bg-[#eaf3fd] px-3 py-1 font-mono text-sm text-[#0b62b5]">
                            Ujian ditutup dalam {{ format(sisa) }}
                        </span>
                    </div>
                    <p v-if="props.lembarSoal" class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                        {{ props.lembarSoal.jumlah_soal }} soal ·
                        {{
                            props.lembarSoal.waktu_pengerjaan
                                ? `durasi ${props.lembarSoal.waktu_pengerjaan} menit sejak mulai`
                                : 'dikerjakan sampai jam selesai'
                        }}
                        · satu kali kesempatan, jawaban tersimpan otomatis.
                    </p>

                    <p v-if="!props.sudahMulai" class="mt-3 text-sm text-[#615d59] dark:text-muted-foreground">
                        Ujian bisa dimulai pukul {{ jam(props.ujian.jam_mulai)
                        }}<template v-if="sisa !== null"
                            >, dalam <span class="font-mono font-medium text-black">{{ format(sisa) }}</span></template
                        >.
                    </p>
                    <template v-else-if="props.pengerjaan?.selesai">
                        <p class="mt-3 flex items-center gap-1.5 text-sm text-[#1a7f37]">
                            <CircleCheck class="size-4" /> Jawaban terkirim pukul {{ formatJamDari(props.pengerjaan.selesai_at) }}.
                        </p>
                        <p class="mt-2 text-sm text-[#31302e] dark:text-foreground">
                            {{
                                props.nilaiDirilis
                                    ? `Nilai: ${props.pengerjaan.nilai ?? '-'} (${props.pengerjaan.skor ?? '-'} dari ${props.lembarSoal?.total_poin} poin)`
                                    : 'Nilai akan terlihat setelah dosen merilisnya.'
                            }}
                        </p>
                    </template>
                    <template v-else-if="berlangsung && props.bolehIkut">
                        <Button v-if="props.pengerjaan && props.lembarSoal" as-child class="mt-4">
                            <Link :href="route('mahasiswa.quiz.show', props.lembarSoal.id)">Lanjutkan ujian</Link>
                        </Button>
                        <Button v-else-if="props.lembarSoal?.siap" class="mt-4" :disabled="mulaiForm.processing" @click="mulaiUjian"
                            >Mulai ujian</Button
                        >
                        <p v-else class="mt-3 text-sm text-[#dd5b00]">Soal belum tersedia. Hubungi dosen atau pengawas.</p>
                    </template>
                    <p v-else-if="props.sudahSelesai" class="mt-3 text-sm text-[#b42318]">
                        Waktu ujian sudah habis. Anda tidak mengerjakan ujian ini.
                    </p>
                </section>

                <section v-if="props.susulan && (props.susulan.pengajuan || props.susulan.boleh_ajukan)" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h2 class="judul-bagian">Ujian susulan</h2>
                        <span
                            v-if="props.susulan.pengajuan"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="STATUS_PENGAJUAN_SUSULAN[props.susulan.pengajuan.status].kelas"
                            >{{ STATUS_PENGAJUAN_SUSULAN[props.susulan.pengajuan.status].label }}</span
                        >
                    </div>
                    <template v-if="props.susulan.pengajuan">
                        <p class="mt-2 text-sm text-[#31302e] dark:text-foreground">
                            Diajukan {{ formatTanggal(props.susulan.pengajuan.diajukan_at) }}: {{ props.susulan.pengajuan.alasan }}
                        </p>
                        <p class="mt-1 flex flex-wrap gap-3 text-sm">
                            <a
                                v-for="i in props.susulan.pengajuan.jumlah_lampiran"
                                :key="i"
                                :href="route('berkas.lampiran-susulan', [props.susulan.pengajuan.id, i - 1])"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 font-medium text-[#0075de] hover:underline"
                                ><Paperclip class="size-3.5" /> Lampiran {{ i }}</a
                            >
                        </p>
                        <p v-if="props.susulan.pengajuan.catatan_admin" class="mt-2 text-sm text-[#b42318]">
                            Catatan admin: {{ props.susulan.pengajuan.catatan_admin }}
                        </p>
                        <p v-if="props.susulan.pengajuan.status === 'menunggu'" class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                            Menunggu persetujuan admin. Bila Anda tetap mengikuti ujian ini, pengajuan otomatis dibatalkan.
                            <button type="button" class="font-medium text-[#b42318] hover:underline" @click="batalkanSusulan">
                                Batalkan pengajuan
                            </button>
                        </p>
                        <p v-else-if="props.susulan.pengajuan.status === 'disetujui'" class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                            Disetujui. Tagihan ujian susulan akan muncul di menu Biaya Kuliah; jadwal susulan tampil setelah tagihan lunas.
                        </p>
                        <p v-else-if="props.susulan.pengajuan.status === 'gugur'" class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                            Anda tercatat mengikuti ujian utama, jadi hak ujian susulan gugur.
                        </p>
                    </template>
                    <template v-if="props.susulan.boleh_ajukan">
                        <p class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                            Tidak bisa mengikuti ujian ini? Ajukan ujian susulan paling lambat {{ formatTanggal(props.susulan.batas) }}, dengan alasan
                            dan bukti.
                        </p>
                        <Button v-if="!bukaFormSusulan" variant="outline" class="mt-3" @click="bukaFormSusulan = true">Ajukan ujian susulan</Button>
                        <form v-else class="mt-3 grid gap-3" @submit.prevent="ajukanSusulan">
                            <label class="grid gap-2">
                                <span class="label-isian">Alasan</span>
                                <textarea v-model="formSusulan.alasan" rows="3" maxlength="1000" required class="isian isian-area" />
                                <InputError :message="formSusulan.errors.alasan" />
                            </label>
                            <label class="grid gap-2">
                                <span class="label-isian">Bukti (wajib, PDF/JPG/PNG, maks 3 berkas × 5 MB)</span>
                                <input
                                    :key="kunciLampiran"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="text-sm"
                                    @change="pilihLampiran"
                                />
                                <InputError :message="galatLampiran" />
                            </label>
                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="bukaFormSusulan = false">Batal</Button>
                                <Button type="submit" :disabled="formSusulan.processing">Kirim pengajuan</Button>
                            </div>
                        </form>
                    </template>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
