<?php

namespace Tests\Feature;

use App\Models\GearReservation;
use App\Models\TravelGear;
use App\Services\GearRecommendationService;
use Carbon\Carbon;

class GearRecommendationTest
{
    /**
     * Uji 3 skenario itinerary berbeda untuk keakuratan prioritas rekomendasi gear.
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testItinerarySpecificRecommendations(): array
    {
        $service = new GearRecommendationService;

        // 1. Itinerary Air Terjun & Rafting (Kanto Lampo & Ayung Rafting)
        $itinWater = [
            'durasi' => 1,
            'jumlah_orang' => 2,
            'jadwal' => [
                [
                    'hari_ke' => 1,
                    'tanggal' => Carbon::today()->addDays(2)->toDateString(),
                    'items' => [
                        ['subkategori' => ['air_terjun'], 'tags' => ['air_terjun', 'outdoor']],
                        ['subkategori' => ['sungai_rafting'], 'tags' => ['sungai_rafting', 'outdoor']],
                    ],
                ],
            ],
        ];
        $recWater = $service->getRecommendations($itinWater);
        $wajibCatsWater = collect($recWater['wajib'])->pluck('category.kode')->all();
        $waterPassed = in_array('alas_kaki', $wajibCatsWater) && in_array('perlengkapan_air', $wajibCatsWater);

        // 2. Itinerary Pura & Melukat (Tirta Empul & Pura Gunung Kawi)
        $itinSpiritual = [
            'durasi' => 1,
            'jumlah_orang' => 2,
            'jadwal' => [
                [
                    'hari_ke' => 1,
                    'tanggal' => Carbon::today()->addDays(3)->toDateString(),
                    'items' => [
                        ['subkategori' => ['pura_candi'], 'tags' => ['pura_candi', 'religi']],
                        ['subkategori' => ['melukat'], 'tags' => ['melukat', 'spiritual']],
                    ],
                ],
            ],
        ];
        $recSpiritual = $service->getRecommendations($itinSpiritual);
        $wajibCatsSpiritual = collect($recSpiritual['wajib'])->pluck('category.kode')->all();
        $spiritualPassed = in_array('pakaian_adat', $wajibCatsSpiritual);

        // 3. Itinerary Sunrise Bukit Campuhan (Ridge walk waktu subuh)
        $itinSunrise = [
            'durasi' => 1,
            'jumlah_orang' => 2,
            'jadwal' => [
                [
                    'hari_ke' => 1,
                    'tanggal' => Carbon::today()->addDays(4)->toDateString(),
                    'items' => [
                        ['subkategori' => ['bukit_trekking'], 'tags' => ['sunrise', 'bukit_trekking']],
                    ],
                ],
            ],
        ];
        $recSunrise = $service->getRecommendations($itinSunrise);
        $disarankanCatsSunrise = collect($recSunrise['disarankan'])->pluck('category.kode')->all();
        $sunrisePassed = in_array('trekking', $disarankanCatsSunrise) && in_array('pakaian_cuaca', $disarankanCatsSunrise);

        $isPassed = $waterPassed && $spiritualPassed && $sunrisePassed;
        $details = 'Air Terjun (Wajib: alas_kaki & dry_bag): '.($waterPassed ? 'OK' : 'X')
            .' | Pura (Wajib: pakaian_adat): '.($spiritualPassed ? 'OK' : 'X')
            .' | Sunrise (Disarankan: headlamp & windbreaker): '.($sunrisePassed ? 'OK' : 'X');

        return [
            'name' => 'Rule Engine: Rekomendasi Travel Gear Kontekstual',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }

    /**
     * Uji Ketahanan Stok: Jika stok suatu mitra habis, sistem otomatis beralih ke mitra alternatif.
     *
     * @return array{name: string, passed: bool, details: string}
     */
    public function testStockExhaustionAlternativeMitigation(): array
    {
        $service = new GearRecommendationService;
        $targetDate = Carbon::today()->addDays(25)->toDateString();

        // Cari gear alas_kaki dari mitra pertama dan habiskan stoknya untuk targetDate lewat reservasi
        $gearToExhaust = TravelGear::whereHas('category', function ($q) {
            $q->where('kode', 'alas_kaki');
        })->first();

        // Reservasikan seluruh stok gear pertama
        $tempReservation = GearReservation::create([
            'gear_id' => $gearToExhaust->id,
            'tanggal_mulai' => $targetDate,
            'tanggal_selesai' => $targetDate,
            'qty' => $gearToExhaust->stok_total,
            'status' => 'ditahan',
        ]);

        // Coba minta rekomendasi untuk itinerary air terjun pada tanggal tersebut
        $itin = [
            'durasi' => 1,
            'jumlah_orang' => 2,
            'jadwal' => [
                [
                    'hari_ke' => 1,
                    'tanggal' => $targetDate,
                    'items' => [
                        ['subkategori' => ['air_terjun'], 'tags' => ['air_terjun']],
                    ],
                ],
            ],
        ];

        $recs = $service->getRecommendations($itin);
        $chosenGearAlasKaki = collect($recs['wajib'])->firstWhere('category.kode', 'alas_kaki');

        // Pastikan gear yang terpilih BUKAN gear yang stoknya habis, melainkan alternatif mitra lain yang stoknya ada
        $isDifferentGear = ($chosenGearAlasKaki !== null) && ($chosenGearAlasKaki['gear']->id !== $gearToExhaust->id);
        $hasSufficientStock = ($chosenGearAlasKaki !== null) && ($chosenGearAlasKaki['gear']->stokTersedia($targetDate, $targetDate) >= 2);

        // Hapus reservasi sementara
        $tempReservation->delete();

        $isPassed = $isDifferentGear && $hasSufficientStock;
        $details = "Gear habis (ID {$gearToExhaust->id}) -> Otomatis beralih ke Gear alternatif ID ".($chosenGearAlasKaki['gear']->id ?? 'none').' milik '.($chosenGearAlasKaki['gear']->mitra->nama_usaha ?? 'none').' (Sisa stok: '.($chosenGearAlasKaki['gear']->stokTersedia($targetDate, $targetDate) ?? 0).')';

        return [
            'name' => 'Resilience: Mitigasi Stok Habis & Auto-Switch Mitra',
            'passed' => $isPassed,
            'details' => $details,
        ];
    }
}
