<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type MataKuliah = {
    nama_matkul: string;
};

type Kelas = {
    id: number;
    kode_kelas: string;
    mataKuliah?: MataKuliah | null;
    mata_kuliah?: MataKuliah | null;
};

type Materi = {
    id: number;
    judul_materi: string;
    jenis: string;
    pertemuan_ke: number;
    catatan?: string | null;
    file?: string[] | string | null;
    uploader?: { name: string } | null;
    kelas_kuliah?: Kelas | null;
    kelasKuliah?: Kelas | null;
};

const props = defineProps<{ materi: Materi }>();

const files = (): string[] => {
    const value = props.materi.file;

    if (Array.isArray(value)) {
        return value.filter((file): file is string => typeof file === 'string' && file !== '');
    }

    if (typeof value === 'string' && value !== '') {
        try {
            const decoded = JSON.parse(value);

            if (Array.isArray(decoded)) {
                return decoded.filter((file): file is string => typeof file === 'string' && file !== '');
            }
        } catch {
            return [value];
        }

        return [value];
    }

    return [];
};

const fileName = (path: string) => path.split('/').pop() ?? path;
const kelas = () => props.materi.kelasKuliah ?? props.materi.kelas_kuliah;
</script>

<template>
    <Head :title="props.materi.judul_materi" />
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Detail Kelas',
                href: kelas() ? route('mahasiswa.jadwal-kuliah.show', kelas()!.id) : route('mahasiswa.jadwal-kuliah'),
            },
            { title: props.materi.judul_materi, href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.materi.judul_materi }}</h1>
                        <p class="deskripsi-halaman">Detail materi perkuliahan.</p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="kelas() ? route('mahasiswa.jadwal-kuliah.show', kelas()!.id) : route('mahasiswa.jadwal-kuliah')">Kembali</Link>
                    </Button>
                </div>
                <section class="kartu p-6">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="teks-bantu">Jenis</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ props.materi.jenis }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Pertemuan</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ props.materi.pertemuan_ke }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Kelas</dt>
                            <dd class="font-medium text-black dark:text-foreground">
                                {{ kelas()?.kode_kelas ?? '-' }} —
                                {{ kelas()?.mataKuliah?.nama_matkul ?? kelas()?.mata_kuliah?.nama_matkul ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Diunggah oleh</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ props.materi.uploader?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5 dark:border-border">
                        <h2 class="judul-bagian">Catatan</h2>
                        <p class="mt-2 whitespace-pre-line text-sm text-[#31302e] dark:text-foreground">
                            {{ props.materi.catatan || '-' }}
                        </p>
                    </div>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5 dark:border-border">
                        <h2 class="judul-bagian">File</h2>
                        <div v-if="files().length" class="mt-3 space-y-2">
                            <a
                                v-for="(path, fileIndex) in files()"
                                :key="path"
                                :href="route('berkas.materi', [props.materi.id, fileIndex])"
                                target="_blank"
                                class="flex items-center justify-between rounded-lg border border-[#e6e6e6] px-3 py-2 text-sm text-[#0075de] transition hover:border-[#0075de] hover:bg-[#f8fbff] dark:border-border dark:hover:bg-accent/40"
                            >
                                {{ fileName(path) }}
                            </a>
                        </div>
                        <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">Tidak ada file.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
