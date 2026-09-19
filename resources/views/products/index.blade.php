<x-app-layout>
    <x-slot name="title">
        {{ isset($currentCategory) ? $currentCategory->name : 'Semua Koleksi Sepatu' }}
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header Title (Figma Style) -->
        <div class="text-center mb-8 bg-bg-secondary p-8 rounded-sm border border-border">
            <h1 class="text-3xl font-black text-primary uppercase tracking-wider">
                {{ isset($currentCategory) ? $currentCategory->name : 'Daftar Semua Produk' }}
            </h1>
            <p class="text-xs text-text-light mt-2 italic uppercase">
                {{ isset($currentCategory) ? $currentCategory->description : 'Temukan berbagai koleksi sepatu premium terbaik untuk setiap langkah bernilai Anda.' }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar Filters -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Search widget -->
                <div class="bg-white border border-border p-6 rounded-sm shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary border-b border-border pb-3 mb-4">Cari Produk</h3>
                    <form action="{{ route('products.index') }}" method="GET" class="relative">
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        @if(request('sort'))
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                        @endif
                        <input type="text" name="search" placeholder="Masukkan kata kunci..." 
                            value="{{ request('search') }}"
                            class="w-full text-xs border border-border rounded-sm px-3 py-2.5 focus:ring-primary focus:border-primary">
                        <button type="submit" class="absolute right-3 top-3 text-text-light">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- Categories filter -->
                <div class="bg-white border border-border p-6 rounded-sm shadow-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary border-b border-border pb-3 mb-4">Kategori</h3>
                    <div class="space-y-2">
                        <a href="{{ route('products.index', ['search' => request('search'), 'sort' => request('sort')]) }}" 
                            class="block text-xs font-semibold px-3 py-2 rounded-sm {{ !isset($currentCategory) && !request('category') ? 'bg-primary text-white' : 'text-gray-700 hover:bg-bg-secondary hover:text-primary transition' }}">
                            Semua Sepatu
                        </a>
                        @foreach($categories as $cat)
                            <a href="{{ route('categories.show', $cat->slug) }}" 
                                class="block text-xs font-semibold px-3 py-2 rounded-sm {{ (isset($currentCategory) && $currentCategory->id === $cat->id) || request('category') === $cat->slug ? 'bg-primary text-white' : 'text-gray-700 hover:bg-bg-secondary hover:text-primary transition' }}">
                                {{ $cat->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Products List Area -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Sorting & Top stats -->
                <div class="bg-white border border-border p-4 rounded-sm shadow-sm flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-xs font-semibold text-text-light">
                        Menampilkan {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} produk
                    </div>

                    <!-- Sort dropdown -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-text-light">Urutkan:</span>
                        <select onchange="location = this.value;" class="text-xs border border-border rounded-sm py-1.5 pl-3 pr-8 focus:ring-primary focus:border-primary">
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'terbaru']) }}" {{ $sort === 'terbaru' ? 'selected' : '' }}>Terbaru</option>
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'termurah']) }}" {{ $sort === 'termurah' ? 'selected' : '' }}>Harga: Terendah ke Tertinggi</option>
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'termahal']) }}" {{ $sort === 'termahal' ? 'selected' : '' }}>Harga: Tertinggi ke Terendah</option>
                            <option value="{{ request()->fullUrlWithQuery(['sort' => 'terlaris']) }}" {{ $sort === 'terlaris' ? 'selected' : '' }}>Terpopuler / Terlaris</option>
                        </select>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-6">
                    @forelse($products as $product)
                        {{-- 2 kartu pertama: eager load gambar untuk mempercepat LCP --}}
                        <x-product-card :product="$product" :eager="$loop->index < 2" />
                    @empty
                        <div class="col-span-full bg-white border border-border p-12 text-center rounded-sm">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-bold text-text mb-1">Produk Tidak Ditemukan</p>
                            <p class="text-xs text-text-light">Coba sesuaikan kata kunci pencarian atau filter kategori Anda.</p>
                        </div>
                    @endforelse
                </div>

                <!-- Pagination Links -->
                <div class="pt-6">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
