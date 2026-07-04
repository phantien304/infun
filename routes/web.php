<?php

use App\Helpers\Facades\ExtendedRoute as Route;

Route::get('/maintenance', 'MaintenanceController@index')->name('maintenance');
Route::get('/error-404', 'ErrorController@index')->name('error.404');
Route::get('/give-me-csrf', 'CsrfTokenController@index')->name('csrf.index');
Route::post('/give-me-csrf', function () {
    return redirect()->route('home');
});
Route::post('file/upload', 'FileController@upload')->name('file.upload');
Route::middleware(['maintenance', 'cache_page', 'limit_access'])->group(function () {
    Route::get('/san-pham', 'ProductController@getList')->name('product.getList');
    Route::get('/khuyen-mai', 'ProductController@special')->name('product.special');
    Route::get('/san-pham/get-list-review', 'ProductController@getListReview')->name('product.getListReview');
    Route::get('/bai-viet', 'BlogController@getList')->name('blog.getList');
    Route::get('/khach-hang-danh-gia', 'StoreReviewController@getList')->name('storeReview.getList');
    Route::get('/thanh-phan', 'IngredientController@getList')->name('ingredient.getList');
    Route::get('/tags', 'TagController@getList')->name('tags.getList');
    Route::prefix('review')->group(function () {
        Route::post('/', 'ReviewController@saveReview')->name('review.saveReview');
        Route::get('/list/{productId}', 'ReviewController@list')->name('review.list');
        Route::middleware('auth')->group(function () {
            Route::post('/vote', 'ReviewController@vote')->name('review.vote');
            Route::post('/report', 'ReviewController@report')->name('review.report');
        });
    });
    Route::any('/order/search', 'OrderController@search')->name('order.search');
    Route::get('/lien-he', 'ContactController@index')->name('contact.index');
    Route::post('/lien-he/send', 'ContactController@send')->name('contact.send');
    Route::prefix('checkout')->group(function () {
        Route::middleware('auth')->group(function () {
            Route::get('repayment/{id?}', 'CheckoutController@repayment')->name('checkout.repayment');
            Route::post('saveRepayment', 'CheckoutController@saveRepayment')->name('checkout.saveRepayment');
        });
        Route::any('cart', 'CheckoutController@cart')->name('checkout.cart');
        Route::post('add-to-cart', 'CheckoutController@addToCart')->name('checkout.addToCart')->middleware('throttle:30,1');
        Route::post('consult-sign', 'CheckoutController@consultSign')->name('checkout.consultSign');
        Route::post('save-order', 'CheckoutController@saveOrder')->name('checkout.saveOrder')->middleware('throttle:10,1');
        Route::get('success', 'CheckoutController@success')->name('checkout.success');
        Route::get('shipping', 'CheckoutController@recalcTotals')->name('checkout.shipping');
        Route::post('payment/call-back', 'CheckoutController@paymentCallBack')->name('checkout.paymentCallBack')->withoutMiddleware(['cache_page']);
        Route::get('/', 'CheckoutController@index')->name('checkout.index');
        Route::get('coupons', 'CheckoutCouponController@list')->name('checkout.coupons');
        Route::post('coupons/apply', 'CheckoutCouponController@apply')->name('checkout.couponsApply');
        Route::post('coupons/save', 'CheckoutCouponController@save')->name('checkout.couponsSave');
        Route::post('coupons/unsave', 'CheckoutCouponController@unsave')->name('checkout.couponsUnsave');
        Route::post('coupons/remove', 'CheckoutCouponController@remove')->name('checkout.couponsRemove');
        Route::post('gifts/pick', 'CheckoutGiftController@pick')->name('checkout.giftsPick');
        Route::post('gifts/remove', 'CheckoutGiftController@remove')->name('checkout.giftsRemove');
        Route::post('vouchers/apply', 'CheckoutVoucherController@apply')->name('checkout.vouchersApply');
        Route::post('vouchers/remove', 'CheckoutVoucherController@remove')->name('checkout.vouchersRemove');
    });
    Route::get('currency/{code}', 'LocaleController@currency')->name('locale.currency')->withoutMiddleware(['cache_page']);
    Route::get('language/{code}', 'LocaleController@language')->name('locale.language')->withoutMiddleware(['cache_page']);

    Route::prefix('resource')->group(function () {
        Route::get('zone', 'ResourceController@zone')->name('resource.zone');
        Route::post('zone-shipping', 'ResourceController@zoneShipping')->name('resource.zoneShipping');
        Route::get('district', 'ResourceController@district')->name('resource.district');
        Route::get('ward', 'ResourceController@ward')->name('resource.ward');
    });
    Route::prefix('account')->group(function () {
        Route::get('login', 'AuthController@login')->name('auth.login');
        Route::post('login', 'AuthController@doLogin')->name('auth.doLogin');
        Route::get('login/{provider}', 'AuthController@loginWithProvider')->name('auth.loginSocial');
        Route::get('login/{provider}/callback', 'AuthController@handleProviderCallback')->name('auth.socialCallback');
        Route::get('register', 'AuthController@register')->name('auth.register');
        Route::post('register', 'AuthController@doRegister')->name('auth.doRegister');
        Route::get('forgot-password', 'AuthController@forgotPassword')->name('auth.forgotPassword');
        Route::post('forgot-password', 'AuthController@doForgotPassword')->name('auth.doForgotPassword');
        Route::get('change-password', 'AuthController@changePassword')->name('auth.changePassword');
        Route::post('change-password', 'AuthController@doChangePassword')->name('auth.doChangePassword');
        Route::get('verify-email', 'AuthController@verifyEmail')->name('auth.verifyEmail');
        Route::post('add-address', 'AccountController@addAddress')->name('account.addAddress');
    });

    Route::middleware('auth')->group(function () {
        Route::prefix('account')->group(function () {
            Route::any('/', 'AccountController@index')->name('account.index');
            Route::any('edit', 'AccountController@edit')->name('account.edit');
            Route::any('password', 'AccountController@password')->name('account.password');
            Route::any('address', 'AccountController@address')->name('account.address');
            Route::any('address/create', 'AccountController@addressForm')->name('account.address.create');
            Route::any('address/edit', 'AccountController@addressForm')->name('account.address.edit');
            Route::any('wishlist', 'AccountController@wishList')->name('account.wishlist');
            Route::get('user-wish-list', 'AccountController@userWishlist')->name('account.userWishlist');
            Route::post('cancel-order', 'AccountController@cancelOrder')->name('account.cancelOrder');
            Route::any('orders', 'AccountController@orders')->name('account.orders');
            Route::any('orders/{id?}', 'AccountController@detailOrder')->name('account.detailOrder');
            Route::any('newsletter', 'AccountController@newsletter')->name('account.newsletter');
            Route::any('logout', 'AccountController@logout')->name('account.logout');
        });
    });

    Route::get('/{slug?}', 'HomeController@index')->name('home');
});
