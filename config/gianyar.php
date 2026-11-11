<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bundle Discount
    |--------------------------------------------------------------------------
    | Potongan harga (desimal) ketika wisatawan memilih paket bundle
    | (Guide + Gear). Nilai 0.10 = diskon 10%.
    | TODO: ganti dengan pengaturan dari database / admin panel.
    */
    'bundle_discount' => 0.10,

    /*
    |--------------------------------------------------------------------------
    | Itinerary Generator Configuration
    |--------------------------------------------------------------------------
    | Parameter-parameter yang dapat di-tune untuk mengatur perilaku
    | algoritma pembangkitan itinerary otomatis. Semua nilai dibaca
    | oleh App\Services\ItineraryGenerator melalui config() helper.
    */
    'itinerary' => [
        // Jumlah slot kegiatan maksimum per hari (pagi, siang, sore)
        'slots_per_hari' => 3,

        // Jam mulai dan akhir kegiatan harian (format H:i)
        'jam_mulai' => '08:00',
        'jam_akhir' => '18:00',

        // Durasi istirahat makan siang dalam menit
        'waktu_makan_menit' => 60,

        // Estimasi kecepatan rata-rata kendaraan (km/h) untuk kalkulasi waktu tempuh
        'kecepatan_rata_rata_kmh' => 30,

        /*
        |----------------------------------------------------------------------
        | Bobot Skor (Scoring Weights)
        |----------------------------------------------------------------------
        | Setiap faktor diberi bobot yang menentukan pengaruhnya terhadap
        | skor akhir destinasi. Total tidak harus 100 — hanya rasio
        | antar-bobot yang penting.
        */
        'bobot' => [
            'kategori' => 35,       // Kesesuaian kategori/subkategori pilihan user
            'waktu' => 20,          // Kesesuaian waktu terbaik destinasi dgn slot hari
            'zona' => 15,           // Kedekatan zona geografis (minimasi travel time)
            'popularitas' => 10,    // Tingkat popularitas destinasi (rating/review)
            'cocok_traveler' => 10, // Kesesuaian tipe traveler (solo, keluarga, dll)
            'fisik' => 10,          // Kesesuaian tingkat aktivitas fisik
        ],

        // Jumlah kandidat teratas yang diambil untuk weighted random selection
        'top_k' => 5,

        // Rentang noise acak (0..N) ditambahkan ke skor untuk variasi
        'jitter_range' => 8,

        // Penalti skor jika subkategori yang sama sudah dipilih di hari yang sama
        'diversity_penalty' => 15,

        // Maksimum destinasi dengan subkategori sama dalam satu hari
        'max_same_subkat_per_hari' => 2,

        // Potongan harga bundle (duplikat root-level untuk backward compat)
        'bundle_discount' => 0.10,
    ],
];
