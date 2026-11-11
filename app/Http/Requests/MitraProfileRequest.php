<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MitraProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'nama_usaha' => 'required|string|max:255',
            'alamat' => 'required|string|max:500',
            'foto_ktp' => 'required|file|mimes:jpg,png,pdf|max:2048',
            'bukti_usaha' => 'required|file|mimes:jpg,png,pdf|max:2048',
        ];

        if (session('user.role') === 'tour_guide') {
            $rules['bahasa'] = 'required|string';
            $rules['tarif'] = 'required|numeric|min:0';
            $rules['foto'] = 'nullable|file|mimes:jpg,png|max:2048';
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nama_usaha.required' => 'Nama usaha wajib diisi.',
            'alamat.required' => 'Alamat wajib diisi.',
            'foto_ktp.required' => 'Foto KTP wajib diunggah.',
            'foto_ktp.mimes' => 'Foto KTP harus berformat JPG, PNG, atau PDF.',
            'foto_ktp.max' => 'Ukuran foto KTP maksimal 2MB.',
            'bukti_usaha.required' => 'Bukti usaha wajib diunggah.',
            'bukti_usaha.mimes' => 'Bukti usaha harus berformat JPG, PNG, atau PDF.',
            'bukti_usaha.max' => 'Ukuran bukti usaha maksimal 2MB.',
            'bahasa.required' => 'Bahasa yang dikuasai wajib diisi.',
            'tarif.required' => 'Tarif per hari wajib diisi.',
            'tarif.numeric' => 'Tarif harus berupa angka.',
        ];
    }
}
