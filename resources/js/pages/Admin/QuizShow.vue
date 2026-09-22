<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type QuestionOption = { text: string; is_correct: boolean };
type QuestionDetail = {
    id: number;
    question_text: string;
    question_type: string;
    question_option?: QuestionOption[] | string[] | Record<string, unknown> | string | null;
    points?: number | null;
};

type QuizAttempt = {
    id: number;
    started_at?: string | null;
    submitted_at?: string | null;
    score?: string | number | null;
    auto_closed?: boolean;
    mahasiswa?: { nim?: string | null; user?: { name: string } | null } | null;
};

type QuizDetail = {
    id: number;
    nama_quiz: string;
    catatan?: string | null;
    waktu_pengerjaan?: number | null;
    tenggat_waktu?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    uploader?: { name: string } | null;
    questions?: QuestionDetail[];
    attempts?: QuizAttempt[];
};

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

type KelasKuliahDetail = {
    id: number;
    kode_kelas: string;
    tahun_ajaran?: string | null;
};

const props = defineProps<{
    kelasKuliah: KelasKuliahDetail;
    quiz: QuizDetail;
}>();

const page = usePage<{
    flash: { question_success?: string; question_error?: string };
}>();

type DraftOption = { id: number; text: string; is_correct: boolean };
type DraftQuestion = {
    id: number;
    question_text: string;
    question_type: string;
    options: DraftOption[];
    points: number | '';
};

const QUESTION_TYPES = [
    { value: '', label: 'Pilih tipe pertanyaan' },
    { value: 'single_choice', label: 'Single Choice' },
    { value: 'multiple_choice', label: 'Multiple Choice' },
    { value: 'true_false', label: 'True or False' },
    { value: 'essay', label: 'Essay' },
] as const;

const TYPE_LABELS: Record<string, string> = {
    single_choice: 'Single Choice',
    multiple_choice: 'Multiple Choice',
    true_false: 'True or False',
    essay: 'Essay',
};

let draftSeq = 1;
let optionSeq = 1;

const blankOptions = (): DraftOption[] => [
    { id: optionSeq++, text: '', is_correct: false },
    { id: optionSeq++, text: '', is_correct: false },
    { id: optionSeq++, text: '', is_correct: false },
];

const blankDraft = (): DraftQuestion => ({
    id: draftSeq++,
    question_text: '',
    question_type: '',
    options: blankOptions(),
    points: '',
});

const isBuilderOpen = ref(false);
const drafts = ref<DraftQuestion[]>([]);

const openBuilder = () => {
    drafts.value = [blankDraft()];
    isBuilderOpen.value = true;
};

const closeBuilder = () => {
    isBuilderOpen.value = false;
    drafts.value = [];
};

const addDraft = () => {
    drafts.value.push(blankDraft());
};

const removeDraft = (id: number) => {
    drafts.value = drafts.value.filter((draft) => draft.id !== id);
    if (!drafts.value.length) closeBuilder();
};

const onTypeChange = (draft: DraftQuestion) => {
    if (draft.question_type === 'true_false') {
        draft.options = [
            { id: optionSeq++, text: 'True', is_correct: false },
            { id: optionSeq++, text: 'False', is_correct: false },
        ];
        return;
    }
    if (draft.question_type === 'essay') {
        draft.options = [];
        return;
    }
    if (!draft.options.length) draft.options = blankOptions();
};

const addOption = (draft: DraftQuestion) => {
    draft.options.push({ id: optionSeq++, text: '', is_correct: false });
};

const removeOption = (draft: DraftQuestion, optionId: number) => {
    draft.options = draft.options.filter((option) => option.id !== optionId);
};

const editing = ref<Record<number, DraftQuestion>>({});
const editingErrors = ref<Record<number, Record<string, string>>>({});

const editErrorFor = (id: number, key: string): string => editingErrors.value[id]?.[key] ?? '';

const editQuestion = (question: QuestionDetail) => {
    const options = optionList(question.question_option);
    editing.value[question.id] = {
        id: question.id,
        question_text: question.question_text,
        question_type: question.question_type,
        options: options.map((option) => ({ id: optionSeq++, text: option.text, is_correct: option.is_correct })),
        points: question.points ?? '',
    };
};

const cancelEdit = (id: number) => {
    delete editing.value[id];
    delete editingErrors.value[id];
};

const deleteQuestionId = ref<number | null>(null);
const deletingQuestion = ref(false);

const confirmDeleteQuestion = (id: number) => {
    deleteQuestionId.value = id;
};

const deleteQuestion = () => {
    if (!deleteQuestionId.value) return;
    deletingQuestion.value = true;
    useForm({}).delete(route('admin.kelas-kuliah.quiz.questions.destroy', [props.kelasKuliah.id, props.quiz.id, deleteQuestionId.value]), {
        preserveScroll: true,
        onFinish: () => {
            deletingQuestion.value = false;
            deleteQuestionId.value = null;
        },
    });
};

const saveQuestion = (draft: DraftQuestion) => {
    const errors: Record<string, string> = {};

    if (draft.question_text.trim() === '') {
        errors.question_text = 'Text pertanyaan wajib diisi.';
    }
    if (!draft.question_type) {
        errors.question_type = 'Tipe pertanyaan wajib dipilih.';
    }
    if (draft.question_type !== 'essay' && draft.question_type && !hasFilledOption(draft)) {
        errors.question_option = 'Opsi jawaban wajib diisi.';
    }
    if (draft.question_type !== 'essay' && draft.question_type && hasFilledOption(draft) && !hasCorrectOption(draft)) {
        errors.correct_answer = 'Jawaban belum dipilih.';
    }
    if (draft.points === '') {
        errors.points = 'Poin wajib diisi.';
    }

    if (Object.keys(errors).length) {
        editingErrors.value[draft.id] = errors;
        return;
    }

    delete editingErrors.value[draft.id];

    const form = useForm({
        question_text: draft.question_text,
        question_type: draft.question_type,
        question_option: draft.question_type === 'essay' ? null : draft.options.map(({ text, is_correct }) => ({ text, is_correct })),
        points: draft.points,
    });

    form.put(route('admin.kelas-kuliah.quiz.questions.update', [props.kelasKuliah.id, props.quiz.id, draft.id]), {
        preserveScroll: true,
        onSuccess: () => cancelEdit(draft.id),
        onError: (serverErrors) => {
            editingErrors.value[draft.id] = Object.fromEntries(Object.entries(serverErrors).map(([key, message]) => [key, String(message)]));
        },
    });
};

const selectSingleCorrect = (draft: DraftQuestion, optionId: number) => {
    draft.options.forEach((option) => {
        option.is_correct = option.id === optionId;
    });
};

const form = useForm({
    questions: [] as Array<{ question_text: string; question_type: string; question_option: QuestionOption[] | null; points: number | '' }>,
});

const canSave = computed(() => form.processing || !drafts.value.length);

const errorFor = (index: number, key: string): string => {
    const direct = form.errors[`questions.${index}.${key}` as keyof typeof form.errors];
    return typeof direct === 'string' ? direct : '';
};

const hasCorrectOption = (draft: DraftQuestion): boolean => draft.question_type === 'essay' || draft.options.some((option) => option.is_correct);
const hasFilledOption = (draft: DraftQuestion): boolean => draft.options.some((option) => option.text.trim() !== '');

const submit = () => {
    form.clearErrors();
    const errors: Record<string, string> = {};
    drafts.value.forEach((draft, index) => {
        if (draft.question_text.trim() === '') {
            errors[`questions.${index}.question_text`] = 'Text pertanyaan wajib diisi.';
        }
        if (!draft.question_type) {
            errors[`questions.${index}.question_type`] = 'Tipe pertanyaan wajib dipilih.';
        }
        if (draft.question_type !== 'essay' && draft.question_type && !hasFilledOption(draft)) {
            errors[`questions.${index}.question_option`] = 'Opsi jawaban wajib diisi.';
        }
        if (draft.question_type !== 'essay' && draft.question_type && hasFilledOption(draft) && !hasCorrectOption(draft)) {
            errors[`questions.${index}.correct_answer`] = 'Jawaban belum dipilih.';
        }
        if (draft.points === '') {
            errors[`questions.${index}.points`] = 'Poin wajib diisi.';
        }
    });
    if (Object.keys(errors).length) {
        form.setError(errors);
        return;
    }

    form.questions = drafts.value.map((draft) => ({
        question_text: draft.question_text,
        question_type: draft.question_type,
        question_option: draft.question_type === 'essay' ? null : draft.options.map(({ text, is_correct }) => ({ text, is_correct })),
        points: draft.points,
    }));

    form.post(route('admin.kelas-kuliah.quiz.questions.store', [props.kelasKuliah.id, props.quiz.id]), {
        preserveScroll: true,
        onSuccess: () => closeBuilder(),
    });
};

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    return String(val);
};

const duration = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return '-';
    return `${value} menit`;
};

const typeLabel = (value: string): string => TYPE_LABELS[value] ?? value;

const optionList = (value: QuestionDetail['question_option']): QuestionOption[] => {
    if (value === null || value === undefined || value === '') return [];
    if (Array.isArray(value)) {
        return value.map((item) =>
            typeof item === 'object' && item !== null
                ? { text: String(item.text), is_correct: Boolean(item.is_correct) }
                : { text: String(item), is_correct: false },
        );
    }
    if (typeof value === 'object') return Object.values(value).map((item) => ({ text: String(item), is_correct: false }));
    return [{ text: String(value), is_correct: false }];
};

const formatTenggat = (value: string | null | undefined): string => {
    if (!value) return '-';

    const [date, time] = value.replace('T', ' ').split(' ');

    if (!date) return String(value);

    const [year, month, day] = date.split('-');
    const [hour = '00', minute = '00'] = (time ?? '').split(':');

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return `${day} ${months[Number(month) - 1]} ${year}, ${hour}:${minute}`;
};

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const area =
    'min-h-24 rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="`Detail ${props.quiz.nama_quiz}`" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="relative mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 pb-28 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Detail Quiz</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Kelas {{ v(props.kelasKuliah?.kode_kelas)
                            }}{{ props.kelasKuliah?.tahun_ajaran ? ` — ${v(props.kelasKuliah.tahun_ajaran)}` : '' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('admin.kelas-kuliah.show', props.kelasKuliah.id)"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route('admin.kelas-kuliah.quiz.edit', [props.kelasKuliah.id, props.quiz.id])"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Quiz</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Nama Quiz</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.quiz.nama_quiz) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Waktu Pengerjaan</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ duration(props.quiz.waktu_pengerjaan) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Tenggat Waktu</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ formatTenggat(props.quiz.tenggat_waktu) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Diunggah Oleh</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.quiz.uploader?.name) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Catatan</dt>
                            <dd class="whitespace-pre-wrap break-words text-[15px] leading-5 text-black">{{ v(props.quiz.catatan) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Daftar Pertanyaan</h2>
                            <p class="text-sm leading-5 text-[#615d59]">Soal-soal yang termasuk dalam quiz ini.</p>
                        </div>
                        <Button v-if="!isBuilderOpen" class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]" @click="openBuilder">
                            <Plus class="size-4" /> Tambah Pertanyaan
                        </Button>
                    </div>

                    <div
                        v-if="page.props.flash?.question_success"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                        role="alert"
                    >
                        {{ page.props.flash.question_success }}
                    </div>
                    <div
                        v-if="page.props.flash?.question_error"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                        role="alert"
                    >
                        {{ page.props.flash.question_error }}
                    </div>

                    <div v-if="!isBuilderOpen" class="mt-4 flex flex-col gap-3">
                        <article
                            v-for="(question, index) in props.quiz.questions ?? []"
                            :key="question.id"
                            class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 p-4 text-left hover:bg-[#f6f5f4]"
                                @click="editing[question.id] ? cancelEdit(question.id) : editQuestion(question)"
                            >
                                <span class="min-w-0 truncate text-[15px] font-semibold leading-6 text-black"
                                    >{{ index + 1 }}. {{ question.question_text }}</span
                                >
                                <ChevronDown
                                    class="size-4 shrink-0 transition-transform"
                                    :class="editing[question.id] ? 'rotate-180' : ''"
                                    aria-hidden="true"
                                />
                            </button>
                            <div v-if="editing[question.id]" class="border-t border-[#e6e6e6] p-4">
                                <div class="grid gap-3">
                                    <textarea v-model="editing[question.id].question_text" :class="area" />
                                    <InputError :message="editErrorFor(question.id, 'question_text')" />
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div class="relative grid gap-2">
                                            <select
                                                v-model="editing[question.id].question_type"
                                                :class="sel"
                                                @change="onTypeChange(editing[question.id])"
                                            >
                                                <option v-for="option in QUESTION_TYPES" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <div v-if="editErrorFor(question.id, 'question_type')" class="absolute left-0 top-full z-10 pt-1">
                                                <InputError :message="editErrorFor(question.id, 'question_type')" />
                                            </div>
                                        </div>
                                        <div class="relative grid gap-2">
                                            <Input v-model="editing[question.id].points" type="number" min="0" :class="inp" />
                                            <div v-if="editErrorFor(question.id, 'points')" class="absolute left-0 top-full z-10 pt-1">
                                                <InputError :message="editErrorFor(question.id, 'points')" />
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            editing[question.id].question_type === 'single_choice' ||
                                            editing[question.id].question_type === 'true_false'
                                        "
                                        class="mt-4 grid gap-2"
                                    >
                                        <div v-for="option in editing[question.id].options" :key="option.id" class="flex items-center gap-2">
                                            <input
                                                :checked="option.is_correct"
                                                type="radio"
                                                :name="`edit-correct-${question.id}`"
                                                class="size-4 accent-[#0075de]"
                                                @change="selectSingleCorrect(editing[question.id], option.id)"
                                            />
                                            <Input
                                                v-model="option.text"
                                                :disabled="editing[question.id].question_type === 'true_false'"
                                                :class="inp"
                                            />
                                        </div>
                                    </div>
                                    <div v-else-if="editing[question.id].question_type === 'multiple_choice'" class="grid gap-2">
                                        <div v-for="option in editing[question.id].options" :key="option.id" class="flex items-center gap-2">
                                            <Checkbox v-model:checked="option.is_correct" class="size-4 rounded-[4px]" />
                                            <Input v-model="option.text" :class="inp" />
                                        </div>
                                    </div>
                                    <InputError :message="editErrorFor(question.id, 'question_option')" />
                                    <InputError :message="editErrorFor(question.id, 'correct_answer')" />
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="rounded-full text-red-600 hover:text-red-700"
                                            @click="confirmDeleteQuestion(question.id)"
                                            >Hapus</Button
                                        >
                                        <Button type="button" variant="outline" class="rounded-full" @click="cancelEdit(question.id)">Batal</Button>
                                        <Button
                                            type="button"
                                            class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                                            @click="saveQuestion(editing[question.id])"
                                            >Simpan</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </article>
                        <div
                            v-if="!(props.quiz.questions ?? []).length"
                            class="rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8 text-center"
                        >
                            <p class="text-sm font-medium text-black">Belum ada question</p>
                            <p class="mt-1 text-sm leading-5 text-[#615d59]">Klik Tambah Pertanyaan di tengah untuk membuat soal pertama.</p>
                        </div>
                    </div>

                    <form v-else @submit.prevent="submit" class="mt-4 flex flex-col gap-4">
                        <div v-for="(draft, draftIndex) in drafts" :key="draft.id" class="rounded-xl border border-[#e6e6e6] p-4">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-black">Pertanyaan {{ draftIndex + 1 }}</h3>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    type="button"
                                    class="size-8 rounded-full text-[#a39e98] hover:text-black"
                                    @click="removeDraft(draft.id)"
                                    aria-label="Hapus pertanyaan"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>

                            <div class="mt-3 grid items-start gap-3">
                                <div class="grid gap-2">
                                    <Label :for="`question-text-${draft.id}`" class="text-sm font-medium text-black">Text Pertanyaan</Label>
                                    <textarea
                                        :id="`question-text-${draft.id}`"
                                        v-model="draft.question_text"
                                        placeholder="Tulis pertanyaan di sini"
                                        :class="area"
                                    />
                                    <InputError :message="errorFor(draftIndex, 'question_text')" />
                                </div>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div class="relative grid gap-2">
                                        <Label :for="`question-type-${draft.id}`" class="text-sm font-medium text-black">Tipe Pertanyaan</Label>
                                        <select
                                            :id="`question-type-${draft.id}`"
                                            v-model="draft.question_type"
                                            :class="sel"
                                            @change="onTypeChange(draft)"
                                        >
                                            <option v-for="option in QUESTION_TYPES" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <div v-if="errorFor(draftIndex, 'question_type')" class="absolute left-0 top-full z-10 pt-1">
                                            <InputError :message="errorFor(draftIndex, 'question_type')" />
                                        </div>
                                    </div>
                                    <div class="relative grid gap-2">
                                        <Label :for="`question-points-${draft.id}`" class="text-sm font-medium text-black">Poin</Label>
                                        <Input
                                            :id="`question-points-${draft.id}`"
                                            v-model="draft.points"
                                            type="number"
                                            min="0"
                                            placeholder="cth. 10"
                                            :class="inp"
                                        />
                                        <div v-if="errorFor(draftIndex, 'points')" class="absolute left-0 top-full z-10 pt-1">
                                            <InputError :message="errorFor(draftIndex, 'points')" />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="draft.question_type === 'single_choice'" class="mt-4 grid gap-2">
                                    <Label class="text-sm font-medium text-black">Opsi Jawaban</Label>
                                    <div v-for="option in draft.options" :key="option.id" class="flex items-center gap-2">
                                        <input
                                            :checked="option.is_correct"
                                            type="radio"
                                            :name="`correct-${draft.id}`"
                                            class="size-4 accent-[#0075de]"
                                            @change="selectSingleCorrect(draft, option.id)"
                                        />
                                        <Input
                                            v-model="option.text"
                                            type="text"
                                            :placeholder="`Opsi ${draft.options.indexOf(option) + 1}`"
                                            :class="inp"
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            class="size-8 shrink-0 rounded-full text-[#a39e98] hover:text-black"
                                            @click="removeOption(draft, option.id)"
                                            aria-label="Hapus opsi"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                    <div>
                                        <Button
                                            variant="outline"
                                            type="button"
                                            class="rounded-full border-[#e6e6e6] bg-white text-black hover:bg-white"
                                            @click="addOption(draft)"
                                        >
                                            <Plus class="size-4" /> Add Option
                                        </Button>
                                    </div>
                                    <InputError :message="errorFor(draftIndex, 'question_option')" />
                                    <InputError :message="errorFor(draftIndex, 'correct_answer')" />
                                </div>

                                <div v-else-if="draft.question_type === 'multiple_choice'" class="grid gap-2">
                                    <Label class="text-sm font-medium text-black">Opsi Jawaban</Label>
                                    <div v-for="option in draft.options" :key="option.id" class="flex items-center gap-2">
                                        <Checkbox v-model:checked="option.is_correct" class="size-4 rounded-[4px]" />
                                        <Input
                                            v-model="option.text"
                                            type="text"
                                            :placeholder="`Opsi ${draft.options.indexOf(option) + 1}`"
                                            :class="inp"
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            type="button"
                                            class="size-8 shrink-0 rounded-full text-[#a39e98] hover:text-black"
                                            @click="removeOption(draft, option.id)"
                                            aria-label="Hapus opsi"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                    <div>
                                        <Button
                                            variant="outline"
                                            type="button"
                                            class="rounded-full border-[#e6e6e6] bg-white text-black hover:bg-white"
                                            @click="addOption(draft)"
                                        >
                                            <Plus class="size-4" /> Add Option
                                        </Button>
                                    </div>
                                    <InputError :message="errorFor(draftIndex, 'question_option')" />
                                    <InputError :message="errorFor(draftIndex, 'correct_answer')" />
                                </div>

                                <div v-else-if="draft.question_type === 'true_false'" class="grid gap-2">
                                    <Label class="text-sm font-medium text-black">Opsi Jawaban</Label>
                                    <div v-for="option in draft.options" :key="option.id" class="flex items-center gap-2">
                                        <input
                                            :checked="option.is_correct"
                                            type="radio"
                                            :name="`correct-${draft.id}`"
                                            class="size-4 accent-[#0075de]"
                                            @change="selectSingleCorrect(draft, option.id)"
                                        />
                                        <Input v-model="option.text" type="text" :class="inp" disabled />
                                    </div>
                                    <InputError :message="errorFor(draftIndex, 'correct_answer')" />
                                </div>

                                <p v-else-if="draft.question_type === 'essay'" class="text-sm italic leading-5 text-[#a39e98]">
                                    Essay tidak membutuhkan opsi jawaban.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <Button
                                variant="outline"
                                type="button"
                                class="rounded-full border-[#e6e6e6] bg-white text-black hover:bg-white"
                                @click="addDraft"
                            >
                                <Plus class="size-4" /> Add Question
                            </Button>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    class="rounded-full border-[#e6e6e6] bg-white text-black hover:bg-white"
                                    @click="closeBuilder"
                                >
                                    Batal
                                </Button>
                                <Button type="submit" :disabled="canSave" class="rounded-full bg-[#0075de] px-6 text-white hover:bg-[#005bab]">
                                    <Save class="size-4" /> Simpan
                                </Button>
                            </div>
                        </div>
                        <InputError :message="form.errors.questions" />
                    </form>
                </section>
                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="space-y-1">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa yang Mengerjakan Quiz</h2>
                        <p class="text-sm leading-5 text-[#615d59]">Daftar mahasiswa yang sudah memulai atau menyelesaikan quiz.</p>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-[#e6e6e6]">
                        <table class="w-full min-w-[700px] text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Mulai</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Selesai</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(attempt, index) in props.quiz.attempts ?? []" :key="attempt.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-sm text-[#615d59]">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="font-medium text-black">{{ attempt.mahasiswa?.user?.name ?? '-' }}</span>
                                        <span v-if="attempt.mahasiswa?.nim" class="block text-[#615d59]">{{ attempt.mahasiswa.nim }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">{{ formatDateTime(attempt.started_at) }}</td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">{{ formatDateTime(attempt.submitted_at) }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-black">{{ attempt.score ?? '-' }}<span v-if="attempt.auto_closed" class="mt-0.5 block text-xs font-normal text-[#dd5b00]">Ditutup otomatis (waktu habis)</span></td>
                                </tr>
                                <tr v-if="!(props.quiz.attempts ?? []).length">
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-[#615d59]">
                                        Belum ada mahasiswa yang mengerjakan quiz.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
        <AlertModal
            :open="deleteQuestionId !== null"
            title="Hapus question?"
            description="Question yang dihapus tidak dapat dikembalikan."
            confirm-text="Hapus"
            :loading="deletingQuestion"
            @update:open="(value) => !value && (deleteQuestionId = null)"
            @confirm="deleteQuestion"
        />
    </AppLayout>
</template>
