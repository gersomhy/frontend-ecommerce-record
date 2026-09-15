<x-app-layout>
    <x-slot name="title">Checkout Pesanan</x-slot>

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet (peta OpenStreetMap) untuk pemilihan titik lokasi pengiriman -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    @php
        // Halaman ini juga bisa dibuka tamu, jadi seluruh akses ke data akun
        // harus aman saat belum ada yang login.
        $akun      = Auth::user();
        $userPhone = $akun?->phone;
        $hasPhone  = !empty($userPhone) && $userPhone !== 'Belum diatur';
        $namaAkun  = $akun?->name ?? '';
        $hpAkun    = $hasPhone ? $userPhone : '';
    @endphp

    <script>
        function checkoutData() {
            return {
                showAddressModal: false,
                showCourierModal: false,
                showPaymentModal: false,
                selectedAddressId: @json($addresses->first()?->id ?? ''),
                selectedAddressText: @json($addresses->first() ? $addresses->first()->recipient_name . " (" . $addresses->first()->phone . ") - " . $addresses->first()->full_address : ''),
                selectedAddressLabel: @json($addresses->first()?->label ?? ''),
                selectedCourier: @json($defaultCouriers[0]['code'] ?? ''),
                selectedPayment: 'MANUAL_BCA',
                hasPhone: @json($hasPhone),

                {{-- ── Penguncian bertahap ────────────────────────────── --}}
                kontakLengkap: @json($kontakLengkap),
                showKontakForm: @json(! $kontakLengkap),

                {{-- Panel masuk. Dibuka sendiri bila percobaan sebelumnya gagal,
                     supaya pesan galatnya langsung terlihat. --}}
                bukaMasuk: @json($errors->getBag('masuk')->any()),

                get alamatLengkap() {
                    if (! this.kontakLengkap) return false;
                    if (this.selectedAddressId !== '') return true;

                    return !!(this.newAddrLabel && this.newAddrName && this.newAddrPhone
                        && this.newAddrLine && this.newAddrCity
                        && this.newAddrProvince && this.newAddrPostal);
                },
                get kurirTerpilih() {
                    return this.alamatLengkap && this.selectedCourier !== '';
                },
                get bayarTerpilih() {
                    return this.kurirTerpilih && this.selectedPayment !== '';
                },
                get siapPesan() {
                    return this.bayarTerpilih;
                },

                {{-- Satu pintu untuk membuka tiap langkah, sekaligus memberi
                     tahu apa yang harus diisi lebih dulu bila masih terkunci --}}
                bukaLangkah(nama) {
                    if (nama === 'alamat') {
                        if (! this.kontakLengkap) {
                            this.showKontakForm = true;
                            return this.ingatkan('Lengkapi data kontak terlebih dahulu.');
                        }
                        this.showAddressModal = true;
                        return;
                    }

                    if (nama === 'kurir') {
                        if (! this.alamatLengkap) {
                            return this.ingatkan('Pilih atau lengkapi alamat pengiriman terlebih dahulu.');
                        }
                        this.showCourierModal = true;
                        return;
                    }

                    if (nama === 'bayar') {
                        if (! this.kurirTerpilih) {
                            return this.ingatkan('Pilih kurir pengiriman terlebih dahulu.');
                        }
                        this.showPaymentModal = true;
                    }
                },

                pesanKunci: '',
                ingatkan(teks) {
                    this.pesanKunci = teks;
                    setTimeout(() => { this.pesanKunci = ''; }, 3500);
                },

                couriers: @json($defaultCouriers ?? []),

                get kurirInstan() {
                    return this.couriers.filter(c => c.jenis === 'instan');
                },
                get kurirReguler() {
                    return this.couriers.filter(c => c.jenis !== 'instan');
                },
                // Saldo R_Pay pembeli, dipakai untuk menandai pilihan
                // pembayaran yang saldonya tidak mencukupi.
                saldoRpay: {{ (float) ($akun?->rpay_balance ?? 0) }},

                get rpayCukup() {
                    return this.saldoRpay >= this.getTotal();
                },

                payments: [
                    {code: 'MANUAL_BCA', name: 'Transfer Bank BCA (Manual)',          type: 'Rek: 1000028122 a.n Lily Minawati Prajogo (Verifikasi Admin)', icon: 'fa-solid fa-money-bill-transfer'},
                    {code: 'R_Pay', name: 'R_Pay (Saldo Dompet)', type: 'Langsung lunas, tanpa transfer', icon: 'fa-solid fa-wallet'},
                    {code: 'QRIS',      name: 'QRIS (Semua E-Wallet & M-Banking)', type: 'GoPay, OVO, Dana, ShopeePay, LinkAja', icon: 'fa-solid fa-qrcode'},
                    {code: 'BCA',       name: 'Transfer BCA Virtual Account',       type: 'Verifikasi Otomatis 24 Jam',           icon: 'fa-solid fa-building-columns'},
                    {code: 'BNI',       name: 'Transfer BNI Virtual Account',       type: 'Verifikasi Otomatis 24 Jam',           icon: 'fa-solid fa-building-columns'},
                    {code: 'BRI',       name: 'Transfer BRI Virtual Account',       type: 'Verifikasi Otomatis 24 Jam',           icon: 'fa-solid fa-building-columns'},
                    {code: 'Mandiri',   name: 'Mandiri Bill Payment',               type: 'Verifikasi Otomatis 24 Jam',           icon: 'fa-solid fa-building-columns'},
                    {code: 'Indomaret', name: 'Indomaret / Ceriamart',              type: 'Bayar di Kasir Outlet',               icon: 'fa-solid fa-store'},
                    {code: 'Alfamart',  name: 'Alfamart / Alfamidi',                type: 'Bayar di Kasir Outlet',               icon: 'fa-solid fa-store'},
                ],
                newAddrLabel: 'Alamat Rumah',
                newAddrName: @json($namaAkun),
                newAddrPhone: @json($hpAkun),
                newAddrLine: '',
                newAddrCity: '',
                newAddrProvince: '',
                newAddrPostal: '',
                newAddrLat: -7.2575,
                newAddrLng: 112.7521,

                // Wilayah Indonesia (Cascading Dropdowns)
                provinces: [],
                cities: [],
                districts: [],
                selectedProvinceId: '',
                selectedCityId: '',
                selectedDistrictId: '',
                loadingProvinces: false,
                loadingCities: false,
                loadingDistricts: false,

                init() {
                    this.fetchProvinces();
                },

                async fetchProvinces() {
                    this.loadingProvinces = true;
                    try {
                        const res = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json');
                        this.provinces = await res.json();
                    } catch(e) {
                        console.error('Failed to fetch provinces', e);
                    }
                    this.loadingProvinces = false;
                },

                // Pengambilan daftar wilayah dipisah dari penanganan dropdown,
                // supaya pengisian otomatis bisa memakainya tanpa ikut mereset
                // pilihan yang baru saja ditetapkan.
                async muatKota() {
                    this.cities = [];
                    if (!this.selectedProvinceId) return;

                    this.loadingCities = true;
                    try {
                        const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${this.selectedProvinceId}.json`);
                        this.cities = await res.json();
                    } catch(e) {
                        console.error('Gagal memuat daftar kota', e);
                    }
                    this.loadingCities = false;
                },

                async muatKecamatan() {
                    this.districts = [];
                    if (!this.selectedCityId) return;

                    this.loadingDistricts = true;
                    try {
                        const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${this.selectedCityId}.json`);
                        this.districts = await res.json();
                    } catch(e) {
                        console.error('Gagal memuat daftar kecamatan', e);
                    }
                    this.loadingDistricts = false;
                },

                async onProvinceChange() {
                    this.selectedCityId = '';
                    this.selectedDistrictId = '';
                    this.districts = [];

                    const prov = this.provinces.find(p => p.id == this.selectedProvinceId);
                    this.newAddrProvince = prov ? prov.name : '';

                    await this.muatKota();
                },

                async onCityChange() {
                    this.selectedDistrictId = '';

                    const city = this.cities.find(c => c.id == this.selectedCityId);
                    this.newAddrCity = city ? city.name : '';

                    await this.muatKecamatan();

                    if (this.newAddrCity) {
                        this.geocodeRegionText(this.newAddrCity + ', ' + this.newAddrProvince, 12);
                    }
                },

                async onDistrictChange() {
                    const dist = this.districts.find(d => d.id == this.selectedDistrictId);
                    if (!dist) return;

                    // Kecamatan adalah tingkat terkecil yang kita punya, jadi di
                    // sinilah kode pos paling mungkin ditemukan dengan tepat.
                    this.cariOpsiKodePos();

                    this.geocodeRegionText(
                        'Kecamatan ' + dist.name + ', ' + this.newAddrCity + ', ' + this.newAddrProvince, 14
                    );
                },

                // Pengisian kode pos otomatis.
                kodePosDiketikSendiri: false,
                opsiKodePos: [],
                memuatKodePos: false,

                // Nilai yang sedang dipilih di dropdown. Dipisah dari
                // newAddrPostal supaya penanda "__manual__" tidak pernah
                // ikut terkirim sebagai kode pos.
                pilihanKodePos: '',
                isiKodePosSendiri: false,

                /**
 * Beralih ke isian manual.
 */
                mulaiIsiSendiri() {
                    this.isiKodePosSendiri = true;
                    this.pilihanKodePos = '';
                    this.newAddrPostal = '';
                    this.kodePosDiketikSendiri = true;

                    this.$nextTick(() => this.$refs.kodePosManual?.focus());
                },

                kembaliKeDaftar() {
                    this.isiKodePosSendiri = false;
                    this.newAddrPostal = '';
                    this.pilihanKodePos = '';
                    this.kodePosDiketikSendiri = false;
                },

                pilihKodePos() {
                    if (this.pilihanKodePos === '__manual__') {
                        this.mulaiIsiSendiri();
                        return;
                    }

                    this.newAddrPostal = this.pilihanKodePos;
                    this.kodePosDiketikSendiri = this.pilihanKodePos !== '';
                },

                terapkanKodePos(kode) {
                    if (! kode) return;
                    if (this.kodePosDiketikSendiri) return;

                    const bersih = String(kode).match(/\b\d{5}\b/);
                    if (bersih) this.newAddrPostal = bersih[0];
                },

                /**
 * Mengambil daftar kode pos untuk wilayah yang dipilih.
 */
                async cariOpsiKodePos() {
                    const dist = this.districts.find(d => d.id == this.selectedDistrictId);

                    if (! dist || ! this.newAddrCity) {
                        this.opsiKodePos = [];
                        return;
                    }

                    // Awalan "KOTA"/"KABUPATEN" dibuang supaya pencariannya
                    // cocok dengan penamaan di data kurir.
                    const kota = this.newAddrCity.replace(/^(KOTA ADM\.?|KOTA|KABUPATEN|KAB\.?)\s+/i, '');
                    const kueri = dist.name + ' ' + kota;

                    this.memuatKodePos = true;

                    try {
                        const url = @json(route('kodepos.cari')) + '?q=' + encodeURIComponent(kueri);
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        this.opsiKodePos = res.ok ? ((await res.json()).pilihan ?? []) : [];
                    } catch (e) {
                        this.opsiKodePos = [];
                    }

                    this.memuatKodePos = false;

                    if (this.opsiKodePos.length === 1) {
                        this.terapkanKodePos(this.opsiKodePos[0].kode);
                        return;
                    }

                    // Kode pos lama dilepas kalau tidak ada di daftar wilayah
                    // yang baru — kalau dibiarkan, paket bisa dikirim ke
                    // kecamatan lain tanpa disadari.
                    if (this.opsiKodePos.length > 1 && this.newAddrPostal
                        && ! this.opsiKodePos.some(o => o.kode === this.newAddrPostal)) {
                        this.newAddrPostal = '';
                        this.kodePosDiketikSendiri = false;
                    }
                },

                // ── Pencocokan nama wilayah ──────────────────────────────
                //
                // Data wilayah memakai ejaan resmi berhuruf kapital
                // ("KOTA SURABAYA", "JAWA TIMUR", "BULAK"), sedangkan alamat
                // yang diketik pembeli memakai singkatan bebas ("Surabaya",
                // "Kec. Bulak"). Awalan administratif dibuang lebih dulu agar
                // keduanya bisa dibandingkan.
                // Singkatan yang lazim dipakai Google Maps dan pembeli, tetapi
                // tidak ada di data wilayah resmi. "Kota SBY" khususnya muncul
                // pada hampir semua alamat Surabaya hasil salin dari Maps.
                aliasWilayah: {
                    'SBY': 'SURABAYA',
                    'JKT': 'JAKARTA',
                    'BDG': 'BANDUNG',
                    'JOGJA': 'YOGYAKARTA',
                    'JOGJAKARTA': 'YOGYAKARTA',
                    'YOGYA': 'YOGYAKARTA',
                    'DIY': 'DI YOGYAKARTA',
                    'DAERAH ISTIMEWA YOGYAKARTA': 'DI YOGYAKARTA',
                    'DKI': 'DKI JAKARTA',
                    'DAERAH KHUSUS IBUKOTA JAKARTA': 'DKI JAKARTA',
                    'DAERAH KHUSUS JAKARTA': 'DKI JAKARTA',
                    'NAD': 'ACEH',
                    'NANGGROE ACEH DARUSSALAM': 'ACEH',
                },

                // Singkatan per kata yang dipakai Google Maps pada nama
                // kecamatan, mis. "Kec. Kby. Baru" untuk Kebayoran Baru.
                singkatanKata: {
                    'KBY': 'KEBAYORAN', 'TJ': 'TANJUNG', 'PS': 'PASAR',
                    'GN': 'GUNUNG', 'PDK': 'PONDOK', 'KEP': 'KEPULAUAN',
                    'CEMP': 'CEMPAKA', 'SLTN': 'SELATAN', 'UTR': 'UTARA',
                    'PLG': 'PULO', 'JATI': 'JATI', 'BTG': 'BATANG',
                },

                normalisasiWilayah(teks) {
                    const bersih = (teks || '')
                        .toUpperCase()
                        .replace(/^(PROVINSI|PROV|PROP)\.?\s+/, '')
                        .replace(/^(KOTA ADMINISTRASI|KOTA ADM|KOTAMADYA|KOTA|KABUPATEN|KAB)\.?\s+/, '')
                        .replace(/^(KECAMATAN|KEC)\.?\s+/, '')
                        .replace(/^(KELURAHAN|KEL|DESA)\.?\s+/, '')
                        .replace(/[^A-Z0-9]+/g, ' ')
                        .trim()
                        .split(' ')
                        .map(kata => this.singkatanKata[kata] || kata)
                        .join(' ');

                    return this.aliasWilayah[bersih] || bersih;
                },

                // "Kota Bandung" dan "Kabupaten Bandung" sama-sama menyusut
                // menjadi "BANDUNG", jadi jenisnya harus ikut diperiksa —
                // tanpa ini alamat Kota Bandung bisa mendarat di kabupaten.
                jenisWilayah(teks) {
                    const t = (teks || '').toUpperCase();
                    if (/^(KOTA ADMINISTRASI|KOTA ADM|KOTAMADYA|KOTA)\.?\s/.test(t)) return 'kota';
                    if (/^(KABUPATEN|KAB)\.?\s/.test(t)) return 'kabupaten';
                    return '';
                },

                // Bila beberapa wilayah bernama sama, pilih yang jenisnya
                // sesuai. Saat pembeli tidak menyebut jenisnya, kota
                // didahulukan karena jauh lebih sering jadi tujuan kiriman.
                pilihJenisTepat(cocok, teksAsli) {
                    if (cocok.length <= 1) return cocok[0] || null;

                    const diminta = this.jenisWilayah(teksAsli) || 'kota';
                    return cocok.find(w => this.jenisWilayah(w.name) === diminta) || cocok[0];
                },

                cariWilayah(daftar, nama) {
                    const cari = this.normalisasiWilayah(nama);
                    if (!cari || !daftar.length) return null;

                    const persis = daftar.filter(w => this.normalisasiWilayah(w.name) === cari);
                    if (persis.length) return this.pilihJenisTepat(persis, nama);

                    const termuat = daftar.filter(w => {
                        const n = this.normalisasiWilayah(w.name);
                        return n.length >= 4 && (' ' + cari + ' ').includes(' ' + n + ' ');
                    });

                    return this.pilihJenisTepat(termuat, nama);
                },

                // Menelusuri potongan alamat dari belakang, karena alamat
                // Indonesia ditulis dari yang paling rinci ke yang paling umum
                // — provinsi dan kota selalu berada di ujung. Cara ini juga
                // mencegah kata "Surabaya" pada nama jalan ikut tertangkap.
                cocokkanDariPotongan(daftar, potongan) {
                    for (let i = potongan.length - 1; i >= 0; i--) {
                        const teks = this.normalisasiWilayah(potongan[i]);
                        const cocok = daftar.filter(w => this.normalisasiWilayah(w.name) === teks);
                        const hit = this.pilihJenisTepat(cocok, potongan[i]);
                        if (hit) return { wilayah: hit, indeks: i };
                    }

                    for (let i = potongan.length - 1; i >= 0; i--) {
                        const teks = ' ' + this.normalisasiWilayah(potongan[i]) + ' ';
                        const cocok = daftar.filter(w => {
                            const n = this.normalisasiWilayah(w.name);
                            return n.length >= 4 && teks.includes(' ' + n + ' ');
                        });
                        const hit = this.pilihJenisTepat(cocok, potongan[i]);
                        if (hit) return { wilayah: hit, indeks: i };
                    }

                    return null;
                },

                // Menyelaraskan ketiga dropdown dengan nama wilayah apa pun
                // asalnya — hasil pencarian teks maupun pembacaan balik peta.
                async sinkronkanWilayah(namaProvinsi, namaKota, namaKecamatan) {
                    if (!this.provinces.length) await this.fetchProvinces();

                    const prov = this.cariWilayah(this.provinces, namaProvinsi);
                    if (!prov) return;

                    if (this.selectedProvinceId != prov.id) {
                        this.selectedProvinceId = prov.id;
                        this.newAddrProvince = prov.name;
                        this.selectedCityId = '';
                        this.selectedDistrictId = '';
                        await this.muatKota();
                    }

                    const kota = this.cariWilayah(this.cities, namaKota);
                    if (!kota) return;

                    if (this.selectedCityId != kota.id) {
                        this.selectedCityId = kota.id;
                        this.newAddrCity = kota.name;
                        this.selectedDistrictId = '';
                        await this.muatKecamatan();
                    }

                    const kec = this.cariWilayah(this.districts, namaKecamatan);
                    if (kec) this.selectedDistrictId = kec.id;
                },

                // ── Pencarian koordinat ──────────────────────────────────
                //
                // Permintaan tidak lagi dikirim langsung ke Nominatim dari
                // browser — layanan itu menolak permintaan beruntun dengan
                // 403/429, dan dulu penolakannya ditelan diam-diam sehingga
                // pin peta tidak pernah bergerak. Sekarang lewat server
                // sendiri yang menyimpan hasilnya di cache.

                // Beberapa nama provinsi resmi tidak dikenali peta apa adanya.
                namaUntukPeta(nama) {
                    const t = (nama || '').toUpperCase();
                    if (t === 'DI YOGYAKARTA') return 'Daerah Istimewa Yogyakarta';
                    if (t === 'DKI JAKARTA')   return 'Jakarta';
                    return nama;
                },

                async mintaNominatim(query) {
                    if (!query) return null;

                    try {
                        const url = @json(route('geocode.cari')) + '?q=' + encodeURIComponent(query);
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) return null;

                        const data = await res.json();
                        return data.hasil || null;
                    } catch(e) {
                        return null;
                    }
                },

                async geocodeRegionText(query, zoom = 13) {
                    const hasil = await this.mintaNominatim(query);
                    if (! hasil) return;

                    this.pasangTitik(hasil, zoom);

                    if (hasil.address?.postcode) {
                        this.terapkanKodePos(hasil.address.postcode);
                        return;
                    }

                    // Pencarian teks kerap tidak menyertakan kode pos, sedangkan
                    // pembacaan balik di titik yang sama jauh lebih sering memuatnya.
                    await this.lengkapiKodePos(hasil.lat, hasil.lon);
                },

                /**
 * Upaya kedua mencari kode pos, lewat pembacaan balik peta.
 */
                async lengkapiKodePos(lat, lng) {
                    if (this.kodePosDiketikSendiri || this.newAddrPostal) return;

                    try {
                        const url = @json(route('geocode.balik')) + `?lat=${lat}&lng=${lng}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (! res.ok) return;

                        const data = await res.json();
                        this.terapkanKodePos(data.hasil?.address?.postcode);
                    } catch (e) {
                        // Gangguan jaringan bukan alasan menggagalkan pengisian alamat.
                    }
                },

                // Memindahkan pin peta ke hasil geocode tanpa menyentuh kolom
                // isian, supaya alamat yang sudah diketik pembeli tidak tertimpa.
                pasangTitik(hasil, zoom = 16) {
                    const lat = parseFloat(hasil.lat);
                    const lng = parseFloat(hasil.lon);
                    if (isNaN(lat) || isNaN(lng)) return false;

                    this.newAddrLat = lat;
                    this.newAddrLng = lng;
                    this.flyMapTo(lat, lng, zoom);
                    this.recalculateShippingCost();
                    return true;
                },

                map: null,
                marker: null,
                mapInitialized: false,
                pasteAddressText: '',
                searchingAddress: false,
                addressSearchError: '',
                mapLayer: 'street',
                currentTileLayer: null,
                coordNotice: false,

                initMapIfNeeded() {
                    if (this.mapInitialized) {
                        this.$nextTick(() => { if (this.map) this.map.invalidateSize(); });
                        return;
                    }
                    this.mapInitialized = true;

                    this.$nextTick(() => {
                        if (this.$refs.mapContainer) {
                            // Peta OpenStreetMap + CartoDB Voyager yang stabil & jernih
                            this.map = L.map(this.$refs.mapContainer).setView([this.newAddrLat, this.newAddrLng], 15);

                            this.currentTileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap contributors',
                                maxZoom: 19
                            }).addTo(this.map);

                            this.marker = L.marker([this.newAddrLat, this.newAddrLng], { draggable: true }).addTo(this.map);

                            // Menggeser pin peta HANYA memperbarui titik Latitude/Longitude presisi
                            // TANPA menimpa atau mereset teks alamat & dropdown kota pembeli.
                            this.marker.on('dragend', () => {
                                const pos = this.marker.getLatLng();
                                this.newAddrLat = pos.lat;
                                this.newAddrLng = pos.lng;
                                this.coordNotice = true;
                                setTimeout(() => { this.coordNotice = false; }, 3000);
                                this.recalculateShippingCost();
                            });

                            this.map.on('click', (e) => {
                                this.marker.setLatLng(e.latlng);
                                this.newAddrLat = e.latlng.lat;
                                this.newAddrLng = e.latlng.lng;
                                this.coordNotice = true;
                                setTimeout(() => { this.coordNotice = false; }, 3000);
                                this.recalculateShippingCost();
                            });
                        }

                        setTimeout(() => { if (this.map) this.map.invalidateSize(); }, 200);
                    });
                },

                flyMapTo(lat, lng, zoom = 16) {
                    this.initMapIfNeeded();
                    this.$nextTick(() => {
                        if (this.map && this.marker) {
                            this.map.setView([lat, lng], zoom);
                            this.marker.setLatLng([lat, lng]);
                        }
                    });
                },

                // Dipakai hanya saat pencarian alamat dari tombol (bukan saat geser pin manual).
                async applyGeocodeResult(result, moveMap = true) {
                    const addr = result.address || {};

                    const nomor     = addr.house_number ? addr.house_number + ', ' : '';
                    const jalan     = addr.road || addr.pedestrian || addr.residential || '';
                    const kelurahan = addr.village || addr.suburb || addr.neighbourhood || '';

                    // HANYA isi jika teks alamat masih kosong
                    if (!this.newAddrLine.trim()) {
                        this.newAddrLine = (nomor + jalan + (kelurahan ? ', ' + kelurahan : '')).trim()
                            || result.display_name || '';
                    }

                    const provinsi  = addr.state || '';
                    const kota      = addr.city || addr.town || addr.municipality || addr.county || '';
                    const kecamatan = addr.city_district || addr.subdistrict
                        || (addr.city || addr.town ? addr.county : '') || addr.suburb || '';

                    if (provinsi && !this.newAddrProvince) this.newAddrProvince = provinsi;
                    if (kota && !this.newAddrCity)         this.newAddrCity     = kota;
                    this.terapkanKodePos(addr.postcode);

                    if (!this.newAddrProvince || !this.newAddrCity) {
                        await this.sinkronkanWilayah(provinsi, kota, kecamatan);
                    }

                    const lat = parseFloat(result.lat);
                    const lng = parseFloat(result.lon);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        this.newAddrLat = lat;
                        this.newAddrLng = lng;
                        if (moveMap) this.flyMapTo(lat, lng);
                    }

                    this.recalculateShippingCost();
                },

                async recalculateShippingCost(lat = null, lng = null, city = null, postal = null) {
                    const useLat = lat !== null ? lat : this.newAddrLat;
                    const useLng = lng !== null ? lng : this.newAddrLng;
                    const useCity = city !== null ? city : (this.newAddrCity || '');
                    const usePostal = postal !== null ? postal : (this.newAddrPostal || this.newAddrPostalCode || '');

                    if (!useLat || !useLng) return;
                    try {
                        const res = await fetch('{{ route('checkout.shipping-cost') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                latitude: useLat,
                                longitude: useLng,
                                city: useCity,
                                postal_code: usePostal
                            })
                        });
                        const data = await res.json();
                        if (data.status === 'success' && data.couriers.length > 0) {
                            this.couriers = data.couriers;
                            if (!this.selectedCourier || !this.couriers.find(c => c.code === this.selectedCourier)) {
                                this.selectedCourier = this.couriers[0].code;
                            }
                        }
                    } catch(e) {
                        console.error('Failed to recalculate shipping cost', e);
                    }
                },

                // Mengurai alamat yang ditempel pembeli.
                async searchPastedAddress() {
                    const asli = this.pasteAddressText.trim();
                    if (!asli) return;

                    this.searchingAddress = true;
                    this.addressSearchError = '';

                    try {
                        // Kode pos: lima angka berurutan di mana pun dalam teks.
                        const kodePos = asli.match(/\b(\d{5})\b/);
                        if (kodePos) this.newAddrPostal = kodePos[1];

                        // Kode pos dibuang dari potongan agar "Jawa Timur 60129"
                        // tetap cocok dengan "JAWA TIMUR".
                        const potongan = asli.split(',')
                            .map(s => s.replace(/\b\d{5}\b/g, '').replace(/\s+/g, ' ').trim())
                            .filter(Boolean);

                        if (!this.provinces.length) await this.fetchProvinces();

                        let namaKecamatan = '';
                        let batasAlamat = potongan.length;

                        const prov = this.cocokkanDariPotongan(this.provinces, potongan);

                        if (prov) {
                            this.selectedProvinceId = prov.wilayah.id;
                            this.newAddrProvince = prov.wilayah.name;
                            batasAlamat = prov.indeks;
                            await this.muatKota();

                            const kota = this.cocokkanDariPotongan(this.cities, potongan.slice(0, prov.indeks));

                            if (kota) {
                                this.selectedCityId = kota.wilayah.id;
                                this.newAddrCity = kota.wilayah.name;
                                batasAlamat = kota.indeks;
                                await this.muatKecamatan();

                                const kec = this.cocokkanDariPotongan(this.districts, potongan.slice(0, kota.indeks));
                                if (kec) {
                                    this.selectedDistrictId = kec.wilayah.id;
                                    namaKecamatan = kec.wilayah.name;
                                }
                            } else {
                                this.selectedCityId = '';
                                this.selectedDistrictId = '';
                            }
                        }

                        // Sisa potongan di depan adalah nama jalan, nomor rumah,
                        // kelurahan, dan kecamatan — semuanya tetap disimpan
                        // pada alamat lengkap supaya tidak ada yang hilang.
                        const rincian = potongan.slice(0, batasAlamat).join(', ');
                        this.newAddrLine = rincian || asli;

                        // Titik peta dicari bertahap: dari alamat terrinci,
                        // mundur ke kecamatan, lalu kota. Dengan begitu pin
                        // selalu mendarat sedekat mungkin — bukan tidak
                        // bergerak sama sekali seperti sebelumnya.
                        const kota     = this.namaUntukPeta(this.newAddrCity);
                        const provinsi = this.namaUntukPeta(this.newAddrProvince);
                        const kec      = namaKecamatan ? 'Kecamatan ' + namaKecamatan : '';
                        const wilayah  = [kec, kota, provinsi].filter(Boolean).join(', ');

                        // Potongan terakhir sebelum kota biasanya kelurahan —
                        // jauh lebih mudah ditemukan daripada nama jalan.
                        const kelurahan = potongan.slice(0, batasAlamat).pop();

                        const kandidat = [
                            rincian && wilayah ? rincian + ', ' + wilayah : '',
                            rincian && kota    ? rincian + ', ' + kota    : '',
                            kelurahan && wilayah ? kelurahan + ', ' + wilayah : '',
                            kelurahan && kota    ? kelurahan + ', ' + kota    : '',
                            wilayah,
                            // Tanpa provinsi: sebagian nama provinsi resmi
                            // tidak dikenali peta, sehingga kueri yang terlalu
                            // lengkap justru tidak membuahkan hasil.
                            kec && kota ? kec + ', ' + kota : '',
                            kota && provinsi ? kota + ', ' + provinsi : '',
                            kota,
                        ].filter(Boolean);

                        if (!kandidat.length) kandidat.push(asli);

                        let titik = null;
                        for (const q of kandidat) {
                            titik = await this.mintaNominatim(q);
                            if (titik) break;
                        }

                        if (titik) {
                            this.pasangTitik(titik, rincian ? 17 : 13);
                            if (! this.newAddrPostal) {
                                this.terapkanKodePos(titik.address?.postcode);
                                await this.lengkapiKodePos(titik.lat, titik.lon);
                            }
                        }

                        if (!prov) {
                            this.addressSearchError = 'Provinsi tidak dikenali dari teks itu. '
                                + 'Pastikan alamat memuat nama kota dan provinsi, atau pilih sendiri di kolom bawah.';
                        } else if (!this.selectedCityId) {
                            this.addressSearchError = 'Kota/kabupaten tidak dikenali. Silakan pilih sendiri di kolom bawah.';
                        } else if (!titik) {
                            this.addressSearchError = 'Kolom wilayah sudah terisi, tetapi titik lokasinya belum ketemu. '
                                + 'Geser pin di peta untuk menandai lokasi persisnya.';
                        }
                    } finally {
                        this.searchingAddress = false;
                    }
                },

                async reverseGeocode(lat, lng) {
                    try {
                        const url = @json(route('geocode.balik')) + `?lat=${lat}&lng=${lng}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) return;

                        const data = await res.json();
                        if (data.hasil && data.hasil.address) {
                            await this.applyGeocodeResult(data.hasil, false);
                        }
                    } catch (e) {}
                },

                useMyLocation() {
                    if (!navigator.geolocation) {
                        this.addressSearchError = 'Browser Anda tidak mendukung deteksi lokasi.';
                        return;
                    }

                    navigator.geolocation.getCurrentPosition((pos) => {
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        this.newAddrLat = lat;
                        this.newAddrLng = lng;
                        this.flyMapTo(lat, lng);
                        this.reverseGeocode(lat, lng);
                        this.recalculateShippingCost();
                    }, () => {
                        this.addressSearchError = 'Tidak bisa mengambil lokasi Anda. Izinkan akses lokasi di pengaturan browser.';
                    });
                },

                getCourierCost() {
                    const c = this.couriers.find(item => item.code === this.selectedCourier);
                    return c ? c.cost : 15000;
                },
                
                getCourierName() {
                    const c = this.couriers.find(item => item.code === this.selectedCourier);
                    return c ? c.name : '';
                },

                getPaymentName() {
                    const p = this.payments.find(item => item.code === this.selectedPayment);
                    return p ? p.name : '';
                },

                formatPrice(price) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(price);
                },

                /** Angka saja, tanpa awalan "Rp" — dipakai di dalam kalimat. */
                formatAngka(nilai) {
                    return new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(nilai)));
                },

                /** Total yang harus dibayar: harga barang − diskon + ongkos kirim. */
                getTotal() {
                    return this.subtotalBarang - this.diskonReferal + this.getCourierCost();
                },

                // ── Ubah jumlah langsung dari checkout ───────────────────
                // Nilai barang yang dicentang.
                subtotalBarang: {{ (float) $cart->total_terpilih }},

                // Jumlah per baris keranjang, supaya angkanya berubah seketika saat ditekan — tidak menunggu jawaba...
                jumlahItem: @json($cartItems->pluck('quantity', 'id')),

                // Baris yang sedang menunggu jawaban peladen.
                jumlahSibuk: null,

                async ubahJumlah(id, jumlahBaru, stok) {
                    jumlahBaru = Math.round(jumlahBaru);

                    if (jumlahBaru < 1 || jumlahBaru > stok || this.jumlahSibuk !== null) return;

                    const sebelumnya = this.jumlahItem[id];
                    this.jumlahItem[id] = jumlahBaru;
                    this.jumlahSibuk = id;

                    try {
                        const res = await fetch('/cart/' + id, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ _method: 'PATCH', quantity: jumlahBaru }),
                        });

                        if (! res.ok) throw new Error('gagal');

                        const data = await res.json();

                        // Peladen bisa memangkas jumlahnya kalau stok berkurang sejak halaman dibuka.
                        this.jumlahItem[id] = data.jumlah_item || jumlahBaru;
                        this.subtotalBarang = data.total_terpilih;

                        window.dispatchEvent(new CustomEvent('cart-count-updated', { detail: { count: data.cart_count } }));

                        // Ongkir ikut berubah karena beratnya berubah, dan diskon referal dihitung dari nilai barang — kedu...
                        this.recalculateShippingCost();

                        if (this.referalStatus === 'benar') this.periksaReferal();
                    } catch (e) {
                        this.jumlahItem[id] = sebelumnya;
                    } finally {
                        this.jumlahSibuk = null;
                    }
                },

                // ── Kode referal ─────────────────────────────────────────
                kodeReferal: '',
                referalStatus: 'kosong',   // kosong | benar | salah
                referalPesan: '',
                pemilikReferal: '',
                diskonReferal: 0,
                memeriksaReferal: false,

                /**
 * Keabsahan kode ditentukan server, bukan di sini.
 */
                async periksaReferal() {
                    const kode = this.kodeReferal.trim().toUpperCase();

                    if (! kode) { this.lepasReferal(); return; }

                    this.memeriksaReferal = true;

                    try {
                        const url = @json(route('referal.periksa')) + '?kode=' + encodeURIComponent(kode);
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json();

                        if (data.sah) {
                            this.referalStatus  = 'benar';
                            this.pemilikReferal = data.pemilik;
                            this.diskonReferal  = data.diskon;
                            this.referalPesan   = '';
                        } else {
                            this.referalStatus  = 'salah';
                            this.referalPesan   = data.alasan || 'Kode referal tidak valid.';
                            this.diskonReferal  = 0;
                        }
                    } catch (e) {
                        this.referalStatus = 'salah';
                        this.referalPesan  = 'Gagal memeriksa kode. Periksa koneksi internetmu.';
                        this.diskonReferal = 0;
                    }

                    this.memeriksaReferal = false;
                },

                lepasReferal() {
                    this.kodeReferal    = '';
                    this.referalStatus  = 'kosong';
                    this.referalPesan   = '';
                    this.pemilikReferal = '';
                    this.diskonReferal  = 0;
                },

                {{-- Semua langkah harus beres, mulai dari kontak --}}
                canCheckout() {
                    return this.siapPesan;
                },

                selectAddress(id, label, recipient, phone, fullAddress, lat = null, lng = null, city = null, postal = null) {
                    this.selectedAddressId = id;
                    this.selectedAddressLabel = label;
                    this.selectedAddressText = recipient + ' (' + phone + ') - ' + fullAddress;
                    this.showAddressModal = false;

                    if (lat && lng) {
                        this.newAddrLat = lat;
                        this.newAddrLng = lng;
                    }
                    if (city) this.newAddrCity = city;
                    if (postal) this.newAddrPostal = postal;

                    this.recalculateShippingCost(lat, lng, city, postal);
                },

                selectNewAddressOption() {
                    this.selectedAddressId = '';
                    this.selectedAddressLabel = 'Alamat Baru';
                    this.selectedAddressText = 'Lengkapi formulir alamat baru di bawah ini.';
                    this.showAddressModal = false;
                    this.initMapIfNeeded();
                }
            };
        }
    </script>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="checkoutData()">

        {{-- Form kontak berdiri sendiri di luar form checkout — HTML tidak --}}
        <form id="form-kontak" action="{{ route('checkout.kontak') }}" method="POST" class="hidden">
            @csrf
        </form>

        {{-- Masuk ke akun tanpa meninggalkan checkout. Alasannya sama seperti
             di atas: form tidak boleh bersarang, jadi berdiri sendiri di sini. --}}
        @unless($sudahLogin)
            <form id="form-masuk" action="{{ route('checkout.masuk') }}" method="POST" class="hidden">
                @csrf
            </form>
        @endunless

        {{-- Pengingat saat langkah yang masih terkunci ditekan --}}
        <div x-show="pesanKunci" x-cloak x-transition class="pengingat-kunci">
            <i class="fa-solid fa-lock"></i>
            <span x-text="pesanKunci"></span>
        </div>

        <form action="{{ route('checkout.store') }}" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            @csrf
            
            
            <input type="hidden" name="address_id" :value="selectedAddressId">
            <input type="hidden" name="courier_code" :value="selectedCourier">
            <input type="hidden" name="courier_cost" :value="getCourierCost()">
            <input type="hidden" name="payment_method" :value="selectedPayment">
            <input type="hidden" name="new_address[latitude]" :value="newAddrLat">
            <input type="hidden" name="new_address[longitude]" :value="newAddrLng">

            
            <div class="lg:col-span-8 space-y-4">
                
                <!-- 1. KONTAK CARD -->
                <div class="bg-white border rounded-2xl shadow-sm p-6 {{ $kontakLengkap ? 'border-gray-100' : 'border-accent/40' }}"
                     data-track-section="checkout_contact" data-track-label="Checkout - Kontak">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide">
                            <span class="langkah-nomor {{ $kontakLengkap ? 'langkah-selesai' : 'langkah-aktif' }}">
                                {!! $kontakLengkap ? '<i class="fa-solid fa-check"></i>' : '1' !!}
                            </span>
                            Kontak
                        </h3>
                        @if($kontakLengkap)
                            <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wide">Lengkap</span>
                        @endif
                    </div>

                    @if($kontakLengkap)
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-full bg-gray-50 flex items-center justify-center border border-gray-100 shrink-0">
                                <i class="fa-regular fa-user text-gray-500 text-sm"></i>
                            </div>
                            <div class="flex-grow">
                                <h4 class="text-xs font-bold text-gray-800">Informasi akun</h4>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    {{ Auth::user()->name }} ({{ Auth::user()->phone }}) - {{ Auth::user()->email }}
                                </p>
                            </div>
                            <button type="button" @click="showKontakForm = !showKontakForm"
                                    class="text-xs font-bold text-gray-500 hover:text-primary transition shrink-0">
                                Ubah
                            </button>
                        </div>
                    @else
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <p class="text-[11px] text-accent font-semibold">
                                <i class="fa-solid fa-circle-exclamation mr-1"></i>
                                @if($sudahLogin)
                                    Isi data kontak dulu. Alamat pengiriman, kurir, dan pembayaran
                                    baru bisa dipilih setelah bagian ini lengkap.
                                @else
                                    Isi data diri untuk melanjutkan. Akun otomatis dibuat agar kamu
                                    bisa memantau pesanan — tidak perlu mendaftar terpisah.
                                @endif
                            </p>

                            @unless($sudahLogin)
                                <button type="button" @click="bukaMasuk = !bukaMasuk"
                                        class="text-[11px] font-bold text-primary hover:underline whitespace-nowrap shrink-0">
                                    <span x-show="!bukaMasuk">Sudah punya akun?</span>
                                    <span x-show="bukaMasuk" x-cloak>Buat akun baru saja</span>
                                </button>
                            @endunless
                        </div>

                        {{-- ══════ Masuk di tempat ══════ --}}
                        @unless($sudahLogin)
                            <div x-show="bukaMasuk" x-cloak x-transition
                                 class="mb-4 p-4 rounded-xl bg-gray-50 border border-gray-100">

                                <p class="text-[11px] text-gray-500 mb-3">
                                    Masuk di sini saja — kamu tidak akan kehilangan halaman ini,
                                    dan barang di keranjang tetap aman.
                                </p>

                                @error('checkout_email', 'masuk')
                                    <p class="text-[11px] text-red-600 mb-2 font-semibold">
                                        <i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $message }}
                                    </p>
                                @enderror

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Email</label>
                                        <input type="email" form="form-masuk" name="email" required
                                               value="{{ old('email') }}"
                                               class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                               placeholder="nama@email.com">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Kata Sandi</label>
                                        <input type="password" form="form-masuk" name="password" required
                                               class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                               placeholder="Kata sandi akunmu">
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-3 mt-3">
                                    <a href="{{ route('password.request') }}"
                                       class="text-[10px] text-gray-400 hover:text-primary hover:underline">
                                        Lupa kata sandi?
                                    </a>
                                    <button type="submit" form="form-masuk"
                                            class="bg-primary hover:bg-primary/90 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition">
                                        Masuk &amp; Lanjutkan
                                    </button>
                                </div>
                            </div>
                        @endunless
                    @endif

                    {{-- Form kontak: tampil bila belum lengkap, atau saat tombol Ubah ditekan.
                         Ditempatkan di luar form utama checkout (lihat catatan di bawah). --}}
                    <div x-show="showKontakForm" x-cloak class="{{ $kontakLengkap ? 'mt-5 pt-5 border-t border-gray-50' : '' }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Nama Lengkap *</label>
                                <input type="text" form="form-kontak" name="name" required maxlength="255"
                                       value="{{ old('name', $namaAkun) }}"
                                       class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                       placeholder="Nama sesuai penerima paket">
                                @error('name') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Nomor HP / WhatsApp *</label>
                                <input type="tel" form="form-kontak" name="phone" required maxlength="20"
                                       value="{{ old('phone', $hpAkun) }}"
                                       class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                       placeholder="08xxxxxxxxxx">
                                @error('phone') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            @unless($sudahLogin)
                                {{-- Tamu sekaligus membuat akun di langkah ini --}}
                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Email *</label>
                                    <input type="email" form="form-kontak" name="email" required maxlength="255"
                                           value="{{ old('email') }}"
                                           class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                           placeholder="nama@email.com">
                                    @error('email') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                                    <p class="text-[10px] text-gray-400 mt-1">Dipakai untuk masuk dan menerima info pesanan.</p>
                                </div>

                                <div>
                                    <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Kata Sandi *</label>
                                    <input type="password" form="form-kontak" name="password" required
                                           class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                           placeholder="Minimal 8 karakter">
                                    @error('password') <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase block mb-1.5">Ulangi Kata Sandi *</label>
                                    <input type="password" form="form-kontak" name="password_confirmation" required
                                           class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-primary focus:border-primary"
                                           placeholder="Ketik ulang kata sandi">
                                </div>
                            @endunless
                        </div>

                        <button type="submit" form="form-kontak"
                                class="mt-3 bg-primary hover:bg-primary-light text-white text-xs font-bold px-6 py-2.5 rounded-xl transition">
                            {{ $sudahLogin ? 'Simpan Kontak' : 'Simpan & Lanjutkan' }}
                        </button>
                    </div>
                </div>

                <!-- 2. PENGIRIMAN & PEMBAYARAN CARD -->
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-5 relative"
                     :class="!kontakLengkap && 'kartu-terkunci'"
                     data-track-section="checkout_shipping" data-track-label="Checkout - Pengiriman &amp; Pembayaran">

                    <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide border-b border-gray-50 pb-3 flex items-center gap-2">
                        <span class="langkah-nomor" :class="kontakLengkap ? 'langkah-aktif' : 'langkah-mati'">2</span>
                        Pengiriman &amp; Pembayaran
                    </h3>

                    {{-- Tirai penutup saat kontak belum lengkap --}}
                    <div x-show="!kontakLengkap" x-cloak class="tirai-kunci">
                        <div class="tirai-isi">
                            <i class="fa-solid fa-lock"></i>
                            <p>Lengkapi <strong>Kontak</strong> terlebih dahulu</p>
                        </div>
                    </div>

                    <!-- Alamat Pengiriman row -->
                    <div @click="bukaLangkah('alamat')"
                         class="flex items-center justify-between gap-4 py-3 rounded-xl px-2 -mx-2 transition"
                         :class="kontakLengkap ? 'hover:bg-slate-50/50 cursor-pointer' : 'opacity-50 cursor-not-allowed'">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-full bg-gray-50 flex items-center justify-center border border-gray-100 shrink-0">
                                <i class="fa-solid fa-location-dot text-gray-500 text-sm"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-800">Alamat Pengiriman</h4>
                                <template x-if="selectedAddressId === '' && !newAddrName">
                                    <p class="text-[10px] text-red-600 font-semibold mt-0.5">Masukkan alamat pengiriman*</p>
                                </template>
                                <template x-if="selectedAddressId !== ''">
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        <span class="bg-primary/10 text-primary text-[8px] font-bold px-1.5 py-0.5 rounded mr-1" x-text="selectedAddressLabel"></span>
                                        <span x-text="selectedAddressText"></span>
                                    </div>
                                </template>
                                <template x-if="selectedAddressId === '' && newAddrName">
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        <span class="bg-accent/10 text-accent text-[8px] font-bold px-1.5 py-0.5 rounded mr-1">Alamat Baru</span>
                                        <span x-text="newAddrName + ' (' + newAddrPhone + ') - ' + newAddrLine + ', ' + newAddrCity"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-gray-400 text-xs shrink-0"></i>
                    </div>

                    <div class="border border-gray-100 p-5 rounded-2xl bg-gray-50/50 space-y-4 text-xs" x-show="selectedAddressId === ''" x-transition
                        x-effect="if (selectedAddressId === '') initMapIfNeeded()">
                        <h4 class="font-black text-gray-800 uppercase text-[10px] tracking-wide border-b border-gray-100 pb-2">Lengkapi Alamat Baru</h4>

                        <!-- Tempel Alamat Otomatis -->
                        <div class="bg-primary/5 border border-primary/10 rounded-2xl p-4 space-y-2.5">
                            <label class="text-gray-500 font-semibold block flex items-center gap-1.5">
                                <i class="fa-solid fa-paste text-primary text-[11px]"></i>
                                Tempel Alamat Lengkap (Otomatis Terisi)
                            </label>
                            <textarea x-model="pasteAddressText" rows="2"
                                placeholder="Contoh: Jl. Kyai Tambak Deres No.30, Kedung Cowek, Kec. Bulak, Surabaya, Jawa Timur 60129"
                                class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-primary bg-white"></textarea>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="searchPastedAddress()" :disabled="searchingAddress || !pasteAddressText.trim()"
                                    class="text-white bg-primary hover:bg-primary-light disabled:opacity-50 disabled:cursor-not-allowed font-bold px-4 py-2 rounded-xl transition flex items-center gap-2 shrink-0">
                                    <i class="fa-solid fa-magnifying-glass-location text-[10px]"></i>
                                    <span x-text="searchingAddress ? 'Mencari...' : 'Cari & Isi Otomatis'"></span>
                                </button>
                                <p class="text-[10px] text-red-500" x-show="addressSearchError" x-text="addressSearchError"></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-gray-400 font-semibold block mb-1">Label Alamat</label>
                                <input type="text" name="new_address[label]" x-model="newAddrLabel" placeholder="Contoh: Rumah, Kantor" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white">
                            </div>
                            <div>
                                <label class="text-gray-400 font-semibold block mb-1">Nama Penerima</label>
                                <input type="text" name="new_address[recipient_name]" x-model="newAddrName" placeholder="Nama Lengkap" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-gray-400 font-semibold block mb-1">Nomor Telepon Penerima</label>
                                <input type="text" name="new_address[phone]" x-model="newAddrPhone" placeholder="Contoh: 081234567890" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-gray-400 font-semibold block mb-1">Alamat Lengkap</label>
                                <textarea name="new_address[address_line]" x-model="newAddrLine" rows="3" placeholder="Nama jalan, nomor rumah, RT/RW, gedung, dsb." class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white"></textarea>
                            </div>
                            <div>
                                <label class="text-gray-400 font-semibold block mb-1">Provinsi *</label>
                                <select x-model="selectedProvinceId" @change="onProvinceChange()" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white">
                                    <option value="">-- Pilih Provinsi --</option>
                                    <template x-for="p in provinces" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="new_address[province]" :value="newAddrProvince">
                            </div>

                            <div>
                                <label class="text-gray-400 font-semibold block mb-1">Kota / Kabupaten *</label>
                                <select x-model="selectedCityId" @change="onCityChange()" :disabled="!selectedProvinceId || loadingCities" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <option value="" x-text="loadingCities ? 'Memuat Kota...' : '-- Pilih Kota/Kabupaten --'"></option>
                                    <template x-for="c in cities" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="new_address[city]" :value="newAddrCity">
                            </div>

                            <div>
                                <label class="text-gray-400 font-semibold block mb-1">Kecamatan *</label>
                                <select x-model="selectedDistrictId" @change="onDistrictChange()" :disabled="!selectedCityId || loadingDistricts" class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <option value="" x-text="loadingDistricts ? 'Memuat Kecamatan...' : '-- Pilih Kecamatan --'"></option>
                                    <template x-for="d in districts" :key="d.id">
                                        <option :value="d.id" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-gray-400 font-semibold block">Kode Pos *</label>
                                    <template x-if="!memuatKodePos && opsiKodePos.length > 0 && !isiKodePosSendiri">
                                        <button type="button" @click="mulaiIsiSendiri()" class="text-[11px] text-primary hover:underline font-bold flex items-center gap-1">
                                            <i class="fa-solid fa-pen-to-square text-[10px]"></i> Ketik Manual
                                        </button>
                                    </template>
                                    <template x-if="!memuatKodePos && opsiKodePos.length > 0 && isiKodePosSendiri">
                                        <button type="button" @click="kembaliKeDaftar()" class="text-[11px] text-gray-500 hover:underline font-bold flex items-center gap-1">
                                            <i class="fa-solid fa-list-check text-[10px]"></i> Pilih dari Daftar
                                        </button>
                                    </template>
                                </div>

                                {{-- Nilai yang benar-benar dikirim --}}
                                <input type="hidden" name="new_address[postal_code]" :value="newAddrPostal">

                                {{-- Sedang mencari kode pos --}}
                                <div x-show="memuatKodePos" x-cloak
                                     class="w-full border border-gray-200 rounded-xl py-2 px-3 bg-gray-50 text-gray-400 flex items-center gap-2">
                                    <i class="fa-solid fa-circle-notch fa-spin text-[11px]"></i>
                                    <span>Mencari kode pos…</span>
                                </div>

                                {{-- Ada pilihan dari data kurir: pembeli tinggal memilih --}}
                                <select x-show="!memuatKodePos && opsiKodePos.length > 0 && !isiKodePosSendiri" x-cloak
                                        x-model="pilihanKodePos" @change="pilihKodePos()"
                                        class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white">
                                    <option value="">-- Pilih Kode Pos --</option>
                                    <template x-for="opsi in opsiKodePos" :key="opsi.kode">
                                        <option :value="opsi.kode" x-text="opsi.label"></option>
                                    </template>
                                    <option value="__manual__">✏️ Ketik Kode Pos Sendiri...</option>
                                </select>

                                {{-- Mode Diketik Sendiri (jika opsi kosong atau klik ketik manual) --}}
                                <input type="text" x-show="!memuatKodePos && (opsiKodePos.length === 0 || isiKodePosSendiri)" x-cloak
                                       x-model="newAddrPostal"
                                       x-ref="kodePosManual"
                                       @input="kodePosDiketikSendiri = true"
                                       inputmode="numeric" maxlength="5"
                                       placeholder="Ketik 5 digit kode pos Anda (cth: 60129)"
                                       class="w-full border border-gray-200 rounded-xl py-2 px-3 focus:outline-none focus:ring-1 focus:ring-orange-500 bg-white">

                                <p class="text-[10px] text-gray-400 mt-1" x-show="!memuatKodePos" x-cloak
                                   x-text="isiKodePosSendiri
                                        ? 'Silakan ketik 5 digit kode pos sesuai lokasi Anda.'
                                        : (opsiKodePos.length > 1
                                            ? 'Kecamatan ini punya ' + opsiKodePos.length + ' kode pos. Pilih yang sesuai atau klik Ketik Manual.'
                                            : (opsiKodePos.length === 1
                                                ? 'Terisi otomatis. Klik Ketik Manual jika ingin mengganti.'
                                                : 'Pilih kecamatan dulu, atau langsung ketik 5 digit kode posmu.'))"></p>
                            </div>

                            <!-- Peta Titik Lokasi Pengiriman Presisi -->
                            <div class="sm:col-span-2 space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-gray-700 font-bold text-xs flex items-center gap-1.5">
                                        <i class="fa-solid fa-map-location-dot text-orange-500"></i>
                                        <span>Titik Lokasi Presisi Pengiriman</span>
                                    </label>
                                    <button type="button" @click="useMyLocation()"
                                        class="bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200 font-bold px-2.5 py-1 rounded-lg transition flex items-center gap-1 text-[11px] shrink-0">
                                        <i class="fa-solid fa-location-crosshairs text-xs"></i>
                                        <span>Gunakan Lokasi Saya (GPS)</span>
                                    </button>
                                </div>

                                <div class="relative">
                                    {{-- Tinggi peta ditulis di CSS sendiri, bukan kelas Tailwind. --}}
                                    <div x-ref="mapContainer" class="peta-lokasi"></div>

                                    {{-- Toast Notifikasi Koordinat Diperbarui --}}
                                    <div x-show="coordNotice" x-transition.opacity
                                         class="absolute bottom-3 left-3 right-3 bg-slate-900/90 text-white text-[11px] px-3 py-2 rounded-lg shadow-lg backdrop-blur-sm flex items-center justify-between z-10">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
                                            <span>Titik koordinat berhasil disesuaikan di peta.</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-[11px] text-gray-500 font-medium">
                                    <span>💡 Geser pin di peta untuk menentukan lokasi persis pengiriman (alamat teks Anda tidak akan berubah).</span>
                                    <span class="font-mono text-[10px] text-gray-400" x-text="'GPS: ' + newAddrLat.toFixed(4) + ', ' + newAddrLng.toFixed(4)"></span>
                                </div>

                                <input type="hidden" name="new_address[latitude]" :value="newAddrLat">
                                <input type="hidden" name="new_address[longitude]" :value="newAddrLng">
                            </div>
                        </div>
                    </div>

                    <!-- Pilih Kurir row: terkunci sampai alamat terisi -->
                    <div @click="bukaLangkah('kurir')"
                        class="flex items-center justify-between gap-4 py-3 rounded-xl px-2 -mx-2 transition"
                        :class="alamatLengkap ? 'hover:bg-slate-50/50 cursor-pointer' : 'opacity-50 cursor-not-allowed'">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-full bg-gray-50 flex items-center justify-center border border-gray-100 shrink-0">
                                <i class="fa-solid text-gray-500 text-sm" :class="alamatLengkap ? 'fa-truck' : 'fa-lock'"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-800">Pilih Kurir</h4>
                                <template x-if="!alamatLengkap">
                                    <p class="text-[10px] text-gray-400 font-semibold mt-0.5">Isi alamat pengiriman dulu</p>
                                </template>
                                <template x-if="alamatLengkap && selectedCourier === ''">
                                    <p class="text-[10px] text-red-600 font-semibold mt-0.5">Pilih kurir pengiriman *</p>
                                </template>
                                <template x-if="selectedCourier !== ''">
                                    <p class="text-[11px] text-gray-500 mt-0.5">
                                        <span class="font-bold text-primary" x-text="getCourierName()"></span>
                                        <span class="ml-1" x-text="'(' + formatPrice(getCourierCost()) + ')'"></span>
                                    </p>
                                </template>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-gray-400 text-xs shrink-0"></i>
                    </div>

                    <!-- Metode Pembayaran row: terkunci sampai kurir dipilih -->
                    <div @click="bukaLangkah('bayar')"
                        class="flex items-center justify-between gap-4 py-3 rounded-xl px-2 -mx-2 transition"
                        :class="kurirTerpilih ? 'hover:bg-slate-50/50 cursor-pointer' : 'opacity-50 cursor-not-allowed'">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 rounded-full bg-gray-50 flex items-center justify-center border border-gray-100 shrink-0">
                                <i class="text-gray-500 text-sm" :class="kurirTerpilih ? 'fa-regular fa-credit-card' : 'fa-solid fa-lock'"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-800">Metode Pembayaran</h4>
                                <template x-if="!kurirTerpilih">
                                    <p class="text-[10px] text-gray-400 font-semibold mt-0.5">Pilih kurir dulu</p>
                                </template>
                                <template x-if="kurirTerpilih && selectedPayment === ''">
                                    <p class="text-[10px] text-red-600 font-semibold mt-0.5">Silahkan masukkan metode pembayaran *</p>
                                </template>
                                <template x-if="selectedPayment !== ''">
                                    <p class="text-[11px] text-gray-500 mt-0.5 font-bold text-primary" x-text="getPaymentName()"></p>
                                </template>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-gray-400 text-xs shrink-0"></i>
                    </div>
                </div>

                <!-- 3. KODE REFERAL — boleh dikosongkan -->
                <div class="kartu-referal">
                    <div class="referal-kepala">
                        <div class="referal-ikon"><i class="fa-solid fa-ticket"></i></div>
                        <div class="min-w-0">
                            <h4 class="referal-judul">Punya Kode Referal?</h4>
                            <p class="referal-sub">
                                Masukkan kode temanmu dan dapat potongan
                                {{ (int) config('referal.persen_diskon', 3) }}% dari harga barang.
                                Boleh dikosongkan.
                            </p>
                        </div>
                    </div>

                    <div class="referal-baris">
                        <input type="text" x-model="kodeReferal"
                               @input="kodeReferal = kodeReferal.toUpperCase(); referalStatus = 'kosong'"
                               @keydown.enter.prevent="periksaReferal()"
                               placeholder="RECORD-NAMATEMAN"
                               autocomplete="off" maxlength="60"
                               class="referal-isian"
                               :class="referalStatus === 'salah' ? 'referal-isian-salah'
                                     : (referalStatus === 'benar' ? 'referal-isian-benar' : '')">

                        <button type="button" @click="periksaReferal()"
                                :disabled="!kodeReferal.trim() || memeriksaReferal"
                                class="referal-tombol">
                            <span x-show="!memeriksaReferal">Pakai</span>
                            <span x-show="memeriksaReferal" x-cloak>
                                <i class="fa-solid fa-circle-notch fa-spin"></i>
                            </span>
                        </button>
                    </div>

                    {{-- Nilai yang ikut terkirim bersama pesanan --}}
                    <input type="hidden" name="referral_code" :value="referalStatus === 'benar' ? kodeReferal : ''">

                    <p class="referal-galat" x-show="referalStatus === 'salah'" x-cloak x-text="referalPesan"></p>

                    <div class="referal-berhasil" x-show="referalStatus === 'benar'" x-cloak>
                        <i class="fa-solid fa-circle-check"></i>
                        <span>
                            Kode dipakai. Kamu hemat
                            <strong x-text="formatPrice(diskonReferal)"></strong>,
                            dan <strong x-text="pemilikReferal"></strong> dapat komisi yang sama.
                        </span>
                        <button type="button" @click="lepasReferal()" class="referal-lepas">Lepas</button>
                    </div>
                </div>

                <!-- 4. KERANJANG CARD -->
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6"
                     data-track-section="checkout_cart" data-track-label="Checkout - Review Keranjang">
                    <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide mb-5">Keranjang ({{ $cartItems->count() }})</h3>
                    <div class="divide-y divide-gray-100">
                        @foreach($cartItems as $item)
                            <div class="py-4 flex gap-4 items-center first:pt-0 last:pb-0">
                                <div class="h-16 w-16 bg-gray-50 border border-gray-100 rounded-xl flex-shrink-0 flex items-center justify-center p-2">
                                    <img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}" class="object-contain max-h-full"
                                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100&q=80';">
                                </div>
                                <div class="min-w-0 flex-grow text-xs">
                                    <h4 class="font-bold text-gray-800 truncate">{{ $item->product->name }}</h4>
                                    @if($item->productVariant)
                                        <p class="text-[10px] text-gray-400 font-semibold mt-0.5">
                                            Color: {{ $item->productVariant->color }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 font-semibold mt-0.5">
                                            Size: {{ $item->productVariant->size }}
                                        </p>
                                    @endif
                                    <p class="text-[11px] text-gray-800 font-bold mt-1.5 flex items-center gap-2">
                                        <span>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</span>
                                        @if($item->product->hasDiscount())
                                            <span class="text-[9px] text-gray-400 line-through">Rp {{ number_format($item->product->base_price, 0, ',', '.') }}</span>
                                        @endif
                                    </p>
                                </div>
                                {{-- Jumlah bisa diubah di sini, tanpa harus balik --}}
                                @php
                                    $stokItem = $item->productVariant
                                        ? (int) $item->productVariant->stock
                                        : (int) $item->product->stock;
                                @endphp
                                <div class="flex items-center gap-1.5 shrink-0"
                                     :class="jumlahSibuk === {{ $item->id }} && 'opacity-50 pointer-events-none'">
                                    <button type="button"
                                            @click="ubahJumlah({{ $item->id }}, jumlahItem[{{ $item->id }}] - 1, {{ $stokItem }})"
                                            :disabled="jumlahItem[{{ $item->id }}] <= 1"
                                            class="w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold transition">
                                        &minus;
                                    </button>

                                    <span class="w-9 text-center text-xs font-bold text-gray-800"
                                          x-text="jumlahItem[{{ $item->id }}]">{{ $item->quantity }}</span>

                                    <button type="button"
                                            @click="ubahJumlah({{ $item->id }}, jumlahItem[{{ $item->id }}] + 1, {{ $stokItem }})"
                                            :disabled="jumlahItem[{{ $item->id }}] >= {{ $stokItem }}"
                                            class="w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-bold transition">
                                        +
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Sidebar Summary (lg:col-span-4) -->
            <div class="lg:col-span-4 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm space-y-6"
                 data-track-section="checkout_summary" data-track-label="Checkout - Ringkasan Pembayaran">
                <h3 class="text-sm font-black text-gray-800 uppercase tracking-wide border-b border-gray-50 pb-3">Total Pembayaran</h3>
                
                <div class="space-y-3.5 text-xs">
                    <div class="flex justify-between font-semibold text-gray-400">
                        <span>Sub total</span>
                        <span x-text="'Rp ' + formatAngka(subtotalBarang)">{{ 'Rp ' . number_format($cart->total_terpilih, 0, ',', '.') }}</span>
                    </div>
                    {{-- Potongan referal hanya muncul kalau kodenya memang terpakai --}}
                    <div class="flex justify-between font-semibold text-emerald-600"
                         x-show="referalStatus === 'benar' && diskonReferal > 0" x-cloak>
                        <span>
                            Diskon referal
                            <span class="text-[10px] text-emerald-500" x-text="'(' + kodeReferal + ')'"></span>
                        </span>
                        <span x-text="'− ' + formatPrice(diskonReferal)"></span>
                    </div>

                    <div class="flex justify-between font-semibold text-gray-400">
                        <span>Ongkos Kirim</span>
                        <span x-text="formatPrice(getCourierCost())"></span>
                    </div>
                    <div class="border-t border-gray-100 pt-4 flex justify-between font-black text-sm text-gray-800 uppercase">
                        <span>Total</span>
                        <span class="text-accent text-base" x-text="formatPrice(getTotal())"></span>
                    </div>
                </div>

                {{-- Sisa langkah yang belum beres --}}
                <div class="pt-1" x-show="!siapPesan" x-cloak>
                    <p class="sisa-langkah">
                        <i class="fa-solid fa-circle-info"></i>
                        <span x-show="!kontakLengkap">Lengkapi data kontak untuk melanjutkan.</span>
                        <span x-show="kontakLengkap && !alamatLengkap">Isi alamat pengiriman untuk melanjutkan.</span>
                        <span x-show="alamatLengkap && !kurirTerpilih">Pilih kurir pengiriman untuk melanjutkan.</span>
                        <span x-show="kurirTerpilih && !bayarTerpilih">Pilih metode pembayaran untuk melanjutkan.</span>
                    </p>
                </div>

                <div class="pt-2">
                    <button type="submit"
                        :disabled="!canCheckout()"
                        :class="canCheckout() ? 'bg-accent hover:bg-accent-light text-white shadow-md' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                        class="w-full text-xs font-bold py-3.5 rounded-xl transition uppercase tracking-wider flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span>Buat Pesanan Sekarang</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- ================= ADDRESS SELECTION MODAL ================= -->
        <div x-show="showAddressModal" 
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4"
            x-transition
            style="display: none;">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-6 relative" @click.away="showAddressModal = false">
                <div class="flex justify-between items-center border-b border-gray-50 pb-3">
                    <h3 class="font-black text-gray-800 text-sm uppercase">Pilih Alamat Pengiriman</h3>
                    <button @click="showAddressModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <!-- Addresses List -->
                <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                    @forelse($addresses as $addr)
                        <div @click="selectAddress({{ $addr->id }}, '{{ addslashes($addr->label) }}', '{{ addslashes($addr->recipient_name) }}', '{{ addslashes($addr->phone) }}', '{{ addslashes($addr->full_address) }}', {{ (float)($addr->latitude ?: -7.2275) }}, {{ (float)($addr->longitude ?: 112.7865) }}, '{{ addslashes($addr->city) }}', '{{ addslashes($addr->postal_code) }}')"
                            class="border border-gray-100 hover:border-primary hover:bg-primary/5 p-4 rounded-2xl cursor-pointer transition flex items-start gap-3"
                            :class="selectedAddressId == '{{ $addr->id }}' ? 'border-primary bg-primary/5' : ''">
                            <div class="text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-primary uppercase text-[9px] bg-primary/10 px-1.5 py-0.5 rounded" x-text="'{{ $addr->label }}'"></span>
                                    @if($addr->is_default)
                                        <span class="bg-gray-100 text-gray-600 text-[8px] px-1.5 py-0.5 rounded font-bold uppercase">Utama</span>
                                    @endif
                                </div>
                                <p class="font-bold text-gray-800 mt-2">{{ $addr->recipient_name }} ({{ $addr->phone }})</p>
                                <p class="text-gray-500 mt-0.5 leading-relaxed">{{ $addr->full_address }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-6">Belum ada alamat tersimpan.</p>
                    @endforelse
                </div>

                <!-- Toggle Form Alamat Baru Option -->
                <div class="pt-1 border-t border-gray-50">
                    <button type="button" @click="selectNewAddressOption()" 
                        class="w-full border border-dashed border-gray-200 hover:border-accent hover:bg-red-50/20 text-xs font-bold text-accent py-3 rounded-xl transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>Tambah Alamat Baru</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= COURIER SELECTION MODAL ================= -->
        <div x-show="showCourierModal" 
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4"
            x-transition
            style="display: none;">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-6 relative" @click.away="showCourierModal = false">
                <div class="flex justify-between items-center border-b border-gray-50 pb-3">
                    <h3 class="font-black text-gray-800 text-sm uppercase">Pilih Kurir Pengiriman</h3>
                    <button @click="showCourierModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                {{-- Pengiriman instan hanya muncul untuk tujuan di sekitar toko,
                     jadi bagian ini ikut hilang sendiri bagi pembeli luar kota. --}}
                <template x-if="kurirInstan.length > 0">
                    <div class="space-y-3">
                        <div class="judul-kurir">
                            <i class="fa-solid fa-bolt"></i>
                            Pengiriman Instan
                            <span class="judul-kurir-ket">Diantar hari ini juga</span>
                        </div>

                        <template x-for="item in kurirInstan" :key="item.code">
                            <div @click="selectedCourier = item.code; showCourierModal = false"
                                class="kartu-kurir kartu-kurir-instan"
                                :class="selectedCourier === item.code ? 'kartu-kurir-aktif' : ''">
                                <div class="text-xs">
                                    <p class="font-bold text-gray-800 flex items-center gap-1.5">
                                        <span x-text="item.name"></span>
                                        <span class="lencana-instan">INSTAN</span>
                                    </p>
                                    <p class="text-gray-400 mt-0.5">Estimasi: <span x-text="item.etd"></span></p>

                                    {{-- Keterangan jadwal jemput untuk pesanan --}}
                                    <template x-if="item.catatan">
                                        <p class="mt-1 text-[10px] leading-snug text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                            <i class="fa-solid fa-clock mr-0.5"></i>
                                            <span x-text="item.catatan"></span>
                                        </p>
                                    </template>
                                </div>
                                <div class="text-xs font-bold text-primary" x-text="formatPrice(item.cost)"></div>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-if="kurirInstan.length > 0">
                        <div class="judul-kurir">
                            <i class="fa-solid fa-truck"></i>
                            Pengiriman Reguler
                        </div>
                    </template>

                    <template x-for="item in kurirReguler" :key="item.code">
                        <div @click="selectedCourier = item.code; showCourierModal = false"
                            class="kartu-kurir"
                            :class="selectedCourier === item.code ? 'kartu-kurir-aktif' : ''">
                            <div class="text-xs">
                                <p class="font-bold text-gray-800" x-text="item.name"></p>
                                <p class="text-gray-400 mt-0.5">Estimasi: <span x-text="item.etd"></span></p>

                                    {{-- Keterangan jadwal jemput untuk pesanan --}}
                                    <template x-if="item.catatan">
                                        <p class="mt-1 text-[10px] leading-snug text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                            <i class="fa-solid fa-clock mr-0.5"></i>
                                            <span x-text="item.catatan"></span>
                                        </p>
                                    </template>
                            </div>
                            <div class="text-xs font-bold text-primary" x-text="formatPrice(item.cost)"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ================= PAYMENT SELECTION MODAL ================= -->
        <div x-show="showPaymentModal" 
            class="fixed inset-0 z-50 overflow-hidden bg-black/60 backdrop-blur-xs flex items-end sm:items-center justify-center p-0 sm:p-4"
            x-transition
            style="display: none;">
            <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full shadow-2xl relative flex flex-col max-h-[85vh] sm:max-h-[82vh]"
                 @click.away="showPaymentModal = false">
                
                {{-- Header Modal: Selalu terlihat di paling atas, tidak pernah terpotong --}}
                <div class="flex justify-between items-center px-5 py-4 sm:px-6 sm:py-4 border-b border-gray-100 shrink-0 bg-white rounded-t-3xl z-10">
                    <div>
                        <h3 class="font-black text-gray-900 text-sm sm:text-base uppercase tracking-wide">Metode Pembayaran</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">Pilih metode pembayaran yang Anda inginkan</p>
                    </div>
                    <button type="button" @click="showPaymentModal = false" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 hover:text-gray-700 flex items-center justify-center transition cursor-pointer">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                {{-- Daftar Pembayaran: Pilihan teratas (Transfer BCA) langsung terlihat jelas --}}
                <div class="overflow-y-auto p-4 sm:p-6 space-y-3 overscroll-contain flex-1">
                    <template x-for="item in payments" :key="item.code">
                        <div @click="if (item.code === 'MANUAL_BCA') { selectedPayment = item.code; showPaymentModal = false; }"
                            class="border p-4 rounded-2xl transition flex items-center gap-3"
                            :class="{
                                'border-primary bg-primary/5 ring-1 ring-primary': selectedPayment === item.code,
                                'border-gray-200 hover:border-primary hover:bg-primary/5 cursor-pointer':
                                    selectedPayment !== item.code && item.code === 'MANUAL_BCA',
                                'border-gray-200 bg-gray-50/70 opacity-60 cursor-not-allowed': item.code !== 'MANUAL_BCA'
                            }">
                            <div class="h-9 w-9 rounded-full bg-gray-50 flex items-center justify-center border border-gray-100 shrink-0"
                                 :class="item.code === 'MANUAL_BCA' ? 'text-primary' : 'text-gray-400'">
                                <i :class="item.icon" class="text-xs"></i>
                            </div>
                            <div class="text-xs flex-grow">
                                <p class="font-bold text-gray-800 flex items-center gap-1.5">
                                    <span x-text="item.name"></span>
                                    <template x-if="item.code !== 'MANUAL_BCA'">
                                        <span class="bg-amber-100 text-amber-800 text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider border border-amber-300">Maintenance</span>
                                    </template>
                                    <template x-if="item.code === 'MANUAL_BCA'">
                                        <span class="bg-emerald-100 text-emerald-800 text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider border border-emerald-300">Tersedia</span>
                                    </template>
                                </p>
                                <template x-if="item.code === 'MANUAL_BCA'">
                                    <p class="mt-0.5 text-gray-500 font-medium" x-text="item.type"></p>
                                </template>
                                <template x-if="item.code !== 'MANUAL_BCA'">
                                    <p class="mt-0.5 text-[11px] text-amber-700 font-semibold flex items-center gap-1">
                                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                        <span>Sementara tidak dapat dipilih (Sedang Maintenance)</span>
                                    </p>
                                </template>
                            </div>
                            <template x-if="item.code === 'MANUAL_BCA'">
                                <i class="fa-solid fa-chevron-right text-gray-300 text-xs shrink-0"></i>
                            </template>
                            <template x-if="item.code !== 'MANUAL_BCA'">
                                <i class="fa-solid fa-lock text-gray-400 text-xs shrink-0"></i>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>

    @push('styles')
        <style>
            [x-cloak] { display: none !important; }

            /* Nomor urut langkah */
            .langkah-nomor {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                margin-right: 7px;
                font-size: 10px;
                font-weight: 800;
                border-radius: 9999px;
                vertical-align: middle;
            }
            .langkah-aktif   { background: var(--color-primary, #1B3A6B); color: #fff; }
            .langkah-selesai { background: #10b981; color: #fff; }
            .langkah-mati    { background: #e5e7eb; color: #9ca3af; }

            /* Kartu yang masih terkunci */
            .kartu-terkunci { position: relative; }
            .tirai-kunci {
                position: absolute;
                inset: 0;
                z-index: 5;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgb(255 255 255 / 0.72);
                border-radius: 16px;
                /* Menahan klik agar isi di baliknya benar-benar tidak bisa ditekan */
                cursor: not-allowed;
            }
            .tirai-isi {
                text-align: center;
                color: #6b7280;
            }
            .tirai-isi i {
                display: block;
                font-size: 20px;
                color: #9ca3af;
                margin-bottom: 7px;
            }
            .tirai-isi p { font-size: 12px; font-weight: 600; }
            .tirai-isi strong { color: var(--color-primary, #1B3A6B); }

            /* Pengingat melayang saat langkah terkunci ditekan */
            .pengingat-kunci {
                position: fixed;
                left: 50%;
                bottom: 26px;
                transform: translateX(-50%);
                z-index: 60;
                display: inline-flex;
                align-items: center;
                gap: 9px;
                padding: 12px 20px;
                font-size: 12.5px;
                font-weight: 600;
                color: #fff;
                background: rgb(15 23 42 / 0.94);
                border-radius: 9999px;
                box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.3);
            }
            .pengingat-kunci i { color: #fbbf24; }

            /* Petunjuk langkah tersisa di bawah ringkasan */
            .sisa-langkah {
                display: flex;
                align-items: flex-start;
                gap: 7px;
                padding: 10px 12px;
                font-size: 11px;
                line-height: 1.6;
                color: #92400e;
                background: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 10px;
            }
            .sisa-langkah i { margin-top: 2px; color: #d97706; }

            /* ── Pilihan kurir ────────────────────────────────────────── Ditulis sebagai CSS sendiri agar tida... */
            .judul-kurir {
                display: flex;
                align-items: center;
                gap: 6px;
                font-size: 10px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #6b7280;
                padding-top: 2px;
            }
            .judul-kurir i { color: var(--color-primary, #1B3A6B); font-size: 11px; }
            .judul-kurir-ket {
                margin-left: auto;
                font-size: 9px;
                font-weight: 700;
                letter-spacing: 0;
                text-transform: none;
                color: #059669;
            }

            .kartu-kurir {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 16px;
                border: 1px solid #f3f4f6;
                border-radius: 16px;
                cursor: pointer;
                transition: border-color 180ms ease, background-color 180ms ease;
            }
            .kartu-kurir:hover {
                border-color: var(--color-primary, #1B3A6B);
                background: rgb(27 58 107 / 0.04);
            }
            .kartu-kurir-aktif {
                border-color: var(--color-primary, #1B3A6B);
                background: rgb(27 58 107 / 0.05);
            }

            /* Garis hijau tipis di tepi kiri: penanda cepat bahwa baris ini adalah layanan instan, terbaca bahk... */
            .kartu-kurir-instan {
                border-left: 3px solid #10b981;
            }

            /* ── Kartu kode referal ───────────────────────────────────── Ditulis sebagai CSS sendiri agar tida... */
            .kartu-referal {
                background: #eef7fe;
                border: 1px solid #dbeafe;
                border-radius: 16px;
                padding: 18px;
                box-shadow: 0 1px 2px rgb(0 0 0 / .04);
            }
            .referal-kepala { display: flex; align-items: flex-start; gap: 13px; }
            .referal-ikon {
                width: 40px; height: 40px; border-radius: 999px; flex-shrink: 0;
                background: #3b82f6; color: #fff;
                display: flex; align-items: center; justify-content: center;
                font-size: 14px; box-shadow: 0 1px 3px rgb(59 130 246 / .4);
            }
            .referal-judul { font-size: 12.5px; font-weight: 800; color: #1e3a8a; }
            .referal-sub { font-size: 10.5px; color: #1d4ed8; line-height: 1.6; margin-top: 3px; }

            .referal-baris { display: flex; gap: 8px; margin-top: 14px; }
            .referal-isian {
                flex: 1 1 auto; min-width: 0;
                border: 1px solid #bfdbfe; border-radius: 10px;
                padding: 10px 12px; font-size: 12px; font-weight: 700;
                letter-spacing: .03em; background: #fff; color: #1f2937;
                text-transform: uppercase;
            }
            .referal-isian::placeholder { font-weight: 500; letter-spacing: 0; color: #9ca3af; }
            .referal-isian:focus {
                outline: none; border-color: #2563eb;
                box-shadow: 0 0 0 3px rgb(37 99 235 / .12);
            }
            .referal-isian-salah { border-color: #fca5a5; background: #fef2f2; }
            .referal-isian-benar { border-color: #6ee7b7; background: #ecfdf5; }

            .referal-tombol {
                flex-shrink: 0; min-width: 74px;
                background: #2563eb; color: #fff;
                border-radius: 10px; padding: 10px 16px;
                font-size: 12px; font-weight: 800;
                transition: background-color 160ms ease;
            }
            .referal-tombol:hover:not(:disabled) { background: #1d4ed8; }
            .referal-tombol:disabled { background: #cbd5e1; cursor: not-allowed; }

            .referal-galat {
                margin-top: 9px; font-size: 11px; font-weight: 700; color: #dc2626;
            }

            .referal-berhasil {
                display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap;
                margin-top: 12px; padding: 11px 13px;
                background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px;
                font-size: 11px; color: #065f46; line-height: 1.6;
            }
            .referal-berhasil > i { margin-top: 2px; }
            .referal-lepas {
                margin-left: auto; font-size: 10.5px; font-weight: 800;
                color: #047857; text-decoration: underline;
            }

            /* ── Peta titik lokasi pengiriman ──────────────────────────── Ukurannya wajib eksplisit: Leaflet m... */
            .peta-lokasi {
                width: 100%;
                height: 280px;
                border-radius: 12px;
                border: 1px solid #d1d5db;
                overflow: hidden;
                position: relative;
                z-index: 0;
                background: #eef2f7;
                box-shadow: 0 1px 2px rgb(0 0 0 / .05);
            }
            @media (min-width: 640px) { .peta-lokasi { height: 340px; } }

            /* Pin dibuat lebih mudah disambar jari di layar sentuh. */
            .peta-lokasi .leaflet-marker-icon { cursor: grab; }
            .peta-lokasi .leaflet-marker-icon:active { cursor: grabbing; }

            .lencana-rpay {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 999px;
                background: #eff6ff;
                color: #1d4ed8;
                font-size: 8.5px;
                font-weight: 900;
                letter-spacing: 0.04em;
                white-space: nowrap;
            }

            .lencana-instan {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 999px;
                background: #ecfdf5;
                color: #047857;
                font-size: 8.5px;
                font-weight: 900;
                letter-spacing: 0.06em;
            }
        </style>
    @endpush

    {{-- Penyegar token CSRF. --}}
    <script>
        (function () {
            const JEDA_MS = 10 * 60 * 1000; // setiap 10 menit

            async function segarkanToken() {
                try {
                    const res = await fetch(@json(route('token.sesi')), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!res.ok) return;

                    const { token } = await res.json();
                    if (!token) return;

                    document.querySelectorAll('input[name="_token"]')
                        .forEach((input) => { input.value = token; });

                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = token;
                } catch (e) {
                    // Jaringan sedang bermasalah — cukup dicoba lagi pada jeda berikutnya.
                }
            }

            setInterval(segarkanToken, JEDA_MS);

            // Saat pembeli kembali ke tab ini setelah lama ditinggal.
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') segarkanToken();
            });
        })();
    </script>
</x-app-layout>
