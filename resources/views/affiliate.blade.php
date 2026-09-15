<x-app-layout>
    <x-slot name="title">Program Affiliate</x-slot>

    {{-- ===== HEADER HALAMAN ===== --}}
    <div class="bg-primary text-white py-16 sm:py-20 mb-12 relative overflow-hidden shadow-md" data-track-section="affiliate_hero" data-track-label="Hero Program Affiliate">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
            <span
                class="inline-block bg-accent text-white text-[11px] font-black px-4 py-1.5 uppercase tracking-widest rounded-full mb-5 shadow-sm">
                Program Affiliate
            </span>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black uppercase tracking-tight leading-tight">Program
                Affiliate Record</h1>
            <p class="text-sm sm:text-base text-gray-300 mt-4 max-w-2xl mx-auto leading-relaxed">
                Cukup belanja & selesaikan pesananmu untuk mendapatkan kode referal affiliate. Bagikan kodenya — pembeli
                dapat diskon, kamu dapat cuan!
            </p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-24 space-y-16">

        {{-- ===== PERINGATAN RESMI ===== --}}
        <div
            class="bg-rose-50/90 border border-rose-200 rounded-2xl p-6 sm:p-7 flex flex-col sm:flex-row items-start gap-4 sm:gap-5 shadow-sm transition hover:shadow-md">
            <div class="shrink-0 p-3 bg-rose-100/80 rounded-xl text-rose-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <div class="space-y-2">
                <h2 class="text-sm sm:text-base font-black text-rose-900 uppercase tracking-wide">Pemberitahuan Resmi
                    Record</h2>
                <ul class="text-xs sm:text-sm text-rose-800 space-y-2 leading-relaxed list-disc list-outside pl-4">
                    <li>Program Affiliate Record berlaku otomatis setelah pesanan selesai.</li>
                    <li>Kami <strong>tidak pernah</strong> meminta biaya pendaftaran, deposit, atau data sensitif
                        seperti PIN, OTP, dan password Anda.</li>
                    <li>Nomor WhatsApp resmi kami <strong>+62 813-2306-5554</strong> hanya digunakan untuk bantuan dan
                        konfirmasi resmi.</li>
                </ul>
            </div>
        </div>

        {{-- ===== APA ITU AFFILIATE ===== --}}
        <section class="bg-white border border-gray-100 rounded-2xl p-6 sm:p-8 shadow-sm" data-track-section="affiliate_intro" data-track-label="Pengenalan Program Affiliate">
            <h2 class="text-xl sm:text-2xl font-black text-primary uppercase tracking-tight mb-4">Apa itu Program
                Affiliate Record?</h2>
            <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
                Program Affiliate Record adalah program saling menguntungkan bagi seluruh pelanggan Record. Setelah kamu
                melakukan <strong>Checkout & Bayar</strong> hingga <strong>Pesanan Diterima</strong>, kamu akan langsung
                mendapatkan kode referal affiliate secara otomatis. Kamu cukup membagikan kode referal tersebut ke calon
                pembeli. Pembeli yang menggunakan kodemu akan mendapat potongan harga/diskon, dan kamu sebagai pemilik
                kode akan mendapatkan komisi (cuan).
            </p>
        </section>

        {{-- ===== CARA KERJA ===== --}}
        <section data-track-section="affiliate_steps" data-track-label="Cara Kerja Affiliate (3 Langkah)">
            <div class="text-center sm:text-left mb-8">
                <h2 class="text-xl sm:text-2xl font-black text-primary uppercase tracking-tight">Cara Kerja</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div
                    class="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-sm hover:shadow-md transition-all duration-200 border-l-4 border-l-accent flex flex-col justify-between">
                    <div>
                        <div
                            class="w-10 h-10 rounded-xl bg-accent/10 text-accent text-lg font-black flex items-center justify-center mb-4">
                            1</div>
                        <h3 class="text-sm sm:text-base font-bold uppercase tracking-wide text-primary mb-2">Belanja &
                            Bayar</h3>
                        <p class="text-xs sm:text-sm text-gray-500 leading-relaxed">Pilih produk sepatu favoritmu,
                            lakukan checkout, dan selesaikan pembayaran seperti biasa.</p>
                    </div>
                </div>
                <div
                    class="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-sm hover:shadow-md transition-all duration-200 border-l-4 border-l-accent flex flex-col justify-between">
                    <div>
                        <div
                            class="w-10 h-10 rounded-xl bg-accent/10 text-accent text-lg font-black flex items-center justify-center mb-4">
                            2</div>
                        <h3 class="text-sm sm:text-base font-bold uppercase tracking-wide text-primary mb-2">Pesanan
                            Diterima & Kode Terbit</h3>
                        <p class="text-xs sm:text-sm text-gray-500 leading-relaxed">Setelah pesanan selesai/diterima,
                            kode referal affiliate kamu akan terbit secara otomatis.</p>
                    </div>
                </div>
                <div
                    class="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-sm hover:shadow-md transition-all duration-200 border-l-4 border-l-accent flex flex-col justify-between">
                    <div>
                        <div
                            class="w-10 h-10 rounded-xl bg-accent/10 text-accent text-lg font-black flex items-center justify-center mb-4">
                            3</div>
                        <h3 class="text-sm sm:text-base font-bold uppercase tracking-wide text-primary mb-2">Bagikan
                            Kode ke Calon Pembeli</h3>
                        <p class="text-xs sm:text-sm text-gray-500 leading-relaxed">Bagikan kode referal milikmu ke
                            teman, keluarga, atau di media sosial untuk digunakan saat checkout.</p>
                    </div>
                </div>
                <div
                    class="bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-sm hover:shadow-md transition-all duration-200 border-l-4 border-l-accent flex flex-col justify-between">
                    <div>
                        <div
                            class="w-10 h-10 rounded-xl bg-accent/10 text-accent text-lg font-black flex items-center justify-center mb-4">
                            4</div>
                        <h3 class="text-sm sm:text-base font-bold uppercase tracking-wide text-primary mb-2">Saling
                            Menguntungkan</h3>
                        <p class="text-xs sm:text-sm text-gray-500 leading-relaxed">Pembeli yang memakai kodemu
                            mendapatkan <strong>Diskon</strong>, sedangkan kamu akan menerima <strong>Komisi
                                (Cuan)</strong> ke saldo R_Pay-mu.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== KEUNTUNGAN ===== --}}
        <section data-track-section="affiliate_benefits" data-track-label="Keuntungan & Simulasi Komisi">
            <div class="text-center sm:text-left mb-8">
                <h2 class="text-xl sm:text-2xl font-black text-primary uppercase tracking-tight">Keuntungan Program
                    Affiliate</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @foreach ([
                        ['icon' => 'fa-solid fa-handshake', 'title' => 'Saling Menguntungkan', 'desc' => 'Teman/pembeli dapat potongan diskon belanja, kamu sebagai pemilik kode dapat komisi uang.'],
                        ['icon' => 'fa-solid fa-bolt', 'title' => 'Otomatis & Tanpa Syarat Rumit', 'desc' => 'Cukup selesaikan pesananmu, kode referal langsung aktif tanpa daftar manual.'],
                        ['icon' => 'fa-solid fa-wallet', 'title' => 'Komisi Masuk Saldo R_Pay', 'desc' => 'Setiap pembelian yang menggunakan kodemu akan menghasilkan komisi langsung ke saldo R_Pay-mu.'],
                        ['icon' => 'fa-solid fa-box-open', 'title' => 'Tanpa Stok & Tanpa Ribet', 'desc' => 'Kamu tidak perlu stok barang atau repot kirim paket, cukup bagikan kode referalmu.'],
                    ] as $benefit)
                    <div
                        class="flex items-start gap-4 sm:gap-5 bg-white border border-gray-100 rounded-2xl p-6 sm:p-7 shadow-sm hover:shadow-md transition-all duration-200">
                        <div class="h-11 w-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i class="{{ $benefit['icon'] }} text-primary text-base"></i>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wide text-gray-900">
                                {{ $benefit['title'] }}
                            </h3>
                            <p class="text-xs sm:text-sm text-gray-500 leading-relaxed">{{ $benefit['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ===== CTA BELANJA ===== --}}
        <section class="bg-primary rounded-2xl p-8 sm:p-12 text-center text-white shadow-xl relative overflow-hidden" data-track-section="affiliate_cta" data-track-label="CTA Belanja & Gabung Affiliate">
            <div class="max-w-xl mx-auto space-y-5 relative z-10">
                <h2 class="text-2xl sm:text-2xl font-black uppercase tracking-tight text-white">Mulai Belanja & Dapatkan
                    Kodenya!</h2>
                <p class="text-sm sm:text-base text-gray-200 leading-relaxed max-w-lg mx-auto">
                    Belanja sepatu impianmu sekarang di Record, selesaikan pesanan, dan langsung bagikan kode referalmu
                    untuk menghasilkan cuan.
                </p>
                <div class="pt-2">
                    <a href="{{ route('home') }}"
                        class="inline-flex items-center justify-center gap-3 bg-accent hover:bg-accent/90 text-white text-sm font-bold px-8 py-4 rounded-xl transition-all duration-200 uppercase tracking-wider shadow-lg hover:shadow-2xl transform hover:-translate-y-0.5">
                        <i class="fa-solid fa-cart-shopping text-xl"></i>
                        <span>Belanja Sekarang</span>
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>