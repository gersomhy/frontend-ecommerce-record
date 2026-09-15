<x-app-layout>
    <x-slot name="title">Keranjang Belanja</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="keranjang()">
        <!-- Page Title -->
        <div class="mb-8">
            <h1 class="text-2xl font-black text-primary uppercase tracking-wider">Keranjang Belanja</h1>
            <div class="h-1 w-20 bg-accent mt-2"></div>
        </div>

        @if($cartItems->isEmpty())
            <div class="bg-white border border-border p-12 text-center rounded-sm shadow-sm">
                <svg class="mx-auto h-16 w-16 text-gray-400 mb-4 animate-bounce" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="text-base font-bold text-text mb-2">Keranjang Belanja Anda Kosong</h3>
                <p class="text-xs text-text-light mb-6">Anda belum menambahkan sepatu apa pun ke keranjang belanja Anda.</p>
                <a href="{{ route('products.index') }}"
                    class="inline-block bg-primary hover:bg-primary-light text-white text-xs font-bold px-6 py-3 rounded-sm transition uppercase tracking-wider">
                    Mulai Belanja
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <!-- Keranjang Belanja (lg:col-span-8) -->
                <div class="lg:col-span-8 bg-white border border-border rounded-sm shadow-sm overflow-hidden" data-track-section="cart_items" data-track-label="Daftar Item Keranjang">
                    <div
                        class="p-6 border-b border-border bg-gray-50/50 hidden sm:grid grid-cols-12 text-[10px] font-bold text-primary uppercase tracking-wider">
                        <div class="col-span-6 flex items-center gap-3">
                            <input type="checkbox" :checked="semuaTercentang"
                                   @change="pilihSemua($event.target.checked)"
                                   class="w-4 h-4 rounded border-gray-300 text-accent focus:ring-accent cursor-pointer">
                            <span>Produk</span>
                        </div>
                        <div class="col-span-2 text-center">Harga</div>
                        <div class="col-span-2 text-center">Jumlah</div>
                        <div class="col-span-2 text-right">Subtotal</div>
                    </div>

                    <div class="divide-y divide-border">
                        @foreach($cartItems as $item)
                            <div class="p-6 grid grid-cols-1 sm:grid-cols-12 items-center gap-4">
                                <!-- Detail Produk (col-span-6) -->
                                <div class="col-span-1 sm:col-span-6 flex gap-4">
                                    {{-- Hanya baris tercentang yang ikut dibayar.
                                         Barang lain tetap tersimpan di sini. --}}
                                    <input type="checkbox" value="{{ $item->id }}"
                                           x-model="terpilih" @change="simpan()"
                                           class="w-4 h-4 mt-6 shrink-0 rounded border-gray-300 text-accent focus:ring-accent cursor-pointer">

                                    <div
                                        class="h-16 w-16 bg-gray-50 border border-border rounded-sm flex-shrink-0 flex items-center justify-center p-1">
                                        <img src="{{ $item->product->image_url }}"
                                            alt="{{ $item->product->name }}" class="object-contain max-h-full"
                                            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=200&q=80';">
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $item->product->slug) }}"
                                            class="text-xs font-bold text-text hover:text-primary transition line-clamp-1">
                                            {{ $item->product->name }}
                                        </a>
                                        @if($item->productVariant)
                                            <p class="text-[10px] text-text-light font-medium mt-1">
                                                Warna: <span class="font-bold text-text">{{ $item->productVariant->color }}</span>,
                                                Ukuran: <span class="font-bold text-text">{{ $item->productVariant->size }}</span>
                                            </p>
                                        @endif
                                        <div class="flex items-center gap-4 mt-2 sm:hidden">
                                            <!-- Tombol hapus untuk mobile -->
                                            <form action="{{ route('cart.destroy', $item->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-[10px] font-bold text-accent hover:underline uppercase">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Harga Satuan (col-span-2) -->
                                <div class="col-span-1 sm:col-span-2 text-center text-xs font-medium text-text">
                                    <span class="sm:hidden text-text-light mr-1">Harga:</span>
                                    Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                                </div>

                                <!-- Jumlah (col-span-2) -->
                                <div class="col-span-1 sm:col-span-2 flex justify-center">
                                    <div class="flex border border-border rounded-sm bg-white">
                                        <button type="button"
                                            @click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})"
                                            class="px-2 py-0.5 text-gray-500 hover:bg-gray-150 transition">-</button>
                                        <input type="text" value="{{ $item->quantity }}" readonly
                                            class="w-8 text-center text-xs font-bold border-none focus:ring-0 p-0.5">
                                        <button type="button"
                                            @click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})"
                                            class="px-2 py-0.5 text-gray-500 hover:bg-gray-150 transition">+</button>
                                    </div>

                                    <!-- Form Tersembunyi untuk submit -->
                                    <form id="update-form-{{ $item->id }}" action="{{ route('cart.update', $item->id) }}"
                                        method="POST" class="hidden">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $item->quantity }}">
                                    </form>
                                </div>

                                <!-- Subtotal (col-span-2) -->
                                <div
                                    class="col-span-1 sm:col-span-2 text-right text-xs font-bold text-text flex sm:block justify-between items-center">
                                    <span class="sm:hidden text-text-light font-medium">Subtotal:</span>
                                    <span>{{ $item->formatted_subtotal }}</span>
                                </div>

                                <!-- Tombol hapus untuk desktop -->
                                <div
                                    class="hidden sm:block absolute right-4 transform translate-x-12 group-hover:translate-x-0 transition-transform duration-200">
                                </div>
                                <div class="hidden sm:flex col-span-12 justify-end pt-2 border-t border-dashed border-gray-100">
                                    <form action="{{ route('cart.destroy', $item->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="text-[9px] font-bold text-accent hover:text-accent-dark tracking-wider flex items-center gap-1 uppercase">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Hapus Item
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Order Summary (lg:col-span-4) -->
                <div class="lg:col-span-4 bg-white border border-border p-6 rounded-sm shadow-sm space-y-6" data-track-section="cart_summary" data-track-label="Ringkasan Belanja Keranjang">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary border-b border-border pb-3">
                        Ringkasan Belanja</h3>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between font-semibold text-text-light">
                            <span x-text="'Subtotal Barang (' + terpilih.length + ')'">Subtotal Barang</span>
                            <span x-text="rupiah(subtotal)">{{ $cart->formatted_total }}</span>
                        </div>
                        <div class="flex justify-between font-semibold text-text-light">
                            <span>Estimasi Ongkos Kirim</span>
                            <span>Free</span>
                        </div>
                        <div
                            class="border-t border-border pt-4 flex justify-between font-black text-sm text-primary uppercase">
                            <span>Total Pembayaran</span>
                            <span class="text-accent" x-text="rupiah(subtotal)">{{ $cart->formatted_total }}</span>
                        </div>
                    </div>

                    <div class="pt-2">
                        {{-- Dimatikan kalau belum ada yang dicentang. --}}
                        <a href="{{ route('checkout.index') }}"
                            x-show="adaYangDipilih"
                            class="block w-full bg-accent hover:bg-accent-light text-white text-center text-xs font-bold py-3.5 rounded-sm transition uppercase tracking-wider shadow-sm">
                            Lanjut ke Checkout
                        </a>
                        <span x-show="!adaYangDipilih" x-cloak
                            class="block w-full bg-gray-200 text-gray-400 text-center text-xs font-bold py-3.5 rounded-sm uppercase tracking-wider cursor-not-allowed">
                            Pilih barang dulu
                        </span>
                        <a href="{{ route('products.index') }}"
                            class="block w-full text-center text-[10px] font-bold text-primary hover:underline mt-4 uppercase">
                            Lanjut Belanja
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Keadaan halaman keranjang. --}}
    <script>
        function keranjang() {
            return {
                // Baris yang dicentang.
                terpilih: @json($cartItems->where('dipilih', true)->pluck('id')->map(fn ($id) => (string) $id)->values()),

                hargaBaris: @json($cartItems->mapWithKeys(fn ($i) => [(string) $i->id => (float) $i->subtotal])),

                semuaId: @json($cartItems->pluck('id')->map(fn ($id) => (string) $id)->values()),

                get semuaTercentang() {
                    return this.semuaId.length > 0 && this.terpilih.length === this.semuaId.length;
                },

                get subtotal() {
                    return this.terpilih.reduce((jml, id) => jml + (this.hargaBaris[id] || 0), 0);
                },

                get adaYangDipilih() {
                    return this.terpilih.length > 0;
                },

                pilihSemua(nyala) {
                    this.terpilih = nyala ? [...this.semuaId] : [];
                    this.simpan();
                },

                // Pilihan disimpan ke peladen supaya checkout membaca hal yang sama dengan yang terlihat di layar.
                simpan() {
                    fetch('{{ route('cart.pilih') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ ids: this.terpilih.map(Number) }),
                    }).catch(() => {});
                },

                rupiah(nilai) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(nilai)));
                },

                updateQuantity(itemId, qty) {
                    if (qty < 1) return;

                    const form = document.getElementById('update-form-' + itemId);
                    form.querySelector('input[name=quantity]').value = qty;
                    form.submit();
                },
            };
        }
    </script>
</x-app-layout>