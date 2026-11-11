<?php

namespace App\Http\Requests;

use App\Support\DummyData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validKeys = array_merge(
            DummyData::subkategoriKeys(),
            array_keys(DummyData::kategoriList()),
            ['alam', 'budaya', 'seni', 'kuliner', 'spiritual', 'petualangan', 'keluarga']
        );

        return [
            'tgl_berangkat' => 'required|date|after_or_equal:today',
            'tgl_pulang' => 'required|date|after:tgl_berangkat',
            'durasi' => 'required|integer|min:1|max:7',
            'jumlah_orang' => 'required|integer|min:1|max:50',
            'kategori' => 'required|array|min:1',
            'kategori.*' => ['required', 'string', Rule::in($validKeys)],
            'tipe_traveler' => 'nullable|string|in:solo,couple,family,group',
            'tingkat_fisik' => 'nullable|string|in:santai,sedang,aktif',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tgl_berangkat.required' => 'Tanggal berangkat wajib diisi.',
            'tgl_berangkat.after_or_equal' => 'Tanggal berangkat minimal hari ini.',
            'tgl_pulang.required' => 'Tanggal pulang wajib diisi.',
            'tgl_pulang.after' => 'Tanggal pulang harus setelah tanggal berangkat.',
            'durasi.required' => 'Durasi liburan wajib diisi.',
            'durasi.min' => 'Durasi minimal :min hari.',
            'durasi.max' => 'Durasi maksimal :max hari.',
            'jumlah_orang.required' => 'Jumlah orang wajib diisi.',
            'jumlah_orang.min' => 'Jumlah orang minimal :min orang.',
            'jumlah_orang.max' => 'Jumlah orang maksimal :max orang.',
            'kategori.required' => 'Pilih minimal satu kategori atau subkategori wisata.',
            'kategori.min' => 'Pilih minimal satu kategori atau subkategori wisata.',
            'kategori.*.in' => 'Kategori atau subkategori yang dipilih tidak valid.',
            'tipe_traveler.in' => 'Tipe traveler yang dipilih tidak valid.',
            'tingkat_fisik.in' => 'Tingkat aktivitas fisik tidak valid.',
        ];
    }
}
