<?php

namespace App\Http\Controllers;

use App\Support\JsonStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MitraController extends Controller
{
    public function dashboard(): View
    {
        $user = session('user');

        return view('mitra.dashboard', compact('user'));
    }

    public function adminIndex(): View
    {
        // TODO: ganti dengan Eloquent
        $users = JsonStore::read('users.json');
        $mitraList = collect($users)->whereIn('role', ['tour_guide', 'penyedia_gear'])->values();

        return view('admin.verifikasi-mitra', compact('mitraList'));
    }

    public function adminVerify(Request $request, string $email): RedirectResponse
    {
        $action = $request->input('action'); // 'setujui' or 'tolak'
        $notes = $request->input('notes', '');

        // TODO: ganti dengan Eloquent
        $users = JsonStore::read('users.json');

        foreach ($users as &$u) {
            if ($u['email'] === $email) {
                if ($action === 'setujui') {
                    $u['status'] = 'aktif';
                    $u['notes'] = '';
                } else {
                    $u['status'] = 'ditolak';
                    $u['notes'] = $notes;
                }
                break;
            }
        }
        unset($u);

        JsonStore::write('users.json', $users);

        $label = $action === 'setujui' ? 'disetujui' : 'ditolak';

        return redirect()->route('admin.mitra')->with('success', "Mitra {$email} berhasil {$label}.");
    }
}
