<?php

namespace App\Http\Controllers;

use App\Models\Destinasi;
use App\Models\Itinerary;
use App\Models\ItineraryExclusion;
use App\Services\ItineraryEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItineraryEditController extends Controller
{
    public function __construct(
        protected ItineraryEditService $editService
    ) {}

    /**
     * Pastikan pengguna memiliki hak akses terhadap itinerary.
     */
    protected function getAuthorizedItinerary(int $id): Itinerary
    {
        $itinerary = Itinerary::with(['items.destinasi', 'exclusions.destinasi'])->findOrFail($id);

        $currentUser = Auth::user();

        // Jika itinerary memiliki pemilik user_id
        if ($itinerary->user_id !== null) {
            if (! $currentUser) {
                // Cek apakah ID itinerary tersimpan di session wisatawan aktif
                if (session('itinerary_id') !== $itinerary->id) {
                    abort(401, 'Silakan masuk untuk mengedit itinerary ini.');
                }
            } elseif (! $currentUser->isSuperAdmin() && $currentUser->id !== $itinerary->user_id) {
                abort(403, 'Akses ditolak: Anda bukan pemilik itinerary ini.');
            }
        } else {
            // Itinerary tamu (guest)
            if (session('itinerary_id') !== $itinerary->id && (! $currentUser || ! $currentUser->isSuperAdmin())) {
                abort(403, 'Akses ditolak: Anda tidak memiliki akses ke sesi itinerary ini.');
            }
        }

        return $itinerary;
    }

    /**
     * Dapatkan 3-5 kandidat pengganti cerdas (Smart Swap) untuk suatu slot kunjungan.
     */
    public function replaceSuggestions(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $alasan = $request->input('alasan');
        $limit = (int) $request->input('limit', 4);

        $candidates = $this->editService->suggestReplacements($itinerary, $itemId, $alasan, $limit);

        return response()->json([
            'success' => true,
            'item_id' => $itemId,
            'alasan' => $alasan,
            'candidates' => $candidates,
        ]);
    }

    /**
     * Eksekusi penggantian destinasi (Smart Swap).
     */
    public function replace(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        $validated = $request->validate([
            'destinasi_id' => 'required|exists:destinasis,id',
            'alasan' => 'nullable|string|max:50',
            'versi' => 'nullable|integer',
        ]);

        $result = $this->editService->replaceItem(
            $itinerary,
            $itemId,
            (int) $validated['destinasi_id'],
            $validated['alasan'] ?? null,
            isset($validated['versi']) ? (int) $validated['versi'] : null
        );

        return response()->json($result);
    }

    /**
     * Hapus destinasi dari itinerary.
     */
    public function destroy(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        $alasan = $request->input('alasan');
        $clientVersion = $request->input('versi') !== null ? (int) $request->input('versi') : null;

        $result = $this->editService->removeItem($itinerary, $itemId, $alasan, $clientVersion);

        return response()->json($result);
    }

    /**
     * Tambah destinasi baru ke hari tertentu dengan algoritma Cheapest Insertion.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        $validated = $request->validate([
            'destinasi_id' => 'required|exists:destinasis,id',
            'hari_ke' => 'required|integer|min:1',
            'versi' => 'nullable|integer',
        ]);

        $result = $this->editService->addItem(
            $itinerary,
            (int) $validated['destinasi_id'],
            (int) $validated['hari_ke'],
            isset($validated['versi']) ? (int) $validated['versi'] : null
        );

        return response()->json($result);
    }

    /**
     * Urutkan ulang (reorder) atau pindah hari rute perjalanan.
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        $validated = $request->validate([
            'items_order' => 'required|array',
            'items_order.*.id' => 'required|integer',
            'items_order.*.hari_ke' => 'required|integer|min:1',
            'items_order.*.urutan' => 'required|integer|min:1',
            'versi' => 'nullable|integer',
        ]);

        $result = $this->editService->reorderItems(
            $itinerary,
            $validated['items_order'],
            isset($validated['versi']) ? (int) $validated['versi'] : null
        );

        return response()->json($result);
    }

    /**
     * Kunci / lepas kunci destinasi (Pin toggle).
     */
    public function lock(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $clientVersion = $request->input('versi') !== null ? (int) $request->input('versi') : null;

        $result = $this->editService->toggleLockItem($itinerary, $itemId, $clientVersion);

        return response()->json($result);
    }

    /**
     * Ubah durasi kunjungan khusus per destinasi.
     */
    public function updateDuration(Request $request, int $id, int $itemId): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        $validated = $request->validate([
            'durasi_menit' => 'required|integer|min:30|max:360',
            'versi' => 'nullable|integer',
        ]);

        $result = $this->editService->updateDuration(
            $itinerary,
            $itemId,
            (int) $validated['durasi_menit'],
            isset($validated['versi']) ? (int) $validated['versi'] : null
        );

        return response()->json($result);
    }

    /**
     * Regenerate sebagian: (a) Hari ini saja, atau (b) Semua kecuali yang dikunci.
     */
    public function regenerate(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        $validated = $request->validate([
            'scope' => 'required|in:hari,semua_kecuali_terkunci',
            'hari_ke' => 'nullable|integer|min:1',
            'versi' => 'nullable|integer',
        ]);

        $result = $this->editService->regeneratePartial(
            $itinerary,
            $validated['scope'],
            isset($validated['hari_ke']) ? (int) $validated['hari_ke'] : null,
            isset($validated['versi']) ? (int) $validated['versi'] : null
        );

        return response()->json($result);
    }

    /**
     * Batalkan aksi terakhir (Undo).
     */
    public function undo(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $clientVersion = $request->input('versi') !== null ? (int) $request->input('versi') : null;

        $result = $this->editService->undo($itinerary, $clientVersion);

        return response()->json($result);
    }

    /**
     * Ulangi aksi yang telah di-undo (Redo).
     */
    public function redo(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $clientVersion = $request->input('versi') !== null ? (int) $request->input('versi') : null;

        $result = $this->editService->redo($itinerary, $clientVersion);

        return response()->json($result);
    }

    /**
     * Kembalikan ke jadwal versi awal (Reset).
     */
    public function reset(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $clientVersion = $request->input('versi') !== null ? (int) $request->input('versi') : null;

        $result = $this->editService->resetToInitial($itinerary, $clientVersion);

        return response()->json($result);
    }

    /**
     * Lihat daftar destinasi yang di-blacklist (disembunyikan) pada itinerary ini.
     */
    public function getExclusions(int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $exclusions = $itinerary->exclusions()->with('destinasi')->get();

        return response()->json([
            'success' => true,
            'exclusions' => $exclusions,
        ]);
    }

    /**
     * Hapus destinasi dari daftar blacklist (kembalikan agar dapat muncul lagi).
     */
    public function removeExclusion(int $id, int $destinasiId): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);

        ItineraryExclusion::where('itinerary_id', $itinerary->id)
            ->where('destinasi_id', $destinasiId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Destinasi telah dikeluarkan dari daftar yang disembunyikan.',
        ]);
    }

    /**
     * Duplikat itinerary terkunci menjadi draft baru yang dapat diedit.
     */
    public function duplicate(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $itinerary = Itinerary::findOrFail($id);

        if (! Auth::check()) {
            return redirect()->route('login')->with('info', 'Silakan login terlebih dahulu untuk menduplikat itinerary.');
        }

        $newItin = $this->editService->duplicateItinerary($itinerary, Auth::user());
        session(['itinerary_id' => $newItin->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Itinerary berhasil diduplikat sebagai draft baru.',
                'new_itinerary_id' => $newItin->id,
            ]);
        }

        return redirect()->route('itinerary.show')->with('success', 'Itinerary berhasil diduplikat sebagai draft baru dan siap diedit.');
    }

    /**
     * Cari destinasi di katalog Gianyar untuk modal Tambah Destinasi.
     */
    public function searchCatalog(Request $request, int $id): JsonResponse
    {
        $itinerary = $this->getAuthorizedItinerary($id);
        $query = $request->input('q', '');
        $kategori = $request->input('kategori');

        // Kecualikan destinasi yang sudah ada di itinerary
        $currentDestIds = $itinerary->items()->pluck('destinasi_id')->all();

        $destQuery = Destinasi::where('is_active', true)->whereNotIn('id', $currentDestIds);

        if (! empty($query)) {
            $destQuery->where(function ($q) use ($query) {
                $q->where('nama', 'like', "%{$query}%")
                    ->orWhere('deskripsi', 'like', "%{$query}%")
                    ->orWhere('kecamatan', 'like', "%{$query}%");
            });
        }

        if (! empty($kategori)) {
            $destQuery->where(function ($q) use ($kategori) {
                $q->where('kategori_utama', $kategori)
                    ->orWhereJsonContains('subkategori', $kategori);
            });
        }

        $destinations = $destQuery->take(20)->get();

        return response()->json([
            'success' => true,
            'destinations' => $destinations,
        ]);
    }
}
