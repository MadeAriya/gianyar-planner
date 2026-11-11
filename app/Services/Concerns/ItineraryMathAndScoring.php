<?php

namespace App\Services\Concerns;

use App\Support\DummyData;

trait ItineraryMathAndScoring
{
    /**
     * Hitung jarak Haversine antara dua koordinat geografis (dalam km).
     */
    public function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Normalisasi kategori input (bisa berupa subkategori spesifik atau kategori umum).
     *
     * @param  string[]  $input
     * @return string[]
     */
    public function normalisasiSubkategori(array $input): array
    {
        $result = [];
        $katList = DummyData::kategoriList();

        foreach ($input as $item) {
            if (isset($katList[$item])) {
                foreach (array_keys($katList[$item]['subkategori']) as $subKey) {
                    $result[] = $subKey;
                }
            } else {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Memeriksa apakah destinasi cocok dengan subkategori pilihan.
     *
     * @param  array<string, mixed>  $dest
     * @param  string[]  $selectedSubkats
     */
    public function isCocokKategori(array $dest, array $selectedSubkats): bool
    {
        if (empty($selectedSubkats)) {
            return true;
        }

        $destSubkats = (array) ($dest['subkategori'] ?? [$dest['kategori'] ?? '']);
        $katUtama = $dest['kategori_utama'] ?? '';

        return count(array_intersect($destSubkats, $selectedSubkats)) > 0
            || in_array($katUtama, $selectedSubkats);
    }

    /**
     * Hitung skor dasar destinasi berdasarkan kecocokan subkategori, profil traveler, dan tingkat fisik.
     *
     * @param  array<string, mixed>  $dest
     * @param  string[]  $selectedSubkats
     */
    public function hitungSkorDasar(array $dest, array $selectedSubkats, string $tipeTraveler, string $tingkatFisik): float
    {
        $skor = 10.0; // Base score

        // 1. Kesesuaian Kategori (Bobot ~35-40)
        if ($this->isCocokKategori($dest, $selectedSubkats)) {
            $skor += (float) config('gianyar.itinerary.bobot.kategori', 35);
        }

        // 2. Kesesuaian Tipe Traveler (solo, couple, family, group)
        $cocokTraveler = (array) ($dest['cocok_untuk'] ?? []);
        if (in_array($tipeTraveler, $cocokTraveler)) {
            $skor += (float) config('gianyar.itinerary.bobot.cocok_traveler', 10);
        }

        // 3. Kesesuaian Tingkat Fisik
        $destFisik = $dest['tingkat_fisik'] ?? 'sedang';
        $bobotFisik = (float) config('gianyar.itinerary.bobot.fisik', 10);
        if ($tingkatFisik === 'santai') {
            if ($destFisik === 'mudah') {
                $skor += $bobotFisik;
            } elseif ($destFisik === 'sulit') {
                $skor -= 8;
            }
        } elseif ($tingkatFisik === 'aktif') {
            if ($destFisik === 'sedang' || $destFisik === 'sulit') {
                $skor += $bobotFisik;
            }
        } else {
            // sedang
            if ($destFisik === 'sedang' || $destFisik === 'mudah') {
                $skor += ($bobotFisik * 0.8);
            }
        }

        // 4. Popularitas (1-10) -> skala hingga 10 poin
        $pop = (float) ($dest['popularitas'] ?? 5);
        $skor += ($pop / 10.0) * (float) config('gianyar.itinerary.bobot.popularitas', 10);

        return $skor;
    }

    /**
     * Weighted Random Selection: Memilih satu kandidat secara proporsional terhadap skornya.
     *
     * @param  array<int, array{destinasi: array, skor: float, is_primary?: bool}>  $candidates
     * @return array{destinasi: array, skor: float, is_primary?: bool}
     */
    public function pilihWeightedRandom(array $candidates): array
    {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $totalWeight = array_sum(array_column($candidates, 'skor'));
        if ($totalWeight <= 0) {
            return $candidates[array_rand($candidates)];
        }

        $rand = (mt_rand(1, 10000) / 10000.0) * $totalWeight;
        $cumulative = 0.0;

        foreach ($candidates as $cand) {
            $cumulative += $cand['skor'];
            if ($rand <= $cumulative) {
                return $cand;
            }
        }

        return $candidates[count($candidates) - 1];
    }

    /**
     * Merangkai kalimat alasan rekomendasi yang natural untuk UI itinerary.
     *
     * @param  array<string, mixed>  $dest
     */
    public function buatAlasanRekomendasi(
        array $dest,
        string $slot,
        ?float $jarakKm,
        string $tipeTraveler,
        bool $isPrimary = true
    ): string {
        $nama = $dest['nama_destinasi'] ?? $dest['nama'];

        if ($slot === 'pagi') {
            if (($dest['kategori_utama'] ?? '') === 'alam' || in_array('sawah', (array) ($dest['subkategori'] ?? []))) {
                return "Suasana pagi hari yang sejuk dan cerah memberikan pencahayaan alami terbaik di {$nama}.";
            }
            if (in_array('melukat', (array) ($dest['subkategori'] ?? []))) {
                return 'Pagi hari adalah waktu paling hening dan sakral untuk prosesi pembersihan diri (melukat).';
            }

            return 'Waktu pagi ideal untuk memulai hari sebelum lokasi dipadati pengunjung.';
        }

        if ($slot === 'siang') {
            if ($jarakKm !== null && $jarakKm <= 8.0) {
                return "Hanya {$jarakKm} km dari destinasi sebelumnya, efisien rute dan cocok untuk rehat siang.";
            }
            if (($dest['kategori_utama'] ?? '') === 'kuliner') {
                return 'Pilihan santap siang istimewa dengan cita rasa autentik khas Gianyar.';
            }

            return 'Lokasi nyaman untuk menikmati siang hari dengan keteduhan pepohonan tropis.';
        }

        // Sore
        if (in_array('sunset', (array) ($dest['tags'] ?? [])) || in_array('pantai', (array) ($dest['subkategori'] ?? []))) {
            return 'Spot sempurna menyambut senja dan pemandangan matahari terbenam yang memukau.';
        }

        if ($jarakKm !== null && $jarakKm <= 10.0) {
            return "Rute searah perjalanan sore (berjarak {$jarakKm} km), menutup petualangan hari ini dengan santai.";
        }

        return 'Penutup perjalanan hari ini yang berkesan untuk rileks bersama.';
    }
}
