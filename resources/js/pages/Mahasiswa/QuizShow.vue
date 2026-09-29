<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type Option = { text: string };
type Question = {
    id: number;
    question_text: string;
    question_type: string;
    question_option?: Option[] | null;
    points?: number | null;
};
type Kelas = {
    id: number;
    kode_kelas: string;
    mataKuliah?: { nama_matkul: string } | null;
    mata_kuliah?: { nama_matkul: string } | null;
};
type Quiz = {
    id: number;
    nama_quiz: string;
    catatan?: string | null;
    waktu_pengerjaan?: number | null;
    tenggat_waktu?: string | null;
    kelasKuliah?: Kelas | null;
    kelas_kuliah?: Kelas | null;
    questions?: Question[];
};
type AttemptAnswer = { question_id: number; answer: string[] | null };
type Attempt = {
    id: number;
    started_at: string;
    submitted_at?: string | null;
    score?: string | number | null;
    auto_closed?: boolean;
    answers?: AttemptAnswer[];
} | null;

type PageProps = { flash?: { success?: string; error?: string } };

const props = defineProps<{
    quiz: Quiz;
    attempt: Attempt;
    deadline: string | null;
    serverNow: string;
    essayBelumDinilai?: boolean;
    /** Terisi bila quiz ini lembar soal ujian online. */
    ujian?: { id: number; jenis: string; nilai_dirilis: boolean } | null;
}>();
const page = usePage<PageProps>();
const kelas = computed(() => props.quiz.kelasKuliah ?? props.quiz.kelas_kuliah ?? null);
const answers = ref<Record<number, string | string[]>>({});
const remainingSeconds = ref(0);
const expired = ref(false);
const saveStatus = ref<'idle' | 'saving' | 'saved' | 'error'>('idle');
let timer: number | undefined;
let heartbeat: number | undefined;
let saveTimeout: number | undefined;
let retryTimeout: number | undefined;
let hydrating = false;

// Selisih jam perangkat terhadap jam server, agar hitung mundur tidak bergantung pada jam laptop/HP mahasiswa.
let clockOffset = 0;
const serverTime = () => Date.now() + clockOffset;

const startForm = useForm({});
const submitForm = useForm<{ answers: Record<number, string | string[]>; auto_submit: boolean }>({ answers: {}, auto_submit: false });
const hasAttempt = computed(() => props.attempt !== null);
const submitted = computed(() => Boolean(props.attempt?.submitted_at));
const tenggat = computed(() => (props.quiz.tenggat_waktu ? new Date(props.quiz.tenggat_waktu).getTime() : null));

const formatTenggat = (value: string | null | undefined): string => {
    if (!value) return '-';

    const [date, time] = value.replace('T', ' ').split(' ');
    const [year, month, day] = date.split('-');
    const [hour = '00', minute = '00'] = (time ?? '').split(':');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return `${day} ${months[Number(month) - 1]} ${year}, ${hour}:${minute}`;
};

const durationLabel = computed(() => (props.quiz.waktu_pengerjaan ? `${props.quiz.waktu_pengerjaan} menit` : 'Tidak ada batas waktu pengerjaan'));
const timeLabel = computed(
    () =>
        `${Math.floor(remainingSeconds.value / 60)
            .toString()
            .padStart(2, '0')}:${(remainingSeconds.value % 60).toString().padStart(2, '0')}`,
);
const saveLabel = computed(
    () => ({ idle: '', saving: 'Menyimpan…', saved: 'Jawaban tersimpan otomatis', error: 'Gagal menyimpan, akan dicoba lagi' })[saveStatus.value],
);
const deadlinePassed = () => tenggat.value !== null && tenggat.value <= serverTime();

const stopTimers = () => {
    window.clearInterval(timer);
    window.clearInterval(heartbeat);
    window.clearTimeout(saveTimeout);
    window.clearTimeout(retryTimeout);
    timer = heartbeat = saveTimeout = retryTimeout = undefined;
};

const xsrfToken = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

const saveAnswers = async () => {
    if (!props.attempt || submitted.value || expired.value) return;

    saveStatus.value = 'saving';

    try {
        const response = await fetch(route('mahasiswa.quiz.answers', props.quiz.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ answers: answers.value }),
        });

        if (response.status === 409) {
            stopTimers();
            router.reload();

            return;
        }

        saveStatus.value = response.ok ? 'saved' : 'error';
    } catch {
        saveStatus.value = 'error';
    }
};

const scheduleSave = () => {
    window.clearTimeout(saveTimeout);
    saveTimeout = window.setTimeout(saveAnswers, 1500);
};

const submitQuiz = (autoSubmit = false) => {
    if (submitted.value || submitForm.processing) return;

    window.clearTimeout(saveTimeout);
    submitForm.answers = { ...answers.value };
    submitForm.auto_submit = autoSubmit;
    submitForm.post(route('mahasiswa.quiz.submit', props.quiz.id), {
        preserveScroll: true,
        onFinish: () => {
            // Kiriman otomatis gagal (mis. koneksi putus): muat ulang berkala sampai server menutup attempt.
            if (autoSubmit && !submitted.value) {
                retryTimeout = window.setTimeout(() => router.reload(), 15000);
            }
        },
    });
};

const expireQuiz = () => {
    expired.value = true;
    stopTimers();
    submitQuiz(true);
};

const tick = () => {
    if (!props.attempt || submitted.value || !props.deadline) return;

    remainingSeconds.value = Math.max(0, Math.ceil((new Date(props.deadline).getTime() - serverTime()) / 1000));

    if (remainingSeconds.value === 0) expireQuiz();
};

const startQuiz = () => {
    startForm.post(route('mahasiswa.quiz.start', props.quiz.id));
};

const syncAttempt = () => {
    stopTimers();
    clockOffset = new Date(props.serverNow).getTime() - Date.now();

    const attempt = props.attempt;

    if (!attempt) {
        expired.value = deadlinePassed();

        return;
    }

    const questions = new Map((props.quiz.questions ?? []).map((question) => [question.id, question]));
    const next: Record<number, string | string[]> = {};

    // Checkbox pilihan ganda harus terikat ke array; tanpa ini Vue memperlakukannya sebagai satu nilai true/false.
    for (const question of questions.values()) {
        if (question.question_type === 'multiple_choice') next[question.id] = [];
    }

    for (const answer of attempt.answers ?? []) {
        if (answer.answer === null) continue;

        next[answer.question_id] = questions.get(answer.question_id)?.question_type === 'multiple_choice' ? answer.answer : (answer.answer[0] ?? '');
    }

    hydrating = true;
    answers.value = next;

    if (attempt.submitted_at) return;

    expired.value = false;
    tick();
    timer = window.setInterval(tick, 1000);
    // Simpan berkala sekaligus menjaga sesi login tetap aktif selama quiz panjang.
    heartbeat = window.setInterval(saveAnswers, 5 * 60 * 1000);
};

watch(
    answers,
    () => {
        if (hydrating) {
            hydrating = false;

            return;
        }

        scheduleSave();
    },
    { deep: true },
);
watch(() => props.attempt, syncAttempt, { immediate: true });

onBeforeUnmount(stopTimers);
</script>

<template>
    <Head :title="props.quiz.nama_quiz" />
    <AppLayout
        :breadcrumbs="
            props.ujian
                ? [
                      { title: 'Jadwal Ujian', href: route('mahasiswa.ujian') },
                      { title: props.quiz.nama_quiz, href: route('mahasiswa.ujian.show', props.ujian.id) },
                  ]
                : [
                      { title: 'Jadwal Kuliah', href: route('mahasiswa.jadwal-kuliah') },
                      { title: 'Detail Kelas', href: kelas ? route('mahasiswa.jadwal-kuliah.show', kelas.id) : '#' },
                      { title: props.quiz.nama_quiz, href: '#' },
                  ]
        "
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.quiz.nama_quiz }}</h1>
                        <p class="deskripsi-halaman">
                            {{ kelas?.kode_kelas ?? '-' }} · {{ kelas?.mataKuliah?.nama_matkul ?? kelas?.mata_kuliah?.nama_matkul ?? '-' }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <Link
                            :href="
                                props.ujian
                                    ? route('mahasiswa.ujian.show', props.ujian.id)
                                    : kelas
                                      ? route('mahasiswa.jadwal-kuliah.show', kelas.id)
                                      : route('mahasiswa.jadwal-kuliah')
                            "
                            >Kembali</Link
                        >
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Quiz</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="teks-bantu">Nama Quiz</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ props.quiz.nama_quiz }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Waktu Pengerjaan</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ durationLabel }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Tenggat Pengerjaan</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ formatTenggat(props.quiz.tenggat_waktu) }}</dd>
                        </div>
                    </dl>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5 dark:border-border">
                        <h3 class="judul-bagian">Penjelasan</h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-[#31302e] dark:text-foreground">{{ props.quiz.catatan || '-' }}</p>
                    </div>
                </section>

                <section v-if="submitted" class="kartu p-6">
                    <div v-if="props.attempt?.auto_closed" class="alert-gagal">
                        Waktu quiz telah habis. Jawaban terakhir yang tersimpan otomatis sudah dinilai.
                    </div>
                    <div v-else class="alert-sukses">Quiz berhasil ter-submit.</div>
                    <p
                        v-if="props.attempt?.score !== null && props.attempt?.score !== undefined"
                        class="mt-3 text-sm text-[#615d59] dark:text-muted-foreground"
                    >
                        Score: {{ props.attempt.score }}
                    </p>
                    <p v-if="props.ujian && !props.ujian.nilai_dirilis" class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                        Nilai ujian akan terlihat setelah dosen merilisnya.
                    </p>
                    <p v-if="props.essayBelumDinilai" class="teks-bantu mt-1">
                        Jawaban esai masih dikoreksi dosen; score akan bertambah setelah dinilai.
                    </p>
                </section>
                <section v-else-if="!hasAttempt" class="kartu p-6">
                    <div v-if="expired || deadlinePassed()" class="alert-gagal">Tenggat quiz telah berakhir. Quiz tidak dapat dimulai.</div>
                    <div v-else class="flex flex-wrap items-center justify-between gap-4">
                        <p class="text-sm text-[#615d59] dark:text-muted-foreground">Pastikan siap sebelum menekan tombol mulai.</p>
                        <Button :disabled="startForm.processing" @click="startQuiz">Start</Button>
                    </div>
                </section>
                <section v-else-if="expired" class="kartu p-6">
                    <div class="alert-gagal">Waktu quiz telah habis. Jawaban Anda sedang dikirim otomatis.</div>
                </section>
                <form v-else class="kartu p-6" @submit.prevent="submitQuiz()">
                    <div
                        class="sticky top-4 z-10 mb-6 flex items-center justify-between rounded-lg border border-[#e6e6e6] bg-white px-4 py-3 dark:border-border dark:bg-card"
                    >
                        <div>
                            <span class="text-sm font-medium text-[#615d59] dark:text-muted-foreground">Sisa waktu</span
                            ><span class="block text-xs" :class="saveStatus === 'error' ? 'text-[#dd5b00]' : 'text-[#a39e98]'" aria-live="polite">{{
                                saveLabel
                            }}</span>
                        </div>
                        <span class="text-xl font-bold text-[#0075de]">{{ props.deadline ? timeLabel : 'Tanpa batas' }}</span>
                    </div>
                    <div class="space-y-6">
                        <article
                            v-for="(question, index) in props.quiz.questions ?? []"
                            :key="question.id"
                            class="border-b border-[#e6e6e6] pb-5 last:border-0 dark:border-border"
                        >
                            <h2 class="text-sm font-medium text-black dark:text-foreground">{{ index + 1 }}. {{ question.question_text }}</h2>
                            <textarea
                                v-if="question.question_type === 'essay'"
                                v-model="answers[question.id]"
                                class="isian isian-area mt-3"
                                placeholder="Tulis jawaban..."
                            />
                            <div v-else class="mt-3 grid gap-2">
                                <label
                                    v-for="option in question.question_option ?? []"
                                    :key="option.text"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-[#e6e6e6] px-3 py-2 text-sm text-[#31302e] hover:bg-[#fbfaf9] dark:border-border dark:text-foreground dark:hover:bg-accent/40"
                                    ><input
                                        v-model="answers[question.id]"
                                        :type="question.question_type === 'multiple_choice' ? 'checkbox' : 'radio'"
                                        :name="`question-${question.id}`"
                                        :value="option.text"
                                        class="size-4 shrink-0 accent-[#0075de]"
                                    />{{ option.text }}</label
                                >
                            </div>
                        </article>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="submitForm.processing">Submit Quiz</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
