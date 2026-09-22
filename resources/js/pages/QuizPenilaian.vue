<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

type Option = { text: string; is_correct?: boolean | number | string };
type Item = {
    question_id: number;
    question_text: string;
    question_type: string;
    points: number;
    options: Option[];
    answer: string[] | null;
    point: string | number | null;
};

const props = defineProps<{
    kelasKuliah: { id: number; kode_kelas: string; nama_matkul?: string | null };
    quiz: { id: number; nama_quiz: string };
    attempt: {
        id: number;
        mahasiswa?: string | null;
        nim?: string | null;
        started_at?: string | null;
        submitted_at?: string | null;
        score?: string | number | null;
        auto_closed?: boolean;
    };
    items: Item[];
    urls: { grade: string; back: string };
    breadcrumbKelas: string;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const essays = computed(() => props.items.filter((item) => item.question_type === 'essay'));
const form = useForm<{ points: Record<number, string | number | null> }>({
    points: Object.fromEntries(essays.value.map((item) => [item.question_id, item.point ?? ''])),
});

const typeLabel: Record<string, string> = {
    single_choice: 'Pilihan tunggal',
    multiple_choice: 'Pilihan ganda',
    true_false: 'Benar/Salah',
    essay: 'Esai',
};

const pointError = (questionId: number) => (form.errors as Record<string, string | undefined>)[`points.${questionId}`];

const isCorrect = (option: Option) => option.is_correct === true || option.is_correct === 1 || option.is_correct === '1';
const chosen = (item: Item, option: Option) => (item.answer ?? []).includes(option.text);

const formatDateTime = (value?: string | null) =>
    value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-';

const submit = () => {
    form.transform((data) => ({
        points: Object.fromEntries(Object.entries(data.points).map(([id, point]) => [id, point === '' ? null : point])),
    })).put(props.urls.grade, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Jawaban ${props.attempt.mahasiswa ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: `Kelas ${props.kelasKuliah.kode_kelas}`, href: props.breadcrumbKelas },
            { title: props.quiz.nama_quiz, href: props.urls.back },
            { title: 'Jawaban Mahasiswa', href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-bold text-black">{{ props.attempt.mahasiswa ?? '-' }}</h1>
                        <p class="text-sm text-[#615d59]">
                            {{ props.attempt.nim ?? '-' }} · {{ props.quiz.nama_quiz }} · {{ props.kelasKuliah.kode_kelas }}
                            {{ props.kelasKuliah.nama_matkul ? `(${props.kelasKuliah.nama_matkul})` : '' }}
                        </p>
                    </div>
                    <Link :href="props.urls.back" class="rounded-lg border border-[#e6e6e6] bg-white px-4 py-2 text-sm font-medium text-black">Kembali</Link>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">{{ page.props.flash.success }}</div>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <dl class="grid gap-4 sm:grid-cols-4">
                        <div><dt class="text-xs text-[#a39e98]">Mulai</dt><dd class="font-medium">{{ formatDateTime(props.attempt.started_at) }}</dd></div>
                        <div><dt class="text-xs text-[#a39e98]">Selesai</dt><dd class="font-medium">{{ formatDateTime(props.attempt.submitted_at) }}</dd></div>
                        <div><dt class="text-xs text-[#a39e98]">Score</dt><dd class="text-xl font-bold text-black">{{ props.attempt.score ?? '-' }}</dd></div>
                        <div v-if="props.attempt.auto_closed"><dt class="text-xs text-[#a39e98]">Keterangan</dt><dd class="text-sm text-[#dd5b00]">Ditutup otomatis (waktu habis)</dd></div>
                    </dl>
                </section>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <article v-for="(item, index) in props.items" :key="item.question_id" class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="font-medium text-black">{{ index + 1 }}. {{ item.question_text }}</h2>
                            <span class="text-xs text-[#a39e98]">{{ typeLabel[item.question_type] ?? item.question_type }} · {{ item.points }} poin</span>
                        </div>

                        <template v-if="item.question_type === 'essay'">
                            <p class="mt-3 whitespace-pre-line rounded-lg border border-[#e6e6e6] bg-[#fafafa] px-3 py-2 text-sm text-[#31302e]">
                                {{ item.answer?.[0] || 'Tidak dijawab.' }}
                            </p>
                            <div class="mt-3 flex items-center gap-2">
                                <label :for="`poin-${item.question_id}`" class="text-sm text-[#615d59]">Poin</label>
                                <input
                                    :id="`poin-${item.question_id}`"
                                    v-model="form.points[item.question_id]"
                                    type="number"
                                    min="0"
                                    :max="item.points"
                                    step="0.5"
                                    placeholder="Belum dinilai"
                                    class="h-9 w-32 rounded-lg border border-[#dddddd] px-3 text-sm focus:border-[#0075de] focus:outline-none"
                                />
                                <span class="text-sm text-[#a39e98]">/ {{ item.points }}</span>
                            </div>
                            <InputError :message="pointError(item.question_id)" />
                        </template>

                        <template v-else>
                            <ul class="mt-3 grid gap-1.5">
                                <li
                                    v-for="option in item.options"
                                    :key="option.text"
                                    class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm"
                                    :class="chosen(item, option) ? (isCorrect(option) ? 'bg-[#e8f6ec] text-[#1a7f37]' : 'bg-[#fdecea] text-[#b42318]') : 'text-[#31302e]'"
                                >
                                    <span class="w-4 text-center">{{ chosen(item, option) ? '●' : '○' }}</span>
                                    {{ option.text }}
                                    <span v-if="isCorrect(option)" class="ml-auto text-xs text-[#1a7f37]">Kunci</span>
                                </li>
                            </ul>
                            <p class="mt-2 text-sm text-[#615d59]">
                                Poin: <span class="font-semibold text-black">{{ item.point ?? 0 }}</span> / {{ item.points }}
                                <span v-if="!item.answer" class="text-[#a39e98]">· tidak dijawab</span>
                            </p>
                        </template>
                    </article>

                    <div v-if="essays.length" class="sticky bottom-4 flex justify-end">
                        <Button type="submit" class="rounded-lg bg-[#0075de] text-white shadow-md hover:bg-[#005bab]" :disabled="form.processing || !props.attempt.submitted_at">
                            Simpan Nilai Esai
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
