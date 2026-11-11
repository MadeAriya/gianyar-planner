<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\AuditLog;
use App\Models\Mitra;
use App\Models\TourGuide;
use App\Models\User;
use App\Support\DummyData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->withInput($request->only('email'))->with('error', 'Email atau kata sandi tidak cocok.');
        }

        if ($user->isSuspended()) {
            return back()->with('error', 'Akun Anda sedang ditangguhkan. Silakan hubungi admin untuk informasi lebih lanjut.');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('user', $user->toArray()); // backward compat

        // Jika akun pending atau rejected (dan bukan super_admin)
        if (! $user->isActive() && ! $user->isSuperAdmin()) {
            return redirect()->route('status.pengajuan');
        }

        $intended = $request->session()->pull('url.intended');
        if ($intended) {
            return redirect($intended);
        }

        return match ($user->role) {
            'super_admin', 'admin' => redirect()->route('admin.dashboard'),
            'tour_guide' => redirect()->route('guide.dashboard'),
            'mitra', 'penyedia_gear' => redirect()->route('mitra.dashboard'),
            default => redirect()->route('preferensi.create'),
        };
    }

    public function showRegister(Request $request): View
    {
        $preselectedRole = $request->query('role', 'user');
        if ($preselectedRole === 'wisatawan') {
            $preselectedRole = 'user';
        }
        if ($preselectedRole === 'penyedia_gear') {
            $preselectedRole = 'mitra';
        }

        $kategoriList = DummyData::kategoriList();

        return view('auth.register', compact('preselectedRole', 'kategoriList'));
    }

    public function register(Request $request): RedirectResponse
    {
        $role = $request->input('role', 'user');
        if ($role === 'wisatawan') {
            $role = 'user';
        }
        if ($role === 'penyedia_gear') {
            $role = 'mitra';
        }

        // 1. Validasi Dasar
        $rules = [
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'no_hp' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,tour_guide,mitra,wisatawan,penyedia_gear',
        ];

        // 2. Validasi Khusus Tour Guide
        if ($role === 'tour_guide') {
            $rules['bahasa'] = 'required|string';
            $rules['spesialisasi'] = 'required|array|min:1';
            $rules['area_layanan'] = 'required|string|max:255';
            $rules['tarif_per_hari'] = 'required|integer|min:50000';
            $rules['pengalaman_tahun'] = 'required|integer|min:0';
            $rules['nomor_lisensi'] = 'nullable|string|max:100';
            $rules['foto_ktp'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:2048';
            $rules['sertifikat_lisensi'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048';
        }

        // 3. Validasi Khusus Mitra Gear
        if ($role === 'mitra') {
            $rules['nama_usaha'] = 'required|string|max:255';
            $rules['alamat'] = 'required|string';
            $rules['kecamatan'] = 'required|string|max:100';
            $rules['metode_serah'] = 'required|in:ambil_di_tempat,antar_ke_hotel,keduanya';
            $rules['foto_ktp'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:2048';
            $rules['bukti_usaha'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:2048';
        }

        $validated = $request->validate($rules, [
            'email.unique' => 'Email ini sudah terdaftar di sistem kami.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'foto_ktp.required' => 'Foto KTP wajib diunggah untuk verifikasi identitas.',
            'bukti_usaha.required' => 'Bukti usaha (NIB / foto toko / surat izin) wajib diunggah.',
            'spesialisasi.required' => 'Pilih minimal satu spesialisasi wisata.',
        ]);

        // Buat User
        $statusAkun = ($role === 'user') ? 'active' : 'pending';

        $user = User::create([
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'no_hp' => $validated['no_hp'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
            'status' => $statusAkun,
            'email_verified_at' => now(),
        ]);

        // Simpan Profil Tour Guide
        if ($role === 'tour_guide') {
            $ktpPath = $request->file('foto_ktp')->store('documents/ktp', 'local');
            $sertifPath = $request->hasFile('sertifikat_lisensi')
                ? $request->file('sertifikat_lisensi')->store('documents/sertifikat', 'local')
                : null;

            $bahasaArr = array_map('trim', explode(',', $validated['bahasa']));

            TourGuide::create([
                'user_id' => $user->id,
                'bahasa' => $bahasaArr,
                'spesialisasi' => $validated['spesialisasi'],
                'area_layanan' => $validated['area_layanan'],
                'tarif_per_hari' => $validated['tarif_per_hari'],
                'pengalaman_tahun' => $validated['pengalaman_tahun'],
                'nomor_lisensi' => $validated['nomor_lisensi'] ?? null,
                'foto_ktp' => $ktpPath,
                'sertifikat_lisensi' => $sertifPath,
                'status_verifikasi' => 'pending',
                'rating_avg' => 5.00,
            ]);

            AuditLog::record($user->id, 'register_guide', 'TourGuide', $user->id, [
                'nama' => $user->nama,
                'email' => $user->email,
            ]);
        }

        // Simpan Profil Mitra Gear
        if ($role === 'mitra') {
            $ktpPath = $request->file('foto_ktp')->store('documents/ktp', 'local');
            $usahaPath = $request->file('bukti_usaha')->store('documents/usaha', 'local');

            Mitra::create([
                'user_id' => $user->id,
                'nama_usaha' => $validated['nama_usaha'],
                'alamat' => $validated['alamat'],
                'kecamatan' => $validated['kecamatan'],
                'metode_serah' => $validated['metode_serah'],
                'foto_ktp' => $ktpPath,
                'bukti_usaha' => $usahaPath,
                'status_verifikasi' => 'pending',
                'rating_avg' => 5.00,
            ]);

            AuditLog::record($user->id, 'register_mitra', 'Mitra', $user->id, [
                'nama_usaha' => $validated['nama_usaha'],
                'email' => $user->email,
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('user', $user->toArray());

        if ($role === 'user') {
            return redirect()->route('preferensi.create')->with('success', 'Selamat datang! Akun Anda berhasil dibuat.');
        }

        return redirect()->route('status.pengajuan')->with('success', 'Pendaftaran berhasil. Berkas Anda sedang menunggu peninjauan oleh admin.');
    }

    public function statusPengajuan(): View
    {
        $user = Auth::user();
        $profil = $user->isTourGuide() ? $user->tourGuide : $user->mitra;

        return view('auth.status-pengajuan', compact('user', 'profil'));
    }

    public function ajukanUlang(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user->status !== 'rejected') {
            return redirect()->route('status.pengajuan');
        }

        if ($user->isTourGuide()) {
            $request->validate([
                'foto_ktp' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'sertifikat_lisensi' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            $guide = $user->tourGuide;
            if ($request->hasFile('foto_ktp')) {
                $guide->foto_ktp = $request->file('foto_ktp')->store('documents/ktp', 'local');
            }
            if ($request->hasFile('sertifikat_lisensi')) {
                $guide->sertifikat_lisensi = $request->file('sertifikat_lisensi')->store('documents/sertifikat', 'local');
            }
            $guide->status_verifikasi = 'pending';
            $guide->catatan_verifikasi = null;
            $guide->save();
        } elseif ($user->isMitra()) {
            $request->validate([
                'foto_ktp' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'bukti_usaha' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            $mitra = $user->mitra;
            if ($request->hasFile('foto_ktp')) {
                $mitra->foto_ktp = $request->file('foto_ktp')->store('documents/ktp', 'local');
            }
            if ($request->hasFile('bukti_usaha')) {
                $mitra->bukti_usaha = $request->file('bukti_usaha')->store('documents/usaha', 'local');
            }
            $mitra->status_verifikasi = 'pending';
            $mitra->catatan_verifikasi = null;
            $mitra->save();
        }

        $user->status = 'pending';
        $user->save();

        AuditLog::record($user->id, 'resubmit_verification', get_class($user), $user->id, [
            'role' => $user->role,
        ]);

        return redirect()->route('status.pengajuan')->with('success', 'Dokumen perbaikan berhasil dikirim. Harap tunggu verifikasi admin.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
