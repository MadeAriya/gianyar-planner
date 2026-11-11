@extends('layouts.guest')
@section('title', 'Daftar Akun — GianyarPlanner')

@section('content')
<div class="flex flex-col gap-8 max-w-xl mx-auto">
    <div>
        <h1 class="font-playfair text-3xl sm:text-4xl text-text">Daftar Akun Baru</h1>
        <p class="text-muted mt-2 text-sm leading-relaxed">Bergabunglah dan mulai rencanakan liburan impian atau tawarkan jasa Anda di Gianyar.</p>
    </div>

    @if($errors->any())
        <x-alert type="error">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('register.process') }}" enctype="multipart/form-data" class="flex flex-col gap-6" id="register-form">
        @csrf

        {{-- Pilih Peran --}}
        <div class="flex flex-col gap-2">
            <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Pilih Peran Akun</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @php
                    $currentRole = old('role', $preselectedRole ?? 'user');
                @endphp
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="user" class="peer hidden" {{ $currentRole === 'user' ? 'checked' : '' }} data-role-radio>
                    <div class="border border-line rounded-md p-4 text-center transition-colors peer-checked:border-forest peer-checked:bg-forest/5 hover:border-muted">
                        <p class="font-inter font-semibold text-sm text-text">Wisatawan</p>
                        <p class="text-xs text-muted mt-1">Rencanakan trip & booking</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="tour_guide" class="peer hidden" {{ $currentRole === 'tour_guide' ? 'checked' : '' }} data-role-radio>
                    <div class="border border-line rounded-md p-4 text-center transition-colors peer-checked:border-forest peer-checked:bg-forest/5 hover:border-muted">
                        <p class="font-inter font-semibold text-sm text-text">Tour Guide</p>
                        <p class="text-xs text-muted mt-1">Pemandu lokal berlisensi</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="role" value="mitra" class="peer hidden" {{ $currentRole === 'mitra' ? 'checked' : '' }} data-role-radio>
                    <div class="border border-line rounded-md p-4 text-center transition-colors peer-checked:border-forest peer-checked:bg-forest/5 hover:border-muted">
                        <p class="font-inter font-semibold text-sm text-text">Mitra Gear</p>
                        <p class="text-xs text-muted mt-1">Penyedia perlengkapan</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Data Dasar --}}
        <div class="flex flex-col gap-4 border-t border-line/70 pt-4">
            <x-input label="Nama Lengkap" name="nama" placeholder="Nama lengkap sesuai KTP" :value="old('nama')" :required="true" />
            <x-input label="Email" name="email" type="email" placeholder="email@contoh.com" :value="old('email')" :required="true" />
            <x-input label="Nomor WhatsApp / HP" name="no_hp" placeholder="08xxxxxxxxxx" :value="old('no_hp')" :required="true" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Password" name="password" type="password" placeholder="Minimal 8 karakter" :required="true" />
                <x-input label="Konfirmasi Password" name="password_confirmation" type="password" placeholder="Ulangi password" :required="true" />
            </div>
        </div>

        {{-- Form Tambahan Khusus Tour Guide --}}
        <div id="section-guide" class="{{ $currentRole === 'tour_guide' ? '' : 'hidden' }} flex flex-col gap-4 border-t border-line/70 pt-4 bg-sand/30 p-4 rounded-md">
            <h3 class="font-playfair text-lg text-text font-bold">Informasi Profesional Tour Guide</h3>
            <p class="text-xs text-muted mb-2">Data ini akan diverifikasi oleh Super Admin sebelum profil Anda ditampilkan kepada wisatawan.</p>

            <x-input label="Bahasa yang Dikuasai (pisahkan koma)" name="bahasa" placeholder="Indonesia, Inggris, Jepang" :value="old('bahasa', 'Indonesia, Inggris')" />
            <x-input label="Area Layanan Operasional (Kecamatan)" name="area_layanan" placeholder="Ubud, Tegalalang, Tampaksiring" :value="old('area_layanan', 'Ubud dan Sekitarnya')" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Tarif Jasa per Hari (IDR)" name="tarif_per_hari" type="number" placeholder="400000" :value="old('tarif_per_hari', 350000)" />
                <x-input label="Pengalaman (Tahun)" name="pengalaman_tahun" type="number" placeholder="3" :value="old('pengalaman_tahun', 2)" />
            </div>

            <x-input label="Nomor Lisensi / Sertifikasi (Opsional)" name="nomor_lisensi" placeholder="HPI-BALI-XXXXX" :value="old('nomor_lisensi')" />

            {{-- Spesialisasi Subkategori --}}
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Spesialisasi Wisata (Pilih minimal 1)</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-48 overflow-y-auto p-2 bg-white rounded border border-line">
                    @php
                        $oldSpesialisasi = (array) old('spesialisasi', ['pura_candi', 'sawah']);
                    @endphp
                    @foreach(\App\Support\DummyData::kategoriList() as $kat)
                        @foreach($kat['subkategori'] as $subKey => $sub)
                            <label class="inline-flex items-center gap-1.5 text-xs text-text cursor-pointer">
                                <input type="checkbox" name="spesialisasi[]" value="{{ $subKey }}" class="rounded text-forest focus:ring-forest"
                                    {{ in_array($subKey, $oldSpesialisasi) ? 'checked' : '' }}>
                                <span class="truncate">{{ $sub['label'] }}</span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- Upload Dokumen Guide --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                <div>
                    <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Foto KTP <span class="text-red-500">*</span></label>
                    <input type="file" name="foto_ktp" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full text-xs text-text border border-line rounded p-2 bg-white" />
                    <p class="text-[10px] text-muted mt-1">Disimpan di storage privat terproteksi.</p>
                </div>
                <div>
                    <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Sertifikat / Kartu Lisensi Pemandu</label>
                    <input type="file" name="sertifikat_lisensi" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full text-xs text-text border border-line rounded p-2 bg-white" />
                    <p class="text-[10px] text-muted mt-1">Format: JPG, PNG, atau PDF (maks 2MB).</p>
                </div>
            </div>
        </div>

        {{-- Form Tambahan Khusus Mitra Gear --}}
        <div id="section-mitra" class="{{ $currentRole === 'mitra' ? '' : 'hidden' }} flex flex-col gap-4 border-t border-line/70 pt-4 bg-sand/30 p-4 rounded-md">
            <h3 class="font-playfair text-lg text-text font-bold">Informasi Usaha Penyedia Gear</h3>
            <p class="text-xs text-muted mb-2">Data ini akan diverifikasi oleh Super Admin sebelum Anda dapat menambahkan katalog barang.</p>

            <x-input label="Nama Usaha / Rental" name="nama_usaha" placeholder="Contoh: Ubud Outdoor Adventure Gear" :value="old('nama_usaha')" />
            <x-input label="Alamat Usaha Lengkap" name="alamat" placeholder="Jl. Raya Hanoman No. 12, Ubud" :value="old('alamat')" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="Kecamatan" name="kecamatan" placeholder="Ubud / Sukawati / Tegalalang" :value="old('kecamatan', 'Ubud')" />
                <div class="flex flex-col gap-2">
                    <label class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">Metode Serah Terima</label>
                    <select name="metode_serah" class="w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text outline-none">
                        <option value="keduanya" {{ old('metode_serah') === 'keduanya' ? 'selected' : '' }}>Keduanya (Ambil di Toko / Antar Hotel)</option>
                        <option value="ambil_di_tempat" {{ old('metode_serah') === 'ambil_di_tempat' ? 'selected' : '' }}>Hanya Ambil di Toko</option>
                        <option value="antar_ke_hotel" {{ old('metode_serah') === 'antar_ke_hotel' ? 'selected' : '' }}>Hanya Antar ke Penginapan</option>
                    </select>
                </div>
            </div>

            {{-- Upload Dokumen Mitra --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                <div>
                    <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Foto KTP Pemilik <span class="text-red-500">*</span></label>
                    <input type="file" name="foto_ktp" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full text-xs text-text border border-line rounded p-2 bg-white" />
                    <p class="text-[10px] text-muted mt-1">Disimpan di storage privat terproteksi.</p>
                </div>
                <div>
                    <label class="block text-xs font-inter font-semibold uppercase tracking-wider text-muted mb-1">Bukti Usaha / Izin / Foto Tempat <span class="text-red-500">*</span></label>
                    <input type="file" name="bukti_usaha" accept=".jpg,.jpeg,.png,.pdf"
                        class="w-full text-xs text-text border border-line rounded p-2 bg-white" />
                    <p class="text-[10px] text-muted mt-1">NIB, SIUP, atau foto fisik tempat usaha.</p>
                </div>
            </div>
        </div>

        <x-button type="submit" class="w-full mt-2">Daftar Sekarang</x-button>
    </form>

    <p class="text-center text-sm text-muted">
        Sudah memiliki akun? <a href="{{ route('login') }}" class="text-forest font-medium hover:underline">Masuk ke Akun</a>
    </p>
</div>

<script>
    document.querySelectorAll('[data-role-radio]').forEach(radio => {
        radio.addEventListener('change', () => {
            const role = radio.value;
            const secGuide = document.getElementById('section-guide');
            const secMitra = document.getElementById('section-mitra');

            if (secGuide) secGuide.classList.toggle('hidden', role !== 'tour_guide');
            if (secMitra) secMitra.classList.toggle('hidden', role !== 'mitra');
        });
    });
</script>
@endsection
