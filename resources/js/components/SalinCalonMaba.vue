<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { UserRoundPlus } from 'lucide-vue-next';
import { ref } from 'vue';

// Tombol + konfirmasi "Salin ke Master Mahasiswa" untuk calon maba yang lulus.
const props = defineProps<{ id: number; nama: string; ringkas?: boolean }>();

const buka = ref(false);
const memproses = ref(false);

const salin = () =>
    router.post(
        route('admin.pendaftar-pmb.salin', props.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (memproses.value = true),
            onFinish: () => {
                memproses.value = false;
                buka.value = false;
            },
        },
    );
</script>

<template>
    <Button
        v-if="props.ringkas"
        variant="outline"
        size="icon-sm"
        class="text-[#0075de]"
        title="Salin ke Master Mahasiswa"
        aria-label="Salin ke Master Mahasiswa"
        @click="buka = true"
        ><UserRoundPlus
    /></Button>
    <Button v-else @click="buka = true"><UserRoundPlus /> Salin ke Master Mahasiswa</Button>
    <AlertModal
        :open="buka"
        title="Salin ke Master Mahasiswa?"
        :description="`Akun mahasiswa untuk ${props.nama} dibuat dengan username nomor pendaftaran, lalu tautan atur sandi dan verifikasi email dikirim. NIM dan dosen wali dilengkapi di Data Mahasiswa.`"
        confirm-text="Salin"
        cancel-text="Batal"
        :loading="memproses"
        :destructive="false"
        @update:open="buka = $event"
        @confirm="salin"
        @cancel="buka = false"
    />
</template>
