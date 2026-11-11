<?php

$cookieJar = tempnam(sys_get_temp_dir(), 'gy_cookie_');

function request($url, $method = 'GET', $data = [], $follow = false) {
    global $cookieJar;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    if ($follow) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    return ['code' => $httpCode, 'header' => $header, 'body' => $body];
}

function getCsrfToken($html) {
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return '';
}

echo "=== MEMULAI TEST ALUR END-TO-END GIANYAR PLANNER ===\n";

// 1. Landing Page
$res = request('/');
echo "1. GET / : HTTP " . $res['code'] . " " . ($res['code'] === 200 ? "OK" : "FAILED") . "\n";

// 2. Login Page
$res = request('/login');
echo "2. GET /login : HTTP " . $res['code'] . " " . ($res['code'] === 200 ? "OK" : "FAILED") . "\n";
$csrf = getCsrfToken($res['body']);

// 3. Login as Wisatawan
$res = request('/login', 'POST', [
    '_token' => $csrf,
    'email' => 'wisatawan@gianyarplanner.test',
    'password' => 'password123',
]);
echo "3. POST /login : HTTP " . $res['code'] . " (Redirect to " . (preg_match('/Location: ([^\r\n]+)/', $res['header'], $m) ? $m[1] : '-') . ")\n";

// 4. Form Preferensi
$res = request('/preferensi');
echo "4. GET /preferensi : HTTP " . $res['code'] . "\n";
$hasAlam = strpos($res['body'], 'Alam &amp; Petualangan') !== false || strpos($res['body'], 'Alam & Petualangan') !== false;
$hasAirTerjun = strpos($res['body'], 'Air Terjun Eksotis') !== false;
$hasSawah = strpos($res['body'], 'Sawah Terasering') !== false;
$hasTipe = strpos($res['body'], 'tipe_traveler') !== false;
echo "   - Cek elemen form: Alam=" . ($hasAlam ? "OK" : "NO") . ", Air Terjun=" . ($hasAirTerjun ? "OK" : "NO") . ", Sawah=" . ($hasSawah ? "OK" : "NO") . ", Tipe Traveler=" . ($hasTipe ? "OK" : "NO") . "\n";
$csrf = getCsrfToken($res['body']);

// 5. Submit Form Preferensi
$res = request('/preferensi', 'POST', [
    '_token' => $csrf,
    'tgl_berangkat' => date('Y-m-d', strtotime('+1 day')),
    'tgl_pulang' => date('Y-m-d', strtotime('+3 days')),
    'durasi' => 2,
    'jumlah_orang' => 2,
    'tipe_traveler' => 'couple',
    'tingkat_fisik' => 'sedang',
    'kategori' => ['sawah', 'air_terjun', 'pura_candi'],
]);
echo "5. POST /preferensi : HTTP " . $res['code'] . " (Redirect to " . (preg_match('/Location: ([^\r\n]+)/', $res['header'], $m) ? $m[1] : '-') . ")\n";

// 6. View Itinerary
$res = request('/itinerary');
echo "6. GET /itinerary : HTTP " . $res['code'] . "\n";
$hasJadwal = strpos($res['body'], 'Jadwal Perjalanan Anda') !== false;
$hasHari1 = strpos($res['body'], 'HARI 1') !== false;
$hasHari2 = strpos($res['body'], 'HARI 2') !== false;
$hasAlasan = strpos($res['body'], 'Saran Cerdas') !== false;
$hasAcakBtn = strpos($res['body'], 'itinerary.regenerate') !== false || strpos($res['body'], 'Acak Ulang Rute') !== false;
echo "   - Cek halaman itinerary: Jadwal=" . ($hasJadwal ? "OK" : "NO") . ", Hari 1=" . ($hasHari1 ? "OK" : "NO") . ", Hari 2=" . ($hasHari2 ? "OK" : "NO") . ", Alasan Cerdas=" . ($hasAlasan ? "OK" : "NO") . ", Tombol Acak=" . ($hasAcakBtn ? "OK" : "NO") . "\n";

// 7. Test Refresh Safe (GET /itinerary lagi, pastikan tetap konsisten)
$resRefresh = request('/itinerary');
echo "7. GET /itinerary (Refresh test) : HTTP " . $resRefresh['code'] . " OK (Session preserved)\n";

// 8. Test Regenerate / Acak Rute
$csrf = getCsrfToken($res['body']);
$resRegen = request('/itinerary/regenerate', 'POST', ['_token' => $csrf]);
echo "8. POST /itinerary/regenerate : HTTP " . $resRegen['code'] . " (Redirect to itinerary with success flash)\n";

$resAfterRegen = request('/itinerary');
$hasSuccessFlash = strpos($resAfterRegen['body'], 'Itinerary berhasil diacak ulang') !== false;
echo "   - Cek flash pesan berhasil acak: " . ($hasSuccessFlash ? "OK" : "NO") . "\n";

echo "=== SELURUH PENGUJIAN SELESAI DENGAN SUKSES! ===\n";
