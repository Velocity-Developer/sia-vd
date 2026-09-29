<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
    nilaiTerkunci: string | null;
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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.attempt.mahasiswa ?? '-' }}</h1>
                        <p class="deskripsi-halaman">
                            {{ props.attempt.nim ?? '-' }} · {{ props.quiz.nama_quiz }} · {{ props.kelasKuliah.kode_kelas }}
                            {{ props.kelasKuliah.nama_matkul ? `(${props.kelasKuliah.nama_matkul})` : '' }}
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="props.urls.back">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>

                <section class="kartu p-6">
                    <dl class="grid gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="teks-bantu">Mulai</dt>
                            <dd class="text-sm font-medium text-black">{{ formatDateTime(props.attempt.started_at) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Selesai</dt>
                            <dd class="text-sm font-medium text-black">{{ formatDateTime(props.attempt.submitted_at) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Score</dt>
                            <dd class="text-xl font-bold text-black">{{ props.attempt.score ?? '-' }}</dd>
                        </div>
                        <div v-if="props.attempt.auto_closed">
                            <dt class="teks-bantu">Keterangan</dt>
                            <dd class="text-sm text-[#dd5b00]">Ditutup otomatis (waktu habis)</dd>
                        </div>
                    </dl>
                </section>

                <p v-if="props.nilaiTerkunci" class="alert-gagal">{{ props.nilaiTerkunci }}</p>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <article v-for="(item, index) in props.items" :key="item.question_id" class="kartu p-6">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="judul-bagian">{{ index + 1 }}. {{ item.question_text }}</h2>
                            <span class="teks-bantu">{{ typeLabel[item.question_type] ?? item.question_type }} · {{ item.points }} poin</span>
                        </div>

                        <template v-if="item.question_type === 'essay'">
                            <p
                                class="mt-3 whitespace-pre-line rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] px-3 py-2 text-sm text-[#31302e] dark:border-border dark:bg-muted dark:text-foreground"
                            >
                                {{ item.answer?.[0] || 'Tidak dijawab.' }}
                            </p>
                            <div class="mt-3 flex items-center gap-2">
                                <label :for="`poin-${item.question_id}`" class="label-isian">Poin</label>
                                <input
                                    :id="`poin-${item.question_id}`"
                                    v-model="form.points[item.question_id]"
                                    :disabled="!!props.nilaiTerkunci"
                                    type="number"
                                    min="0"
                                    :max="item.points"
                                    step="0.5"
                                    placeholder="Belum dinilai"
                                    class="isian w-32"
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
                                    :class="
                                        chosen(item, option)
                                            ? isCorrect(option)
                                                ? 'bg-[#f0faf2] text-[#17702b]'
                                                : 'bg-[#fdf3ec] text-[#a84400]'
                                            : 'text-[#31302e] dark:text-foreground'
                                    "
                                >
                                    <span class="w-4 text-center">{{ chosen(item, option) ? '●' : '○' }}</span>
                                    {{ option.text }}
                                    <span v-if="isCorrect(option)" class="ml-auto text-xs text-[#17702b]">Kunci</span>
                                </li>
                            </ul>
                            <p class="mt-2 text-sm text-[#615d59]">
                                Poin: <span class="font-semibold text-black">{{ item.point ?? 0 }}</span> / {{ item.points }}
                                <span v-if="!item.answer" class="text-[#a39e98]">· tidak dijawab</span>
                            </p>
                        </template>
                    </article>

                    <div v-if="essays.length && !props.nilaiTerkunci" class="sticky bottom-4 flex justify-end">
                        <Button type="submit" :disabled="form.processing || !props.attempt.submitted_at">Simpan Nilai Esai</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
