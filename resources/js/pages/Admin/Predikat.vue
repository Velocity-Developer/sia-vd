<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Item = { id: number; nama: string; bobot_minimal: number; bobot_maksimal: number };

const props = defineProps<{ predikat: Item[] }>();

const angka = (nilai: number) => Number(nilai).toFixed(2);

const confirmOpen = ref(false);
const pendingItem = ref<Item | null>(null);

const remove = (item: Item) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.predikat.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Predikat" />
    <AppLayout :breadcrumbs="[{ title: 'Predikat', href: route('admin.predikat.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Predikat</h1>
                        <p class="deskripsi-halaman">
                            Predikat kelulusan menurut rentang IPK. Predikat ditetapkan saat SKL terbit; SKL yang sudah terbit tidak berubah.
                        </p>
                    </div>
                    <Button as-child><Link :href="route('admin.predikat.create')">Tambah Predikat</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[620px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Nama Predikat</th>
                                    <th>Bobot Minimal</th>
                                    <th>Bobot Maksimal</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.predikat" :key="item.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="font-medium text-black">{{ item.nama }}</td>
                                    <td class="tabular-nums">{{ angka(item.bobot_minimal) }}</td>
                                    <td class="tabular-nums">{{ angka(item.bobot_maksimal) }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.predikat.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link
                                            ></Button>
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
                                <tr v-if="!props.predikat.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">Predikat akan tampil di sini. Tambahkan predikat untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <AlertModal
                    :open="confirmOpen"
                    description="Anda yakin ingin menghapus data ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="confirmDelete"
                    @cancel="confirmOpen = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
