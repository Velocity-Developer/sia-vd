<?php

namespace App\Http\Middleware;

use App\Models\PengaturanInstitusi;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'institusi' => fn (): array => PengaturanInstitusi::shared(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user()?->withoutRelations(),
                'role' => fn (): ?array => $request->user()?->role?->only(['id', 'name', 'slug', 'user_type']),
                'permissions' => fn (): array => $request->user()?->permissionKeys() ?? [],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                'materi_success' => fn (): ?string => $request->session()->get('materi_success'),
                'materi_error' => fn (): ?string => $request->session()->get('materi_error'),
                'jadwal_success' => fn (): ?string => $request->session()->get('jadwal_success'),
                'jadwal_error' => fn (): ?string => $request->session()->get('jadwal_error'),
                'tugas_success' => fn (): ?string => $request->session()->get('tugas_success'),
                'tugas_error' => fn (): ?string => $request->session()->get('tugas_error'),
                'quiz_success' => fn (): ?string => $request->session()->get('quiz_success'),
                'quiz_error' => fn (): ?string => $request->session()->get('quiz_error'),
                'question_success' => fn (): ?string => $request->session()->get('question_success'),
                'question_error' => fn (): ?string => $request->session()->get('question_error'),
                'krs_success' => fn (): ?string => $request->session()->get('krs_success'),
                'krs_error' => fn (): ?string => $request->session()->get('krs_error'),
                'pindah_kelas_success' => fn (): ?string => $request->session()->get('pindah_kelas_success'),
                'pindah_kelas_error' => fn (): ?string => $request->session()->get('pindah_kelas_error'),
                'pindah_kelas_warning' => fn (): ?string => $request->session()->get('pindah_kelas_warning'),
            ],
        ];
    }
}
