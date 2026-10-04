<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_KRS, type RingkasanKrs } from '@/lib/krs';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Kelas = {
    kelas_id: number;
    kode_kelas: string | null;
    kode_matkul: string | null;
    nama_matkul: string | null;
    semester_matkul: number | null;
    sks: number;
    dosen: string | null;
    jadwal: string[];
};
type Diambil = Kelas & { id: number; nilai: string | null };
type Ditawarkan = Kelas & { label: string | null; terkunci: string | null; terisi: number; kapasitas: number | null };
type Opsi = { id: number; name: string };

const props = defineProps<{
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; mahasiswa_id: number | null };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mahasiswaOptions: Opsi[];
    krs: {
        mahasiswa: {
            id: number;
            user_id: number;
            nama: string | null;
            nim: string | null;
            prodi: string | null;
            angkatan: string | null;
            semester: number | null;
            status: string | null;
            boleh_krs: boolean;
            dosen_pa: string | null;
        };
        tahun_akademik: string;
        status: RingkasanKrs | null;
        sks_diambil: number;
        maks_sks: number;
        ips_sebelumnya: { ips: number; tahun_akademik: string } | null;
        verifikasi_aktif: boolean;
        diambil: Diambil[];
        ditawarkan: Ditawarkan[];
    } | null;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const kirim = (ubah: Record<string, unknown> = {}) =>
    router.get(
        route('admin.input-krs.index'),
        {
            tahun_akademik_id: tahunAkademikId.value,
            prodi_id: prodiId.value === semua ? null : prodiId.value,
            mahasiswa_id: props.filter.mahasiswa_id,
            ...ubah,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
const pilihMahasiswa = (id: number) => kirim({ mahasiswa_id: id });

const sisaSks = computed(() => (props.krs ? props.krs.maks_sks - props.krs.sks_diambil : 0));
const status = computed(() => (props.krs?.status?.status ? STATUS_KRS[props.krs.status.status] : null));
const penuh = (k: Ditawarkan) => k.kapasitas !== null && k.terisi >= k.kapasitas;

const proses = ref(false);
const tambah = (k: Ditawarkan) => {
    if (!props.krs) return;
    router.post(
        route('admin.input-krs.store', { mahasiswa: props.krs.mahasiswa.id, kelasKuliah: k.kelas_id }),
        {},
        {
            preserveScroll: true,
            onStart: () => (proses.value = true),
            onFinish: () => (proses.value = false),
        },
    );
};

const keluarkan = ref<Diambil | null>(null);
const konfirmasiKeluarkan = () => {
    if (!keluarkan.value) return;
    router.delete(route('admin.input-krs.destroy', keluarkan.value.id), { preserveScroll: true, onFinish: () => (keluarkan.value = null) });
};

const simpan = (setujui: boolean) => {
    if (!props.krs) return;
    router.post(
        route('admin.input-krs.simpan', props.krs.mahasiswa.id),
        { tahun_akademik_id: props.filter.tahun_akademik_id, setujui },
        { preserveScroll: true, onStart: () => (proses.value = true), onFinish: () => (proses.value = false) },
    );
};
</script>

<template>
    <Head title="Input KRS" />
    <AppLayout :breadcrumbs="[{ title: 'Input KRS', href: route('admin.input-krs.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Input KRS</h1>
                        <p class="deskripsi-halaman">
                            Isi KRS atas nama mahasiswa. Aturan tawaran, prasyarat, bentrok jadwal, kapasitas, dan batas SKS tetap berlaku, tetapi
                            tidak terikat periode KRS.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="bilah-filter">
                    <SelectFilter v-model="tahunAkademikId" label="Tahun akademik" @change="kirim()">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodiId" label="Program studi" @change="kirim({ mahasiswa_id: null })">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <div class="w-full lg:max-w-[360px] lg:flex-1">
                        <SearchSelect
                            id="mahasiswa"
                            :model-value="props.filter.mahasiswa_id"
                            :options="props.mahasiswaOptions"
                            placeholder="Pilih mahasiswa"
                            search-placeholder="Cari NIM atau nama"
                            @update:model-value="pilihMahasiswa"
                        />
                    </div>
                </div>

                <div v-if="!props.krs" class="kartu p-8 text-center text-sm text-[#615d59] dark:text-muted-foreground">
                    Pilih mahasiswa untuk mulai mengisi KRS-nya.
                </div>

                <template v-else>
                    <section class="kartu grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">Mahasiswa</p>
                            <p class="mt-1 text-sm font-medium">{{ krs!.mahasiswa.nama }}</p>
                            <p class="teks-bantu">{{ krs!.mahasiswa.nim ?? 'Tanpa NIM' }} · {{ krs!.mahasiswa.prodi }}</p>
                        </div>
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">Semester / Angkatan</p>
                            <p class="mt-1 text-sm font-medium">{{ krs!.mahasiswa.semester ?? '-' }} / {{ krs!.mahasiswa.angkatan ?? '-' }}</p>
                            <p class="teks-bantu">PA: {{ krs!.mahasiswa.dosen_pa ?? 'belum diatur' }}</p>
                        </div>
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">SKS {{ krs!.tahun_akademik }}</p>
                            <p class="mt-1 text-sm font-medium" :class="sisaSks < 0 ? 'text-[#b42318]' : ''">
                                {{ krs!.sks_diambil }} / {{ krs!.maks_sks }} SKS
                            </p>
                            <p class="teks-bantu">
                                {{
                                    krs!.ips_sebelumnya
                                        ? `IPS ${krs!.ips_sebelumnya.ips.toFixed(2)} (${krs!.ips_sebelumnya.tahun_akademik})`
                                        : 'Belum ada IPS sebelumnya'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="teks-bantu uppercase tracking-[0.08em]">Status KRS</p>
                            <span v-if="status" class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="status.kelas">{{
                                status.label
                            }}</span>
                            <p v-else class="mt-1 text-sm font-medium">Belum disimpan</p>
                            <p v-if="krs!.status?.diverifikasi_oleh" class="teks-bantu mt-1">
                                {{ formatTanggal(krs!.status.diverifikasi_pada ?? null, false) }} · {{ krs!.status.diverifikasi_oleh }}
                            </p>
                        </div>
                    </section>

                    <div v-if="!krs!.mahasiswa.boleh_krs" class="alert-gagal" role="alert">
                        Status mahasiswa ini {{ krs!.mahasiswa.status ?? 'belum diisi' }}, jadi kelas baru tidak bisa ditambahkan.
                    </div>

                    <section class="grid gap-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="judul-bagian">Kelas di KRS</h2>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-if="krs!.verifikasi_aktif"
                                    variant="outline"
                                    :disabled="proses || !krs!.diambil.length"
                                    @click="simpan(false)"
                                >
                                    Simpan (Menunggu Verifikasi)
                                </Button>
                                <Button :disabled="proses || !krs!.diambil.length" @click="simpan(true)">Simpan &amp; Setujui</Button>
                            </div>
                        </div>
                        <div class="tabel-wadah">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[760px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Mata Kuliah</th>
                                            <th>Kelas</th>
                                            <th>SKS</th>
                                            <th>Dosen</th>
                                            <th>Jadwal</th>
                                            <th class="kolom-aksi">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(k, index) in krs!.diambil" :key="k.id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td>
                                                <span class="block font-medium text-black dark:text-foreground"
                                                    >{{ k.kode_matkul }} — {{ k.nama_matkul }}</span
                                                >
                                                <span class="block text-xs text-[#a39e98]"
                                                    >Semester {{ k.semester_matkul ?? '-'
                                                    }}<template v-if="k.nilai"> · nilai {{ k.nilai }}</template></span
                                                >
                                            </td>
                                            <td>{{ k.kode_kelas }}</td>
                                            <td>{{ k.sks }}</td>
                                            <td>{{ k.dosen ?? '-' }}</td>
                                            <td>
                                                <div v-for="(j, i) in k.jadwal" :key="i">{{ j }}</div>
                                                <span v-if="!k.jadwal.length">-</span>
                                            </td>
                                            <td class="kolom-aksi">
                                                <Button size="sm" variant="outline" :disabled="!!k.nilai" @click="keluarkan = k">Keluarkan</Button>
                                            </td>
                                        </tr>
                                        <tr v-if="!krs!.diambil.length" class="baris-kosong">
                                            <td colspan="7" class="tabel-kosong">
                                                Belum ada kelas. Tambahkan dari daftar kelas yang ditawarkan di bawah.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3">
                        <h2 class="judul-bagian">Kelas yang Ditawarkan</h2>
                        <div class="tabel-wadah">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[860px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Mata Kuliah</th>
                                            <th>Kelas</th>
                                            <th>SKS</th>
                                            <th>Dosen</th>
                                            <th>Jadwal</th>
                                            <th>Terisi</th>
                                            <th class="kolom-aksi">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(k, index) in krs!.ditawarkan" :key="k.kelas_id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td>
                                                <span class="block font-medium text-black dark:text-foreground"
                                                    >{{ k.kode_matkul }} — {{ k.nama_matkul }}</span
                                                >
                                                <span class="block text-xs text-[#a39e98]">
                                                    Semester {{ k.semester_matkul ?? '-' }}
                                                    <span v-if="k.label" class="ml-1 rounded-full bg-[#f2f9ff] px-1.5 text-[#0075de]">{{
                                                        k.label
                                                    }}</span>
                                                </span>
                                                <span v-if="k.terkunci" class="mt-1 block text-xs text-[#b25000]">{{ k.terkunci }}</span>
                                            </td>
                                            <td>{{ k.kode_kelas }}</td>
                                            <td>{{ k.sks }}</td>
                                            <td>{{ k.dosen ?? '-' }}</td>
                                            <td>
                                                <div v-for="(j, i) in k.jadwal" :key="i">{{ j }}</div>
                                                <span v-if="!k.jadwal.length">-</span>
                                            </td>
                                            <td class="whitespace-nowrap" :class="penuh(k) ? 'text-[#b42318]' : ''">
                                                {{ k.terisi }}{{ k.kapasitas !== null ? ` / ${k.kapasitas}` : '' }}
                                            </td>
                                            <td class="kolom-aksi">
                                                <Button
                                                    size="sm"
                                                    :disabled="proses || !!k.terkunci || penuh(k) || !krs!.mahasiswa.boleh_krs"
                                                    @click="tambah(k)"
                                                    >Tambah</Button
                                                >
                                            </td>
                                        </tr>
                                        <tr v-if="!krs!.ditawarkan.length" class="baris-kosong">
                                            <td colspan="8" class="tabel-kosong">Tidak ada kelas lain yang ditawarkan untuk mahasiswa ini.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <p class="teks-bantu">
                        Data lengkap mahasiswa ada di
                        <Link :href="route('admin.users.mahasiswa.show', krs!.mahasiswa.user_id)" class="text-[#0075de] hover:underline"
                            >detail mahasiswa</Link
                        >.
                    </p>
                </template>
            </div>
        </div>

        <AlertModal
            :open="keluarkan !== null"
            title="Keluarkan kelas dari KRS?"
            :description="`${keluarkan?.nama_matkul ?? ''} (kelas ${keluarkan?.kode_kelas ?? ''}) dikeluarkan dari KRS ${krs?.mahasiswa.nama ?? ''}.`"
            confirm-text="Keluarkan"
            cancel-text="Batal"
            @update:open="(buka: boolean) => !buka && (keluarkan = null)"
            @confirm="konfirmasiKeluarkan"
        />
    </AppLayout>
</template>
