<?php

namespace Tests\Feature;

use App\Http\Controllers\DokumenController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAccountActive;
use App\Models\TourGuide;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RbacAndAuthorizationTest
{
    /**
     * Uji Role-Based Access Control (RBAC).
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testRoleAccessControl(): array
    {
        $middleware = new CheckRole;

        $admin = User::where('role', 'super_admin')->first();
        $user = User::where('role', 'user')->first();
        $guide = User::where('role', 'tour_guide')->first();
        $mitra = User::where('role', 'mitra')->first();

        $dummyNext = fn ($req) => response('OK');

        // Admin mengakses rute admin -> lolos
        Auth::login($admin);
        $resAdmin = $middleware->handle(Request::create('/admin/dashboard'), $dummyNext, 'super_admin');
        $adminPassed = ($resAdmin->getContent() === 'OK');

        // User biasa mencoba mengakses rute admin -> ditolak / 403
        Auth::login($user);
        $userBlocked = false;
        try {
            $middleware->handle(Request::create('/admin/dashboard'), $dummyNext, 'super_admin');
        } catch (HttpException $e) {
            $userBlocked = ($e->getStatusCode() === 403);
        }

        // Guide mencoba mengakses rute mitra -> ditolak / 403
        Auth::login($guide);
        $guideBlockedMitra = false;
        try {
            $middleware->handle(Request::create('/mitra/dashboard'), $dummyNext, 'mitra');
        } catch (HttpException $e) {
            $guideBlockedMitra = ($e->getStatusCode() === 403);
        }

        // Mitra mencoba mengakses rute guide -> ditolak / 403
        Auth::login($mitra);
        $mitraBlockedGuide = false;
        try {
            $middleware->handle(Request::create('/guide/dashboard'), $dummyNext, 'tour_guide');
        } catch (HttpException $e) {
            $mitraBlockedGuide = ($e->getStatusCode() === 403);
        }

        $isPassed = $adminPassed && $userBlocked && $guideBlockedMitra && $mitraBlockedGuide;
        $details = 'Admin: '.($adminPassed ? 'Lolos' : 'X').' | User to Admin: '.($userBlocked ? 'Dicegah 403' : 'Bocor').' | Guide to Mitra: '.($guideBlockedMitra ? 'Dicegah 403' : 'Bocor').' | Mitra to Guide: '.($mitraBlockedGuide ? 'Dicegah 403' : 'Bocor');

        return [
            'name' => 'Security: Role-Based Access Control (RBAC)',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }

    /**
     * Uji Lifecycle Status Akun (pending, rejected, suspended, active).
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testAccountLifecycleMiddleware(): array
    {
        $middleware = new EnsureAccountActive;
        $dummyNext = fn ($req) => response('OK');

        $makeRequest = function ($uri) {
            $req = Request::create($uri);
            if (app()->bound('session.store')) {
                $session = app('session.store');
                $session->start();
                $req->setLaravelSession($session);
            }

            return $req;
        };

        // 1. Akun Active -> Lolos
        $activeUser = User::where('status', 'active')->first();
        Auth::login($activeUser);
        $resActive = $middleware->handle($makeRequest('/dashboard'), $dummyNext);
        $activePassed = ($resActive->getContent() === 'OK');

        // 2. Akun Pending -> Diarahkan ke status-pengajuan
        $pendingUser = User::where('status', 'pending')->first();
        Auth::login($pendingUser);
        $resPending = $middleware->handle($makeRequest('/guide/dashboard'), $dummyNext);
        $pendingPassed = $resPending->isRedirection() && str_contains($resPending->headers->get('Location'), 'status-pengajuan');

        // 3. Akun Rejected -> Diarahkan ke status-pengajuan
        $rejectedUser = User::where('status', 'rejected')->first();
        Auth::login($rejectedUser);
        $resRejected = $middleware->handle($makeRequest('/guide/dashboard'), $dummyNext);
        $rejectedPassed = $resRejected->isRedirection() && str_contains($resRejected->headers->get('Location'), 'status-pengajuan');

        // 4. Akun Suspended -> Dikeluarkan & dialihkan ke login
        $suspendedUser = User::where('status', 'suspended')->first();
        Auth::login($suspendedUser);
        $resSuspended = $middleware->handle($makeRequest('/dashboard'), $dummyNext);
        $suspendedPassed = $resSuspended->isRedirection() && str_contains($resSuspended->headers->get('Location'), 'login');

        $isPassed = $activePassed && $pendingPassed && $rejectedPassed && $suspendedPassed;
        $details = 'Active: '.($activePassed ? 'Lolos' : 'X').' | Pending: '.($pendingPassed ? 'Dialihkan ke status-pengajuan' : 'X').' | Rejected: '.($rejectedPassed ? 'Dialihkan ke status-pengajuan' : 'X').' | Suspended: '.($suspendedPassed ? 'Dicekal & Logout' : 'X');

        return [
            'name' => 'Security: Account Status Middleware (EnsureAccountActive)',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }

    /**
     * Uji Keamanan Penyimpanan Dokumen Privat (KTP & Izin Usaha).
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testPrivateDocumentAccess(): array
    {
        $controller = new DokumenController;

        // Pastikan ada file dummy di disk local/documents/
        Storage::disk('local')->put('documents/ktp_test_sec.jpg', 'DUMMY_KTP_CONTENT_CONFIDENTIAL');

        $admin = User::where('role', 'super_admin')->first();
        $ownerGuide = TourGuide::with('user')->first();
        $otherUser = User::where('role', 'user')->first();

        // Tetapkan foto_ktp ke dokumen test
        $ownerGuide->update(['foto_ktp' => 'documents/ktp_test_sec.jpg']);

        $req = Request::create("/dokumen/guide/{$ownerGuide->id}/foto_ktp");

        // 1. User lain mencoba melihat -> 403 Forbidden
        Auth::login($otherUser);
        $otherBlocked = false;
        try {
            $controller->show($req, 'guide', $ownerGuide->id, 'foto_ktp');
        } catch (HttpException $e) {
            $otherBlocked = ($e->getStatusCode() === 403);
        }

        // 2. Pemilik sah (Guide bersangkutan) melihat -> 200 OK
        Auth::login($ownerGuide->user);
        $ownerAllowed = false;
        try {
            $resOwner = $controller->show($req, 'guide', $ownerGuide->id, 'foto_ktp');
            $ownerAllowed = ($resOwner->getStatusCode() === 200);
        } catch (\Throwable $e) {
            $ownerAllowed = false;
        }

        // 3. Super Admin melihat -> 200 OK
        Auth::login($admin);
        $adminAllowed = false;
        try {
            $resAdmin = $controller->show($req, 'guide', $ownerGuide->id, 'foto_ktp');
            $adminAllowed = ($resAdmin->getStatusCode() === 200);
        } catch (\Throwable $e) {
            $adminAllowed = false;
        }

        // Bersihkan file test
        Storage::disk('local')->delete('documents/ktp_test_sec.jpg');

        $isPassed = $otherBlocked && $ownerAllowed && $adminAllowed;
        $details = 'User Lain: '.($otherBlocked ? 'Dicekal 403' : 'Bocor!').' | Pemilik: '.($ownerAllowed ? 'Diizinkan (200)' : 'Ditolak').' | Admin: '.($adminAllowed ? 'Diizinkan (200)' : 'Ditolak');

        return [
            'name' => 'Security: Private Document Protection (DokumenController)',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }
}
