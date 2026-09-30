<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import TimePicker from '@/components/TimePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFitur } from '@/composables/useFitur';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, MODE_UJIAN, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type Opsi = { id: number; name: string };
type Ujian = {
    id: number;
    kelas_id: number;
    jenis: JenisUjian;
    mode: ModeUjian;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang_id: number | null;
    pengawas: string | null;
    petunjuk: string | null;
    status: 'draf' | 'terbit';
    kelas_kuliah?: { kode_kelas: string; mata_kuliah?: { nama_matkul: string } | null } | null;
};

const props = defineProps<{
    ujian: Ujian | null;
    tahunAkademikId: number | null;
    kelasOptions: Opsi[];
    kelasRemidiOptions: Opsi[];
    kelasSusulanOptions: Record<'uts_susulan' | 'uas_susulan', Opsi[]>;
    batasRemidi: { bayar: string | null; awal: string | null; awal_label: string | null; nilai: string | null };
    menungguVerifikasi: Record<number, number>;
    ruangOptions: Opsi[];
    jenisAwal: JenisUjian | null;
}>();

const form = useForm({
    kelas_id: '' as number | string,
    jenis: (props.jenisAwal ?? 'uts') as JenisUjian,
    mode: props.ujian?.mode ?? ('tatap_muka' as ModeUjian),
    tanggal: props.ujian?.tanggal.slice(0, 10) ?? '',
    jam_mulai: jam(props.ujian?.jam_mulai),
    jam_akhir: jam(props.ujian?.jam_akhir),
    ruang_id: (props.ujian?.ruang_id ?? '') as number | string,
    pengawas: props.ujian?.pengawas ?? '',
    petunjuk: props.ujian?.petunjuk ?? '',
    status: props.ujian?.status ?? ('draf' as 'draf' | 'terbit'),
    abaikan_bentrok_mahasiswa: false,
});

// Kolom kelas & jenis hanya dikirim saat membuat jadwal baru.
const simpan = () => {
    form.transform((data) => {
        const { kelas_id, jenis, ...sisa } = data;
        return { ...(props.ujian ? {} : { kelas_id, jenis }), ...sisa, ruang_id: data.ruang_id || null };
    });
    if (props.ujian) form.put(route('admin.ujian.update', props.ujian.id));
    else form.post(route('admin.ujian.store'));
};
const bentrokMahasiswa = computed(() => (form.errors.tanggal ?? '').includes('punya ujian lain'));
const remidi = computed(() => (props.ujian?.jenis ?? form.jenis) === 'remidi');
// Tanpa fitur keuangan, remidi dan susulan tidak bersyarat bayar.
const keuangan = useFitur().aktif('keuangan');
// Remidi hanya untuk kelas yang daftar remidinya dikunci dan punya peserta lunas.
const kelasTerpilih = computed(() => Number(props.ujian?.kelas_id ?? form.kelas_id) || 0);
const jumlahMenunggu = computed(() => (remidi.value ? (props.menungguVerifikasi[kelasTerpilih.value] ?? 0) : 0));
const jenisAktif = computed(() => props.ujian?.jenis ?? form.jenis);
const susulan = computed(() => jenisAktif.value === 'uts_susulan' || jenisAktif.value === 'uas_susulan');
// Susulan hanya untuk kelas yang ujian utamanya punya pemohon lunas dan belum dijadwalkan.
const opsiKelas = computed(() => {
    if (remidi.value) return props.kelasRemidiOptions;
    if (jenisAktif.value === 'uts_susulan' || jenisAktif.value === 'uas_susulan') return props.kelasSusulanOptions[jenisAktif.value];

    return props.kelasOptions;
});
watch(
    () => form.jenis,
    () => {
        if (!opsiKelas.value.some((k) => k.id === form.kelas_id)) form.kelas_id = '';
    },
);
const judul = computed(() => (props.ujian ? 'Ubah Jadwal Ujian' : 'Tambah Jadwal Ujian'));
</script>

<template>
    <Head :title="judul" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Jadwal Ujian', href: route('admin.ujian.index') },
            { title: judul, href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ judul }}</h1>
                        <p v-if="props.ujian" class="deskripsi-halaman">
                            {{ JENIS_UJIAN[props.ujian.jenis] }} · {{ props.ujian.kelas_kuliah?.kode_kelas }} —
                            {{ props.ujian.kelas_kuliah?.mata_kuliah?.nama_matkul }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="route('admin.ujian.index', { tahun_akademik_id: props.tahunAkademikId })">Kembali</Link>
                    </Button>
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="simpan">
                    <section v-if="!props.ujian" class="kartu p-6">
                        <div class="grid items-start gap-4 sm:grid-cols-[1fr,300px]">
                            <div class="grid gap-2">
                                <Label for="kelas_id" class="label-isian">Kelas</Label>
                                <SearchSelect
                                    id="kelas_id"
                                    v-model="form.kelas_id"
                                    :options="opsiKelas"
                                    placeholder="Pilih kelas"
                                    search-placeholder="Cari kode kelas atau mata kuliah"
                                    required
                                />
                                <p v-if="remidi && !props.kelasRemidiOptions.length" class="teks-bantu">
                                    Belum ada kelas yang daftar remidinya dikunci dan punya peserta{{ keuangan ? ' lunas' : '' }}.
                                </p>
                                <p v-if="susulan && !opsiKelas.length" class="teks-bantu">
                                    Belum ada kelas dengan pemohon susulan yang disetujui{{ keuangan ? ' dan tagihannya lunas' : '' }}.
                                </p>
                                <p v-if="jumlahMenunggu" class="text-xs text-[#dd5b00]">
                                    {{ jumlahMenunggu }} bukti bayar kelas ini masih menunggu verifikasi. Verifikasi dulu di Tagihan Remidi agar
                                    pesertanya tidak tertinggal ujian.
                                </p>
                                <InputError :message="form.errors.kelas_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label class="label-isian">Jenis ujian</Label>
                                <div class="flex min-h-10 flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                    <label v-for="(label, j) in JENIS_UJIAN" :key="j" class="flex items-center gap-2">
                                        <input v-model="form.jenis" type="radio" :value="j" class="size-4 accent-[#0075de]" /> {{ label }}
                                    </label>
                                </div>
                                <InputError :message="form.errors.jenis" />
                            </div>
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Mode ujian</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Mode ujian">
                            <label
                                v-for="m in MODE_UJIAN"
                                :key="m.value"
                                class="flex cursor-pointer gap-3 rounded-lg border p-3"
                                :class="form.mode === m.value ? 'border-[#0075de] bg-[#0075de]/5' : 'border-[#e6e6e6] dark:border-border'"
                            >
                                <input v-model="form.mode" type="radio" :value="m.value" class="mt-1 size-4 shrink-0 accent-[#0075de]" />
                                <span>
                                    <span class="block text-sm font-medium text-black dark:text-foreground">{{ m.label }}</span>
                                    <span class="teks-bantu block">{{ m.teks }}</span>
                                </span>
                            </label>
                        </div>
                        <InputError :message="form.errors.mode" />
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Waktu &amp; tempat</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="tanggal" class="label-isian">Tanggal</Label>
                                <DatePicker id="tanggal" v-model="form.tanggal" placeholder="Pilih tanggal" />
                                <p v-if="susulan" class="teks-bantu">Tidak sebelum ujian utamanya, paling lambat batas input nilai kelas.</p>
                                <p v-if="remidi && props.batasRemidi.awal" class="teks-bantu">
                                    Sesudah {{ props.batasRemidi.awal_label }} {{ formatTanggal(props.batasRemidi.awal, false) }} s.d.
                                    {{ formatTanggal(props.batasRemidi.nilai, false) }}
                                </p>
                                <InputError :message="form.errors.tanggal" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jam_mulai" class="label-isian">Jam mulai</Label>
                                <TimePicker id="jam_mulai" v-model="form.jam_mulai" required />
                                <InputError :message="form.errors.jam_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jam_akhir" class="label-isian">Jam selesai</Label>
                                <TimePicker id="jam_akhir" v-model="form.jam_akhir" required />
                                <InputError :message="form.errors.jam_akhir" />
                            </div>
                            <div v-if="form.mode === 'tatap_muka'" class="grid gap-2 sm:col-span-2">
                                <Label for="ruang_id" class="label-isian">Ruang</Label>
                                <select id="ruang_id" v-model="form.ruang_id" class="isian isian-pilih" required>
                                    <option value="">Pilih ruang</option>
                                    <option v-for="r in props.ruangOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                                <InputError :message="form.errors.ruang_id" />
                            </div>
                            <p v-else class="text-sm text-[#615d59] sm:col-span-2">Ujian online dikerjakan di SIA, tanpa ruang.</p>
                            <div class="grid gap-2">
                                <Label for="pengawas" class="label-isian">Pengawas (opsional)</Label>
                                <Input id="pengawas" v-model="form.pengawas" maxlength="255" />
                                <InputError :message="form.errors.pengawas" />
                            </div>
                            <div class="grid gap-2 sm:col-span-3">
                                <Label for="petunjuk" class="label-isian">Petunjuk untuk mahasiswa (opsional)</Label>
                                <textarea
                                    id="petunjuk"
                                    v-model="form.petunjuk"
                                    rows="3"
                                    maxlength="5000"
                                    placeholder="mis. Buku tertutup, bawa kalkulator, datang 15 menit lebih awal"
                                    class="isian isian-area"
                                />
                                <InputError :message="form.errors.petunjuk" />
                            </div>
                        </div>
                        <label v-if="bentrokMahasiswa || form.abaikan_bentrok_mahasiswa" class="mt-4 flex items-center gap-2 text-sm text-[#dd5b00]">
                            <input v-model="form.abaikan_bentrok_mahasiswa" type="checkbox" class="size-4 accent-[#0075de]" />
                            Tetap simpan walau ada mahasiswa dengan dua ujian di jam yang sama
                        </label>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Status</h2>
                        <div class="mt-3 flex flex-wrap gap-6 text-sm">
                            <label class="flex items-center gap-2"
                                ><input v-model="form.status" type="radio" value="draf" class="size-4 accent-[#0075de]" /> Draf (belum tampil ke
                                mahasiswa)</label
                            >
                            <label class="flex items-center gap-2"
                                ><input v-model="form.status" type="radio" value="terbit" class="size-4 accent-[#0075de]" /> Terbit</label
                            >
                        </div>
                        <InputError :message="form.errors.status" />
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button as-child variant="outline">
                            <Link :href="route('admin.ujian.index', { tahun_akademik_id: props.tahunAkademikId })">Batal</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
