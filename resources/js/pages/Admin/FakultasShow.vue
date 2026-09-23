<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type ProdiRow = {
    id: number;
    kode_prodi: string;
    nama_prodi: string;
    jenjang: string;
    status_akreditasi: string;
    ketuaProgramStudi?: { user?: { name?: string } } | null;
    ketua_program_studi?: { user?: { name?: string } } | null;
};

const props = defineProps<{
    fakultas: {
        id: number;
        kode_fakultas: string;
        nama_fakultas: string;
        tanggal_berdiri: string;
        no_telp: string;
        email: string;
        dekan?: { user?: { name?: string } } | null;
        programStudis?: ProdiRow[];
        program_studis?: ProdiRow[];
    };
}>();

const prodiList = () => (props.fakultas.programStudis ?? (props.fakultas as any).program_studis ?? []) as ProdiRow[];
const kaprodiName = (p: ProdiRow): string => p.ketuaProgramStudi?.user?.name ?? p.ketua_program_studi?.user?.name ?? '-';

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    if (typeof val === 'string') {
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 10);
        return val;
    }
    return String(val);
};

const info = [
    { label: 'Kode Fakultas', key: 'kode_fakultas' as const },
    { label: 'Nama Fakultas', key: 'nama_fakultas' as const },
    { label: 'Tanggal Berdiri', key: 'tanggal_berdiri' as const },
    { label: 'No. Telepon', key: 'no_telp' as const },
    { label: 'Email', key: 'email' as const },
];
</script>

<template>
    <Head :title="`Detail ${props.fakultas.nama_fakultas}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Fakultas', href: '#' }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Detail Fakultas</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Ringkasan informasi fakultas, dekan, dan program studi di bawahnya.</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('admin.fakultas.index')"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route('admin.fakultas.edit', props.fakultas.id)"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Fakultas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="f in info" :key="f.key" class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">{{ f.label }}</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v((props.fakultas as any)[f.key]) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Dekan</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.fakultas.dekan?.user?.name) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Program Studi ({{ prodiList().length }})</h2>
                        <Link :href="route('admin.program-studi.index')"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white"
                                >Kelola Prodi</Button
                            ></Link
                        >
                    </div>
                    <div v-if="prodiList().length > 0" class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kode</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama Prodi</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jenjang</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Akreditasi</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kaprodi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="p in prodiList()" :key="p.id" class="hover:bg-[#f6f5f4]/60">
                                        <td class="px-4 py-3 text-[15px] font-medium leading-5 text-black">{{ p.kode_prodi }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ p.nama_prodi }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ p.jenjang }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ p.status_akreditasi }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ kaprodiName(p) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="mt-4 rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8 text-center">
                        <p class="text-sm font-medium text-black">Belum ada program studi</p>
                        <p class="mt-1 text-sm leading-5 text-[#615d59]">Fakultas ini belum memiliki program studi.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
