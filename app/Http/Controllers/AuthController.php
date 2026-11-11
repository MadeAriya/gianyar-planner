<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\MitraProfileRequest;
use App\Http\Requests\RegisterRequest;
use App\Support\JsonStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // TODO: ganti dengan Eloquent
        $users = JsonStore::read('users.json');
        $user = collect($users)->firstWhere('email', $validated['email']);

        if (! $user || ! password_verify($validated['password'], $user['password'])) {
            return back()->withInput()->with('error', 'Email atau password salah.');
        }

        if ($user['status'] !== 'aktif' && $user['role'] === 'wisatawan') {
            return back()->with('error', 'Akun belum aktif.');
        }

        $request->session()->put('user', $user);

        $intended = $request->session()->pull('url.intended');
        if ($intended) {
            return redirect($intended);
        }

        return match ($user['role']) {
            'admin' => redirect()->route('admin.mitra'),
            'wisatawan' => redirect()->route('preferensi.create'),
            default => redirect()->route('mitra.dashboard'),
        };
    }

    public function showRegister(Request $request): View
    {
        $preselectedRole = $request->query('role');

        return view('auth.register', compact('preselectedRole'));
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // TODO: ganti dengan Eloquent
        $users = JsonStore::read('users.json');

        if (collect($users)->firstWhere('email', $validated['email'])) {
            return back()->withInput()->with('error', 'Email sudah terdaftar.');
        }

        $user = [
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'password' => password_hash($validated['password'], PASSWORD_BCRYPT),
            'no_hp' => $validated['no_hp'],
            'role' => $validated['role'],
            'status' => $validated['role'] === 'wisatawan' ? 'aktif' : 'belum_lengkap',
        ];

        JsonStore::append('users.json', $user);
        $request->session()->put('user', $user);

        return match ($validated['role']) {
            'wisatawan' => redirect()->route('preferensi.create'),
            default => redirect()->route('mitra.profil'),
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->flush();

        return redirect()->route('home');
    }

    public function showMitraProfile(): View
    {
        $user = session('user');

        return view('mitra.lengkapi-profil', compact('user'));
    }

    public function saveMitraProfile(MitraProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // TODO: ganti dengan Eloquent
        $users = JsonStore::read('users.json');
        $email = session('user.email');

        foreach ($users as &$u) {
            if ($u['email'] === $email) {
                $u['nama_usaha'] = $validated['nama_usaha'];
                $u['alamat'] = $validated['alamat'];
                $u['status'] = 'menunggu_verifikasi';

                if ($request->hasFile('foto_ktp')) {
                    $u['foto_ktp'] = $request->file('foto_ktp')->store('mitra', 'public');
                }
                if ($request->hasFile('bukti_usaha')) {
                    $u['bukti_usaha'] = $request->file('bukti_usaha')->store('mitra', 'public');
                }

                if (session('user.role') === 'tour_guide') {
                    $u['bahasa'] = $validated['bahasa'];
                    $u['tarif'] = $validated['tarif'];
                    if ($request->hasFile('foto')) {
                        $u['foto'] = $request->file('foto')->store('mitra', 'public');
                    }
                }

                session(['user' => $u]);
                break;
            }
        }
        unset($u);

        JsonStore::write('users.json', $users);

        return redirect()->route('mitra.profil')->with('success', 'Profil berhasil diajukan untuk verifikasi.');
    }
}
