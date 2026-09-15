<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => 'Format :attribute tidak valid.',
    'integer' => ':attribute harus berupa angka.',
    'numeric' => ':attribute harus berupa angka.',
    'digits' => ':attribute harus terdiri dari :digits digit.',
    'date' => 'Format :attribute tidak valid.',
    'unique' => ':attribute sudah digunakan.',
    'exists' => ':attribute tidak ditemukan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'in' => 'Pilihan :attribute tidak valid.',
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'file' => 'Ukuran :attribute minimal :min kilobita.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal memiliki :min item.',
    ],
    'max' => [
        'numeric' => ':attribute maksimal :max.',
        'file' => 'Ukuran :attribute maksimal :max kilobita.',
        'string' => ':attribute maksimal :max karakter.',
        'array' => ':attribute maksimal memiliki :max item.',
    ],
    'mimes' => ':attribute harus berupa berkas dengan format: :values.',
    'file' => ':attribute harus berupa berkas.',
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'same' => ':attribute harus sama dengan :other.',
    'different' => ':attribute tidak boleh sama dengan :other.',
    'attributes' => [
        'current_password' => 'kata sandi saat ini',
        'password_confirmation' => 'konfirmasi kata sandi',
        'file_jawaban.*' => 'berkas jawaban',
        'target_ids.*' => 'kelas tujuan',
    ],
];
