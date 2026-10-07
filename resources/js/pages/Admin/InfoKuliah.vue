<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

type Item = { id: number; kategori: string | null; information: string; file: string; uploader?: { name: string } };
type Pagination = { data: Item[]; links?: { url: string | null; label: string; active: boolean }[]; total?: number; from?: number | null };
const page = usePage<{ flash: { success?: string; error?: string } }>();
const props = defineProps<{ infoKuliahs: Pagination }>();
const open = ref(false);
const selected = ref<Item | null>(null);
const remove = (item: Item) => {
    selected.value = item;
    open.value = true;
};
const confirmDelete = () => {
    if (selected.value)
        router.delete(route('admin.info-kuliah.destroy', selected.value.id), {
            onFinish: () => {
                open.value = false;
                selected.value = null;
            },
        });
};
</script>

<template>
    <Head title="Informasi & Pengumuman" />
    <AppLayout :breadcrumbs="[{ title: 'Informasi & Pengumuman', href: route('admin.info-kuliah.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Informasi & Pengumuman</h1>
                        <p class="deskripsi-halaman">Kelola informasi & pengumuman yang tampil di halaman depan dan menu Pengumuman.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.info-kuliah.create')">Tambah Informasi</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[820px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kategori</th>
                                    <th>Informasi</th>
                                    <th>File</th>
                                    <th>Uploader</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.infoKuliahs.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.infoKuliahs.from ?? 1) + index }}</td>
                                    <td>{{ item.kategori ?? '-' }}</td>
                                    <td class="whitespace-pre-line">
                                        {{ item.information }}
                                    </td>
                                    <td>
                                        <a
                                            :href="route('berkas.info-kuliah', item.id)"
                                            target="_blank"
                                            class="font-medium text-[#0075de] hover:underline"
                                            >Lihat file</a
                                        >
                                    </td>
                                    <td>{{ item.uploader?.name ?? '-' }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="route('admin.info-kuliah.edit', item.id)" title="Edit" aria-label="Edit"
                                                    ><Pencil
                                                /></Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus"
                                                @click="remove(item)"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.infoKuliahs.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada informasi & pengumuman. Tambahkan yang baru untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="props.infoKuliahs.links ?? []" :total="props.infoKuliahs.data.length" />
                <AlertModal
                    :open="open"
                    description="Anda yakin ingin menghapus data ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="open = $event"
                    @confirm="confirmDelete"
                    @cancel="open = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
