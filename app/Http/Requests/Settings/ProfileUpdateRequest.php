<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Nama mahasiswa tercetak di KHS/transkrip, jadi hanya bisa diubah admin lewat data mahasiswa.
            'name' => $this->namaTerkunci() ? ['exclude'] : ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function namaTerkunci(): bool
    {
        return $this->user()->type() === UserType::Mahasiswa;
    }
}
