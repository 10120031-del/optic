<?php

use App\Http\Controllers\Admin\ContactLensController as AdminContactLensController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FrameController as AdminFrameController;
use App\Http\Controllers\Admin\LensController as AdminLensController;
use App\Http\Controllers\Admin\LensFeatureController as AdminLensFeatureController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderReturnController as AdminOrderReturnController;
use App\Http\Controllers\Admin\PrescriptionController as AdminPrescriptionController;
use App\Http\Controllers\Admin\PromotionCampaignController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactLensController;
use App\Http\Controllers\FaceMatchController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront (public + guest cart)
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/frames', [FrameController::class, 'index'])->name('frames.index');
Route::get('/frames/{frame}', [FrameController::class, 'show'])->name('frames.show');

Route::get('/contact-lenses', [ContactLensController::class, 'index'])->name('contact-lenses.index');
Route::get('/contact-lenses/{contactLens}', [ContactLensController::class, 'show'])->name('contact-lenses.show');

Route::get('/face-match', [FaceMatchController::class, 'create'])->name('face-match.create');
Route::post('/face-match', [FaceMatchController::class, 'analyze'])->name('face-match.analyze');
Route::get('/face-match/{faceShape:slug}', [FaceMatchController::class, 'recommend'])->name('face-match.recommend');

// Open to guests so they can fill a cart before signing in, but closed to
// staff — an admin account never shops.
Route::middleware('customer')->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/eyeglasses', [CartController::class, 'storeEyeglass'])->name('cart.eyeglasses.store');
    Route::patch('/cart/eyeglasses/{eyeglass}', [CartController::class, 'updateEyeglass'])->name('cart.eyeglasses.update');
    Route::delete('/cart/eyeglasses/{eyeglass}', [CartController::class, 'destroyEyeglass'])->name('cart.eyeglasses.destroy');
    Route::post('/cart/contact-lenses', [CartController::class, 'storeContactLens'])->name('cart.contact-lenses.store');
    Route::patch('/cart/contact-lenses/{contactLens}', [CartController::class, 'updateContactLens'])->name('cart.contact-lenses.update');
    Route::delete('/cart/contact-lenses/{contactLens}', [CartController::class, 'destroyContactLens'])->name('cart.contact-lenses.destroy');
});

/*
|--------------------------------------------------------------------------
| Guest-only auth routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated customer routes (checkout, orders, prescriptions, reviews)
| Staff are redirected to the admin dashboard by the 'customer' middleware.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'customer'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/return', [OrderReturnController::class, 'create'])->name('orders.returns.create');
    Route::post('/orders/{order}/return', [OrderReturnController::class, 'store'])->name('orders.returns.store');

    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/create', [PrescriptionController::class, 'create'])->name('prescriptions.create');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');

    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});

// Prescription scans live on the private disk. Served through the controller
// (which checks owner-or-staff) rather than a public URL, and kept out of the
// 'customer' group so staff reviewing an upload aren't bounced to the dashboard.
Route::get('/prescriptions/{prescription}/file', [PrescriptionController::class, 'file'])
    ->middleware('auth')
    ->name('prescriptions.file');

/*
|--------------------------------------------------------------------------
| Admin (shop owner / staff)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('frames', AdminFrameController::class)->except(['show']);
    Route::delete('/frames/{frame}/images/{image}', [AdminFrameController::class, 'destroyImage'])->name('frames.images.destroy');
    Route::resource('lenses', AdminLensController::class)->except(['show']);

    // Hyphenated resource names route-bind to a snake_case wildcard
    // ({lens_feature}) by default; pinned to camelCase here to match the
    // $lensFeature / $contactLens parameter names the controllers use.
    Route::resource('lens-features', AdminLensFeatureController::class)
        ->except(['show'])
        ->parameters(['lens-features' => 'lensFeature']);
    Route::resource('contact-lenses', AdminContactLensController::class)
        ->except(['show'])
        ->parameters(['contact-lenses' => 'contactLens']);

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

    Route::get('/returns', [AdminOrderReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{return}', [AdminOrderReturnController::class, 'show'])->name('returns.show');
    Route::patch('/returns/{return}/status', [AdminOrderReturnController::class, 'updateStatus'])->name('returns.status');

    Route::get('/prescriptions', [AdminPrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/{prescription}', [AdminPrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::patch('/prescriptions/{prescription}/verify', [AdminPrescriptionController::class, 'verify'])->name('prescriptions.verify');

    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/promotions', [PromotionCampaignController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/create', [PromotionCampaignController::class, 'create'])->name('promotions.create');
    Route::post('/promotions', [PromotionCampaignController::class, 'store'])->name('promotions.store');
    Route::get('/promotions/{promotion}', [PromotionCampaignController::class, 'show'])->name('promotions.show');
});
