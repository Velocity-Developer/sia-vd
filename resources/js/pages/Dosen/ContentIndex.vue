<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Eye } from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage<{ flash: Record<string, string | undefined> }>();
const props = defineProps<{ type: 'tugas' | 'materi' | 'quiz'; items: { data: any[]; links: any[]; total: number }; otherClasses: any[] }>();
const selected = ref<any | null>(null);
const targets = ref<number[]>([]);
const duplicate = () => {
    if (!selected.value || !targets.value.length) return;
    router.post(
        route(`dosen.kelas-kuliah.${props.type}.duplicate`, [selected.value.kelas_id, selected.value.id]),
        { target_ids: targets.value, from_index: true },
        {
            onSuccess: () => {
                selected.value = null;
                targets.value = [];
            },
        },
    );
};
const labels = { tugas: 'Tugas', materi: 'Materi', quiz: 'Quiz' };
const title = labels[props.type];
const itemTitle = (item: any) => item.judul_tugas ?? item.judul_materi ?? item.nama_quiz;
const detailRoute = (item: any) => (props.type === 'quiz' ? 'dosen.kelas-kuliah.quiz.show' : 'dosen.kelas-kuliah.show');
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title, href: route(`dosen.${props.type}`) }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                    <p class="text-sm leading-5 text-[#615d59]">Daftar {{ title.toLowerCase() }} dari kelas yang Anda ampu.</p>
                </div>
                <div
                    v-if="page.props.flash?.[`${props.type}_success`]"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash[`${props.type}_success`] }}
                </div>
                <div
                    v-if="page.props.flash?.[`${props.type}_error`]"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                    role="alert"
                >
                    {{ page.props.flash[`${props.type}_error`] }}
                </div>
                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Nama</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Kelas</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="item in props.items.data" :key="item.id">
                                    <td class="px-4 py-3 text-[15px] text-black">{{ itemTitle(item) }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#615d59]">
                                        {{ item.kelas_kuliah?.kode_kelas ?? '-' }} —
                                        {{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? item.kelas_kuliah?.mataKuliah?.nama_matkul ?? '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link :href="route(detailRoute(item), props.type === 'quiz' ? [item.kelas_id, item.id] : item.kelas_id)"
                                            ><Button variant="outline" size="icon" class="size-8 rounded-full text-[#0075de]"
                                                ><Eye class="size-4" /></Button></Link
                                        ><Button
                                            variant="outline"
                                            size="icon"
                                            class="ml-1 size-8 rounded-full text-[#2a9d99]"
                                            @click="selected = item"
                                            ><Copy class="size-4"
                                        /></Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.items.data.length">
                                    <td colspan="3" class="px-4 py-16 text-center text-sm text-[#615d59]">Belum ada {{ title.toLowerCase() }}.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <nav v-if="props.items.total > 0" class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in props.items.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        preserve-scroll
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        :class="link.active ? 'border-[#0075de] bg-[#0075de] text-white' : 'border-[#e6e6e6] bg-white'"
                        v-html="link.label"
                    />
                </nav>
                <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
                    <div class="w-full max-w-md rounded-xl bg-white p-6">
                        <h2 class="text-lg font-semibold">Duplikasi {{ title }}</h2>
                        <label v-for="kelas in props.otherClasses.filter((k) => k.id !== selected.kelas_id)" :key="kelas.id" class="mt-3 flex gap-2"
                            ><input v-model="targets" type="checkbox" :value="kelas.id" />{{ kelas.kode_kelas }} —
                            {{ kelas.mata_kuliah?.nama_matkul ?? kelas.mataKuliah?.nama_matkul }}</label
                        >
                        <div class="mt-5 flex justify-end gap-2">
                            <Button variant="outline" @click="selected = null">Batal</Button><Button @click="duplicate">Duplikasi</Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
