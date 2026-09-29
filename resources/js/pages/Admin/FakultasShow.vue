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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Fakultas</h1>
                        <p class="deskripsi-halaman">Ringkasan informasi fakultas, dekan, dan program studi di bawahnya.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"><Link :href="route('admin.fakultas.index')">Kembali</Link></Button>
                        <Button as-child><Link :href="route('admin.fakultas.edit', props.fakultas.id)">Edit</Link></Button>
                    </div>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Fakultas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="f in info" :key="f.key" class="space-y-1">
                            <dt class="teks-bantu">{{ f.label }}</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v((props.fakultas as any)[f.key]) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Dekan</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.fakultas.dekan?.user?.name) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="judul-bagian">Program Studi ({{ prodiList().length }})</h2>
                        <Button as-child variant="outline" size="sm"><Link :href="route('admin.program-studi.index')">Kelola Prodi</Link></Button>
                    </div>
                    <div class="tabel-wadah mt-4 shadow-none">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[640px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Kode</th>
                                        <th>Nama Prodi</th>
                                        <th>Jenjang</th>
                                        <th>Akreditasi</th>
                                        <th>Kaprodi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(p, index) in prodiList()" :key="p.id">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td class="font-medium text-black">{{ p.kode_prodi }}</td>
                                        <td>{{ p.nama_prodi }}</td>
                                        <td>{{ p.jenjang }}</td>
                                        <td>{{ p.status_akreditasi }}</td>
                                        <td>{{ kaprodiName(p) }}</td>
                                    </tr>
                                    <tr v-if="!prodiList().length" class="baris-kosong">
                                        <td colspan="6" class="tabel-kosong">Fakultas ini belum memiliki program studi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
