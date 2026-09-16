<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

type Item = { id: number; information: string; file: string; uploader?: { name: string } };
type Pagination = { data: Item[]; links?: { url: string | null; label: string; active: boolean }[]; total?: number; from?: number | null };
const page = usePage<{ flash: { success?: string; error?: string } }>();
const props = defineProps<{ infoKuliahs: Pagination }>();
const open = ref(false);
const selected = ref<Item | null>(null);
const remove = (item: Item) => { selected.value = item; open.value = true; };
const confirmDelete = () => { if (selected.value) router.delete(route('admin.info-kuliah.destroy', selected.value.id), { onFinish: () => { open.value = false; selected.value = null; } }); };
</script>

<template>
    <Head title="Info Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Info Kuliah', href: route('admin.info-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1"><h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Info Kuliah</h1><p class="text-sm leading-5 text-[#615d59]">Kelola informasi perkuliahan.</p></div>
                    <Link :href="route('admin.info-kuliah.create')"><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Info Kuliah</Button></Link>
                </div>
                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">{{ page.props.flash.error }}</div>
                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left">
                            <thead><tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]"><th class="w-16 px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th><th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi</th><th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">File</th><th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Uploader</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th></tr></thead>
                            <tbody class="divide-y divide-[#e6e6e6]"><tr v-for="(item, index) in props.infoKuliahs.data" :key="item.id" class="transition-colors hover:bg-[#f6f5f4]/60"><td class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">{{ (props.infoKuliahs.from ?? 1) + index }}</td><td class="whitespace-pre-line px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ item.information }}</td><td class="px-4 py-3"><a :href="`/storage/${item.file}`" target="_blank" class="text-[15px] font-medium text-[#0075de] hover:underline">Lihat file</a></td><td class="px-4 py-3 text-[15px] text-[#31302e]">{{ item.uploader?.name ?? '-' }}</td><td class="px-4 py-3"><div class="flex justify-end gap-1.5"><Link :href="route('admin.info-kuliah.edit', item.id)" title="Edit" aria-label="Edit"><Button variant="outline" size="icon" class="size-8 rounded-full border-[#e6e6e6] text-[#2a9d99] hover:bg-[#f6f5f4]"><Pencil class="size-4" /></Button></Link><button type="button" title="Hapus" aria-label="Hapus" @click="remove(item)"><Button variant="outline" size="icon" class="size-8 rounded-full border-[#e6e6e6] text-[#dd5b00] hover:bg-[#f6f5f4]"><Trash2 class="size-4" /></Button></button></div></td></tr><tr v-if="!props.infoKuliahs.data.length"><td colspan="5" class="px-4 py-16 text-center"><div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8"><p class="text-sm font-medium text-black">Belum ada info kuliah</p><p class="mt-1 text-sm leading-5 text-[#615d59]">Tambahkan informasi perkuliahan baru untuk memulai.</p></div></td></tr></tbody>
                        </table>
                    </div>
                </div>
                <nav v-if="props.infoKuliahs.links?.length" class="flex flex-wrap items-center gap-2" aria-label="Pagination"><Link v-for="link in props.infoKuliahs.links" :key="link.label" :href="link.url ?? '#'" preserve-scroll preserve-state class="rounded-lg border px-3 py-1.5 text-sm font-medium" :class="link.active ? 'border-[#0075de] bg-[#0075de] text-white' : link.url ? 'border-[#e6e6e6] bg-white text-black hover:bg-[#f6f5f4]' : 'pointer-events-none border-[#e6e6e6] bg-white opacity-40'" v-html="link.label" /></nav>
                <AlertModal :open="open" description="Anda yakin ingin menghapus data ini?" confirm-text="Ya" cancel-text="Batal" @update:open="open = $event" @confirm="confirmDelete" @cancel="open = false" />
            </div>
        </div>
    </AppLayout>
</template>
