<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PengaturanSistemController extends Controller
{
    /**
     * Tab Pengaturan Sistem beserta izin yang dibutuhkan, sesuai urutan tampil.
     */
    public const TAB = [
        'institusi' => 'admin.institusi',
        'email' => 'admin.pengaturan-email',
        'akademik' => 'admin.pengaturan-akademik',
        'tampilan' => 'admin.pengaturan-tampilan',
    ];

    /**
     * Menu Pengaturan Sistem membuka tab pertama yang boleh diakses pengguna.
     */
    public function index(Request $request): RedirectResponse
    {
        foreach (self::TAB as $tab => $izin) {
            if ($request->user()->hasPermission($izin)) {
                return to_route('pengaturan-sistem.'.$tab);
            }
        }

        abort(403);
    }
}
