<?php

namespace App\Http\Requests\Auth;

use App\CaptchaGambar;
use App\Models\PengaturanMaintenance;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['captcha.required' => 'Ketik kode pada gambar captcha.'];
    }

    /**
     * Captcha gambar dicocokkan sesudah username & kata sandi terisi, sebelum kata sandi diperiksa.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! app(CaptchaGambar::class)->cocok($this->input('captcha'))) {
                    $validator->errors()->add('captcha', 'Kode captcha salah atau kedaluwarsa. Ketik kode pada gambar yang baru.');
                }
            },
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Kolom masuk menerima username, NIM, atau NIDN (lihat User::usernameUntukMasuk).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $masuk = trim($this->string('username'));
        $kredensial = [
            'username' => User::usernameUntukMasuk($masuk) ?? $masuk,
            'password' => $this->string('password')->toString(),
        ];

        if (! Auth::validate($kredensial)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Status baru diperiksa sesudah kata sandi cocok, agar status akun tidak bocor ke orang lain.
        /** @var User $user */
        $user = Auth::getLastAttempted();
        if (($alasan = $user->alasanTidakBolehMasuk()) !== null) {
            throw ValidationException::withMessages(['username' => $alasan]);
        }

        // Sama seperti status akun: maintenance baru diberitahukan sesudah kata sandi cocok.
        if (PengaturanMaintenance::menghalangi($user)) {
            throw ValidationException::withMessages(['username' => PengaturanMaintenance::shared()['pesan']]);
        }

        Auth::login($user, $this->boolean('remember'));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
