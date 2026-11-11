<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\TourGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DokumenController extends Controller
{
    /**
     * Unduh atau lihat dokumen privat terproteksi (KTP, Bukti Usaha, Sertifikat).
     *
     * @param  string  $type  'guide' atau 'mitra'
     * @param  int  $id  ID tour_guide atau mitra
     * @param  string  $field  'foto_ktp', 'sertifikat_lisensi', atau 'bukti_usaha'
     */
    public function show(Request $request, string $type, int $id, string $field): Response
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $filePath = null;
        $ownerUserId = null;

        if ($type === 'guide') {
            $guide = TourGuide::findOrFail($id);
            $ownerUserId = $guide->user_id;

            if ($field === 'foto_ktp') {
                $filePath = $guide->foto_ktp;
            } elseif ($field === 'sertifikat_lisensi') {
                $filePath = $guide->sertifikat_lisensi;
            }
        } elseif ($type === 'mitra') {
            $mitra = Mitra::findOrFail($id);
            $ownerUserId = $mitra->user_id;

            if ($field === 'foto_ktp') {
                $filePath = $mitra->foto_ktp;
            } elseif ($field === 'bukti_usaha') {
                $filePath = $mitra->bukti_usaha;
            }
        }

        // Cek hak akses: hanya Super Admin atau pemilik dokumen
        if (! $user->isSuperAdmin() && $user->id !== $ownerUserId) {
            abort(403, 'Anda tidak berhak mengakses dokumen ini.');
        }

        if (! $filePath) {
            abort(404, 'Dokumen belum diunggah.');
        }

        // Cek file di storage (private disk atau local storage)
        $disk = Storage::disk('local');
        if (! $disk->exists($filePath)) {
            abort(404, 'File dokumen tidak ditemukan di server.');
        }

        return response()->file($disk->path($filePath));
    }
}
