<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

type Kurikulum = {
    id: number;
    nama: string;
    prodi: string | null;
    mulai_berlaku: string | null;
    aktif: boolean;
    jumlah_mk: number;
    total_sks: number;
};

const props = defineProps<{
    kurikulum: Kurikulum[];
    prodiOptions: { id: number; name: string }[];
    prodiId: number | null;
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const prodiId = ref<number | string>(props.prodiId ?? 'all');
const saring = () =>
    router.get(route('admin.kurikulum.index'), prodiId.value === 'all' ? {} : { prodi_id: prodiId.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

const confirmOpen = ref(false);
const pending = ref<Kurikulum | null>(null);
const hapus = (item: Kurikulum) => {
    pending.value = item;
    confirmOpen.value = true;
};
const konfirmasiHapus = () => {
    if (!pending.value) return;
    router.delete(route('admin.kurikulum.destroy', pending.value.id), {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            pending.value = null;
        },
    });
};
</script>

<template>
    <Head title="Kurikulum" />
    <AppLayout :breadcrumbs="[{ title: 'Kurikulum', href: route('admin.kurikulum.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Kurikulum</h1>
                        <p class="deskripsi-halaman">Daftar kurikulum per program studi beserta mata kuliahnya, termasuk MK TA/Skripsi dan PPL.</p>
                    </div>
                    <Button as-child><Link :href="route('admin.kurikulum.create')">Tambah Kurikulum</Link></Button>
                </div>

                <div v-if="props.prodiOptions.length > 1" class="bilah-filter">
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="saring">
                        <option value="all">Semua Program Studi</option>
                        <option v-for="prodi in props.prodiOptions" :key="prodi.id" :value="prodi.id">{{ prodi.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.kurikulum.length }}</span> kurikulum
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[880px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Nama Kurikulum</th>
                                    <th>Program Studi</th>
                                    <th>Mulai Berlaku</th>
                                    <th class="text-center">Jumlah MK</th>
                                    <th class="text-center">Total SKS</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.kurikulum" :key="item.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="font-medium text-black">{{ item.nama }}</td>
                                    <td>{{ item.prodi ?? '-' }}</td>
                                    <td>{{ item.mulai_berlaku ?? '-' }}</td>
                                    <td class="text-center">{{ item.jumlah_mk }}</td>
                                    <td class="text-center">{{ item.total_sks }}</td>
                                    <td>
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="item.aktif ? 'bg-[#1aae39]/10 text-[#137a2a]' : 'bg-[#a39e98]/15 text-[#615d59]'"
                                            >{{ item.aktif ? 'Aktif' : 'Tidak aktif' }}</span
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]"
                                                ><Link :href="route('admin.kurikulum.show', item.id)" title="Detail" aria-label="Detail"><Eye /></Link
                                            ></Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route('admin.kurikulum.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link
                                            ></Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus"
                                                @click="hapus(item)"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.kurikulum.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Belum ada kurikulum. Tambahkan kurikulum untuk program studi Anda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <AlertModal
                    :open="confirmOpen"
                    :description="`Hapus kurikulum ${pending?.nama ?? ''}? Daftar mata kuliah di kurikulum ini ikut terhapus, data mata kuliahnya tetap ada.`"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="konfirmasiHapus"
                    @cancel="confirmOpen = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
