export type Peran = 'admin' | 'dosen';

/**
 * Halaman kelas dipakai bersama admin dan dosen; nama route-nya hanya beda prefix
 * (admin.kelas-kuliah.* / dosen.kelas-kuliah.*).
 */
export const rutePeran =
    (peran: Peran) =>
    (nama: string, params?: Parameters<typeof route>[1]): string =>
        route(`${peran}.${nama}`, params);
