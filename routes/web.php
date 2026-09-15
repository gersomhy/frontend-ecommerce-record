<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\KodePosController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RpayController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// ─── Halaman Publik (bisa diakses siapa saja) ────────────────────

// Pelacakan durasi atensi seksi website (dwell time)
Route::post('/track/dwell', [TrackingController::class, 'recordSectionDwell'])->name('track.dwell');

Route::get('/', [HomeController::class, 'index'])->name('home');

// Halaman profil perusahaan
Route::view('/about', 'about')->name('about');
Route::view('/kontak', 'kontak')->name('kontak');
Route::view('/faq', 'faq')->name('faq');
Route::view('/affiliate', 'affiliate')->name('affiliate');

// Halaman kebijakan & ketentuan
Route::view('/pengiriman-retur', 'pengiriman-retur')->name('pengiriman-retur');
Route::view('/kebijakan-privasi', 'kebijakan-privasi')->name('kebijakan-privasi');
Route::view('/syarat-ketentuan', 'syarat-ketentuan')->name('syarat-ketentuan');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

// Webhook Midtrans (Server-to-Server)
Route::post('/midtrans/callback', [CheckoutController::class, 'midtransCallback'])->name('midtrans.callback');
Route::post('/checkout/midtrans/callback', [CheckoutController::class, 'midtransCallback']);

// Webhook Biteship — status pengiriman dikirim Biteship setiap kali berubah.
// Gratis, dan menggantikan pemanggilan Tracking API yang memotong saldo Rp 10
// tiap kali halaman pelacakan dibuka.
Route::post('/webhook/biteship', \App\Http\Controllers\BiteshipWebhookController::class)
    ->name('webhook.biteship');

// ─── Keranjang & awal checkout: boleh diakses tamu ────────────────
//
// Pembeli baru tidak dipaksa mendaftar lebih dulu. Ia bisa memasukkan
// produk ke keranjang dan membuka checkout; pendaftaran akun dilakukan
// di langkah "Kontak" pada halaman checkout itu sendiri.

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::post('/cart/pilih', [CartController::class, 'pilih'])->name('cart.pilih');
Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/kontak', [CheckoutController::class, 'simpanKontak'])->name('checkout.kontak');
Route::post('/checkout/masuk', [CheckoutController::class, 'masuk'])->name('checkout.masuk');
Route::post('/checkout/shipping-cost', [CheckoutController::class, 'calculateShippingCost'])->name('checkout.shipping-cost');

// Pencarian koordinat alamat, diperantarai server agar hasilnya bisa
// di-cache bersama dan tidak kena penolakan Nominatim. Dibatasi lajunya
// supaya satu pengunjung tidak bisa menguras jatah permintaan.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/geocode', [GeocodingController::class, 'cari'])->name('geocode.cari');
    Route::get('/geocode/balik', [GeocodingController::class, 'balik'])->name('geocode.balik');

    // Pilihan kode pos menurut wilayah, bersumber dari data kurir.
    Route::get('/kode-pos', [KodePosController::class, 'cari'])->name('kodepos.cari');

    // Pemeriksaan kode referal saat checkout.
    Route::get('/referal/periksa', [ReferralController::class, 'periksa'])->name('referal.periksa');
});

// Menyegarkan token CSRF pada halaman yang lama dibiarkan terbuka (mis. checkout).
// Sekaligus menyentuh sesi supaya tidak keburu kedaluwarsa dan memicu galat 419.
Route::get('/token-sesi', fn () => response()->json(['token' => csrf_token()]))->name('token.sesi');

// Invoice Pesanan (dapat diakses dengan login atau via Signed URL aman dari email)
Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
Route::get('/orders/{order}/invoice/download', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.download');

// ─── Halaman Khusus Customer (wajib login) ────────────────────────

Route::middleware('auth')->group(function () {

    // Profil akun
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/delete-request', [ProfileController::class, 'requestDeletionCode'])->name('profile.delete-request');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Pembuatan pesanan tetap wajib login — akun sudah terbentuk di langkah Kontak
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/{orderNumber}/payment', [CheckoutController::class, 'payment'])->name('checkout.payment');
    Route::post('/checkout/{orderNumber}/change-method', [CheckoutController::class, 'changePaymentMethod'])->name('checkout.payment.change-method');
    Route::get('/checkout/{orderNumber}/status', [CheckoutController::class, 'paymentStatus'])->name('checkout.payment.status');
    Route::get('/checkout/{orderNumber}/finish', [CheckoutController::class, 'paymentFinish'])->name('checkout.payment.finish');
    Route::post('/checkout/{orderNumber}/upload-proof', [CheckoutController::class, 'uploadPaymentProof'])->name('checkout.payment.upload-proof');

    // Riwayat pesanan & Tracking
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Lacak pesanan — wajib login karena nomor resi termasuk data pribadi.
    // Didaftarkan sebelum /orders/{order} agar tidak tertangkap sebagai nomor pesanan.
    Route::get('/tracking', [OrderController::class, 'tracking'])->name('tracking');

    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('/orders/{order}/invoice/download', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.download');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/tracking-status', [OrderController::class, 'trackingStatus'])->name('orders.tracking-status');
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');
    Route::post('/orders/{order}/pengembalian', [ReturnController::class, 'store'])->name('orders.return');
    Route::post('/orders/{order}/pengembalian/resi', [ReturnController::class, 'kirimBalik'])->name('orders.return.resi');

    // Penilaian produk, hanya untuk pesanan yang sudah selesai.
    Route::post('/orders/{order}/penilaian', [ProductReviewController::class, 'store'])->name('orders.review');

    // R_Pay — dompet digital pembeli
    Route::get('/rpay', [RpayController::class, 'index'])->name('rpay.index');
    Route::post('/rpay/pencairan', [RpayController::class, 'withdraw'])->name('rpay.withdraw');

    // Dashboard customer
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// ─── Route Autentikasi (dari Breeze) ─────────────────────────────

require __DIR__.'/auth.php';
