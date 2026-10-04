<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { labelJenisPenilaian } from '@/lib/penilaian';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Kurikulum = { id: number; nama: string; prodi: string | null; mulai_berlaku: string | null; aktif: boolean; keterangan: string | null };
type MkKurikulum = { id: number; kode_matkul: string; nama_matkul: string; sks: number; jenis_penilaian: string; semester: number; jenis: string };
type PilihanMk = { id: number; kode_matkul: string; nama_matkul: string; sks: number; semester: number; jenis: string; jenis_penilaian: string };

const props = defineProps<{ kurikulum: Kurikulum; mataKuliah: MkKurikulum[]; pilihanMataKuliah: PilihanMk[] }>();

const page = usePage<{ flash: { success?: string; error?: string }; errors: Record<string, string> }>();

// Dikelompokkan per semester dengan subtotal SKS.
const perSemester = computed(() => {
    const grup = new Map<number, MkKurikulum[]>();
    for (const mk of props.mataKuliah) grup.set(mk.semester, [...(grup.get(mk.semester) ?? []), mk]);
    return [...grup.entries()]
        .sort(([a], [b]) => a - b)
        .map(([semester, items]) => ({ semester, items, sks: items.reduce((n, mk) => n + mk.sks, 0) }));
});
const totalSks = computed(() => props.mataKuliah.reduce((n, mk) => n + mk.sks, 0));
const sksWajib = computed(() => props.mataKuliah.filter((mk) => mk.jenis === 'Wajib').reduce((n, mk) => n + mk.sks, 0));

const ubah = (mk: MkKurikulum, kolom: 'semester' | 'jenis', nilai: string) =>
    router.put(
        route('admin.kurikulum.mata-kuliah.update', [props.kurikulum.id, mk.id]),
        { semester: kolom === 'semester' ? Number(nilai) : mk.semester, jenis: kolom === 'jenis' ? nilai : mk.jenis },
        { preserveScroll: true },
    );

const cari = ref('');
const pilihanTersaring = computed(() => {
    const kata = cari.value.trim().toLowerCase();
    return kata === ''
        ? props.pilihanMataKuliah
        : props.pilihanMataKuliah.filter((mk) => `${mk.kode_matkul} ${mk.nama_matkul}`.toLowerCase().includes(kata));
});
const tambahForm = useForm({ mata_kuliah_ids: [] as number[] });
const centang = (id: number, aktif: boolean | 'indeterminate') => {
    tambahForm.mata_kuliah_ids = aktif === true ? [...tambahForm.mata_kuliah_ids, id] : tambahForm.mata_kuliah_ids.filter((x) => x !== id);
};
const centangSemua = (aktif: boolean | 'indeterminate') => {
    tambahForm.mata_kuliah_ids = aktif === true ? pilihanTersaring.value.map((mk) => mk.id) : [];
};
const tambah = () =>
    tambahForm.post(route('admin.kurikulum.mata-kuliah.store', props.kurikulum.id), {
        preserveScroll: true,
        onSuccess: () => tambahForm.reset(),
    });

const confirmOpen = ref(false);
const pending = ref<MkKurikulum | null>(null);
const keluarkan = () => {
    if (!pending.value) return;
    router.delete(route('admin.kurikulum.mata-kuliah.destroy', [props.kurikulum.id, pending.value.id]), {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            pending.value = null;
        },
    });
};
</script>

<template>
    <Head :title="props.kurikulum.nama" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Kurikulum', href: route('admin.kurikulum.index') },
            { title: props.kurikulum.nama, href: route('admin.kurikulum.show', props.kurikulum.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.kurikulum.nama }}</h1>
                        <p class="deskripsi-halaman">{{ props.kurikulum.prodi }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"><Link :href="route('admin.kurikulum.index')">Kembali</Link></Button>
                        <Button as-child><Link :href="route('admin.kurikulum.edit', props.kurikulum.id)">Edit</Link></Button>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>
                <div v-if="page.props.errors?.semester || page.props.errors?.jenis" class="alert-gagal" role="alert">
                    {{ page.props.errors.semester ?? page.props.errors.jenis }}
                </div>

                <section class="kartu p-6">
                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Mulai Berlaku</dt>
                            <dd class="text-sm font-medium text-black">{{ props.kurikulum.mulai_berlaku ?? '-' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Status</dt>
                            <dd class="text-sm font-medium text-black">{{ props.kurikulum.aktif ? 'Aktif' : 'Tidak aktif' }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Jumlah Mata Kuliah</dt>
                            <dd class="text-sm font-medium text-black">{{ props.mataKuliah.length }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Total SKS</dt>
                            <dd class="text-sm font-medium text-black">
                                {{ totalSks }} SKS ({{ sksWajib }} wajib, {{ totalSks - sksWajib }} pilihan)
                            </dd>
                        </div>
                        <div v-if="props.kurikulum.keterangan" class="space-y-1 sm:col-span-2 lg:col-span-4">
                            <dt class="teks-bantu">Keterangan</dt>
                            <dd class="whitespace-pre-line text-sm text-black">{{ props.kurikulum.keterangan }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="judul-bagian">Mata Kuliah Kurikulum</h2>
                    <div v-if="!perSemester.length" class="kartu p-6">
                        <p class="teks-bantu">Belum ada mata kuliah. Tambahkan dari daftar di bawah.</p>
                    </div>
                    <div v-for="grup in perSemester" :key="grup.semester" class="tabel-wadah">
                        <div class="flex items-center justify-between border-b border-[#e6e6e6] px-4 py-3">
                            <h3 class="text-sm font-semibold text-black">Semester {{ grup.semester }}</h3>
                            <span class="teks-bantu">{{ grup.items.length }} MK · {{ grup.sks }} SKS</span>
                        </div>
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[760px]">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama Mata Kuliah</th>
                                        <th class="text-center">SKS</th>
                                        <th class="w-28">Semester</th>
                                        <th class="w-32">Sifat</th>
                                        <th class="kolom-aksi"><span class="sr-only">Keluarkan</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="mk in grup.items" :key="mk.id">
                                        <td class="font-medium text-black">{{ mk.kode_matkul }}</td>
                                        <td>
                                            {{ mk.nama_matkul }}
                                            <span
                                                v-if="mk.jenis_penilaian !== 'reguler'"
                                                class="ml-1 inline-flex rounded-full bg-[#1aae39]/10 px-2 py-0.5 text-xs font-medium text-[#137a2a]"
                                                >{{ labelJenisPenilaian[mk.jenis_penilaian] ?? mk.jenis_penilaian }}</span
                                            >
                                        </td>
                                        <td class="text-center">{{ mk.sks }}</td>
                                        <td>
                                            <select
                                                :value="mk.semester"
                                                class="isian isian-pilih"
                                                :aria-label="`Semester ${mk.nama_matkul}`"
                                                @change="ubah(mk, 'semester', ($event.target as HTMLSelectElement).value)"
                                            >
                                                <option v-for="n in 14" :key="n" :value="n">{{ n }}</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select
                                                :value="mk.jenis"
                                                class="isian isian-pilih"
                                                :aria-label="`Sifat ${mk.nama_matkul}`"
                                                @change="ubah(mk, 'jenis', ($event.target as HTMLSelectElement).value)"
                                            >
                                                <option value="Wajib">Wajib</option>
                                                <option value="Pilihan">Pilihan</option>
                                            </select>
                                        </td>
                                        <td class="kolom-aksi">
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Keluarkan dari kurikulum"
                                                aria-label="Keluarkan dari kurikulum"
                                                @click="
                                                    pending = mk;
                                                    confirmOpen = true;
                                                "
                                                ><Trash2
                                            /></Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Tambah Mata Kuliah</h2>
                    <p class="teks-bantu mt-1">
                        Mata kuliah {{ props.kurikulum.prodi }} yang belum masuk kurikulum ini. Semester dan sifat awal mengikuti data mata kuliah,
                        bisa diubah sesudah ditambahkan. Mata kuliah baru dibuat di menu Mata Kuliah.
                    </p>
                    <template v-if="props.pilihanMataKuliah.length">
                        <div class="relative mt-4 max-w-md">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input v-model="cari" placeholder="Cari kode atau nama mata kuliah" aria-label="Cari mata kuliah" class="pl-9" />
                        </div>
                        <div class="tabel-wadah mt-3 shadow-none">
                            <div class="tabel-gulir max-h-96 overflow-y-auto">
                                <table class="tabel min-w-[640px]">
                                    <thead>
                                        <tr>
                                            <th class="w-10">
                                                <Checkbox
                                                    :model-value="
                                                        pilihanTersaring.length > 0 && tambahForm.mata_kuliah_ids.length === pilihanTersaring.length
                                                    "
                                                    aria-label="Pilih semua"
                                                    @update:model-value="centangSemua"
                                                />
                                            </th>
                                            <th>Kode</th>
                                            <th>Nama Mata Kuliah</th>
                                            <th class="text-center">SKS</th>
                                            <th class="text-center">Semester</th>
                                            <th>Sifat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="mk in pilihanTersaring" :key="mk.id">
                                            <td>
                                                <Checkbox
                                                    :model-value="tambahForm.mata_kuliah_ids.includes(mk.id)"
                                                    :aria-label="`Pilih ${mk.nama_matkul}`"
                                                    @update:model-value="centang(mk.id, $event)"
                                                />
                                            </td>
                                            <td class="font-medium text-black">{{ mk.kode_matkul }}</td>
                                            <td>{{ mk.nama_matkul }}</td>
                                            <td class="text-center">{{ mk.sks }}</td>
                                            <td class="text-center">{{ mk.semester }}</td>
                                            <td>{{ mk.jenis }}</td>
                                        </tr>
                                        <tr v-if="!pilihanTersaring.length" class="baris-kosong">
                                            <td colspan="6" class="tabel-kosong">Tidak ada mata kuliah yang cocok.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p v-if="tambahForm.errors.mata_kuliah_ids" class="mt-2 text-sm text-[#dd5b00]">{{ tambahForm.errors.mata_kuliah_ids }}</p>
                        <div class="mt-4 flex justify-end">
                            <Button type="button" :disabled="tambahForm.processing || !tambahForm.mata_kuliah_ids.length" @click="tambah">
                                Tambahkan {{ tambahForm.mata_kuliah_ids.length || '' }} Mata Kuliah
                            </Button>
                        </div>
                    </template>
                    <p v-else class="teks-bantu mt-4">Semua mata kuliah program studi ini sudah masuk kurikulum.</p>
                </section>

                <AlertModal
                    :open="confirmOpen"
                    :description="`Keluarkan ${pending?.nama_matkul ?? ''} dari kurikulum ini? Data mata kuliahnya tetap ada.`"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="keluarkan"
                    @cancel="confirmOpen = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
