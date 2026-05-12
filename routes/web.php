<?php

use App\Http\Controllers\Admin\ActiveSessionsController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminCustomerStreamingProfileController;
use App\Http\Controllers\Admin\AdminVendorController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChannelDiagnosticsController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ContentPosterEnrichmentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LibraryFolderPosterController;
use App\Http\Controllers\Admin\LibraryFoldersController;
use App\Http\Controllers\Admin\LocalLibraryController;
use App\Http\Controllers\Admin\M3uImportController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ResellerController;
use App\Http\Controllers\Admin\StreamingAppearanceController;
use App\Http\Controllers\Admin\ThemeAssetsController;
use App\Http\Controllers\Admin\XtreamSourceController;
use App\Http\Controllers\App\HeroPreviewUrlController;
use App\Http\Controllers\App\HomeController as AppHomeController;
use App\Http\Controllers\App\PlanExpiredController;
use App\Http\Controllers\App\PlaybackRequestController;
use App\Http\Controllers\App\StreamingProfileController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\Partner\CustomerStreamingProfileController as PartnerCustomerStreamingProfileController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayStreamController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reseller\CustomerController as ResellerCustomerController;
use App\Http\Controllers\Reseller\DashboardController as ResellerDashboardController;
use App\Http\Controllers\Reseller\NetworkController as ResellerNetworkController;
use App\Http\Controllers\TranscodeController;
use App\Http\Controllers\Vendor\CustomerController as VendorCustomerController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    $u = auth()->user();
    abort_unless($u, 403);

    return match ($u->role->value) {
        'admin' => redirect()->route('admin.dashboard'),
        'reseller' => redirect()->route('reseller.dashboard'),
        'vendor' => redirect()->route('vendor.dashboard'),
        default => redirect()->route('app.profiles.index'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/play/{content}/{token}', PlayStreamController::class)
    ->name('play.stream');

Route::get('/transcode/{content}/{token}', TranscodeController::class)
    ->name('play.transcode');

Route::post('/heartbeat/{content}/{token}', HeartbeatController::class)
    ->middleware('throttle:120,1')
    ->name('player.heartbeat');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::delete('/profile/sessions/{session}', [ProfileController::class, 'destroySession'])
        ->where('session', '[A-Za-z0-9]{10,255}')
        ->name('profile.sessions.destroy');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('streaming-apariencia', [StreamingAppearanceController::class, 'edit'])->name('streaming-appearance.edit');
    Route::post('streaming-apariencia', [StreamingAppearanceController::class, 'update'])->name('streaming-appearance.update');

    Route::get('imagenes-sitio', [ThemeAssetsController::class, 'edit'])->name('theme-assets.edit');
    Route::put('imagenes-sitio', [ThemeAssetsController::class, 'update'])
        ->middleware('relax.upload')
        ->name('theme-assets.update');

    Route::resource('plans', PlanController::class)->except(['show']);

    Route::get('resellers', [ResellerController::class, 'index'])->name('resellers.index');
    Route::get('resellers/create', [ResellerController::class, 'create'])->name('resellers.create');
    Route::post('resellers', [ResellerController::class, 'store'])->name('resellers.store');
    Route::post('resellers/bulk-destroy', [ResellerController::class, 'bulkDestroy'])->name('resellers.bulk_destroy');
    Route::get('resellers/{reseller}', [ResellerController::class, 'show'])->name('resellers.show');
    Route::get('resellers/{reseller}/edit', [ResellerController::class, 'edit'])->name('resellers.edit');
    Route::put('resellers/{reseller}', [ResellerController::class, 'update'])->name('resellers.update');
    Route::delete('resellers/{reseller}', [ResellerController::class, 'destroy'])->name('resellers.destroy');
    Route::post('resellers/{reseller}/credits', [ResellerController::class, 'updateCredits'])->name('resellers.credits');

    Route::get('vendors', [AdminVendorController::class, 'index'])->name('vendors.index');
    Route::get('vendors/create', [AdminVendorController::class, 'create'])->name('vendors.create');
    Route::post('vendors', [AdminVendorController::class, 'store'])->name('vendors.store');
    Route::post('vendors/bulk-destroy', [AdminVendorController::class, 'bulkDestroy'])->name('vendors.bulk_destroy');
    Route::get('vendors/{vendor}', [AdminVendorController::class, 'show'])->name('vendors.show');
    Route::get('vendors/{vendor}/edit', [AdminVendorController::class, 'edit'])->name('vendors.edit');
    Route::put('vendors/{vendor}', [AdminVendorController::class, 'update'])->name('vendors.update');
    Route::delete('vendors/{vendor}', [AdminVendorController::class, 'destroy'])->name('vendors.destroy');
    Route::post('vendors/{vendor}/credits', [AdminVendorController::class, 'updateCredits'])->name('vendors.credits');

    Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [AdminCustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [AdminCustomerController::class, 'store'])->name('customers.store');
    Route::post('customers/bulk-destroy', [AdminCustomerController::class, 'bulkDestroy'])->name('customers.bulk_destroy');
    Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [AdminCustomerController::class, 'edit'])->name('customers.edit');
    Route::get('customers/{customer}/espacios', [AdminCustomerStreamingProfileController::class, 'edit'])->name('customers.streaming-profiles.edit');
    Route::put('customers/{customer}/espacios/{profile}', [AdminCustomerStreamingProfileController::class, 'update'])->name('customers.streaming-profiles.update');
    Route::post('customers/{customer}/profiles/{profile}/vendido', [AdminCustomerStreamingProfileController::class, 'toggleSold'])->name('customers.profiles.sold');
    Route::put('customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::post('customers/{customer}/renew', [AdminCustomerController::class, 'renew'])->name('customers.renew');
    Route::post('customers/{customer}/suspend', [AdminCustomerController::class, 'suspend'])->name('customers.suspend');
    Route::post('customers/{customer}/activate', [AdminCustomerController::class, 'activate'])->name('customers.activate');
    Route::delete('customers/{customer}', [AdminCustomerController::class, 'destroy'])->name('customers.destroy');

    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('contents', ContentController::class)
        ->except(['show'])
        ->middlewareFor(['store', 'update'], 'relax.upload');
    Route::post('contents/bulk-destroy', [ContentController::class, 'bulkDestroy'])->name('contents.bulk_destroy');
    Route::post('contents/bulk-destroy-library-folder', [ContentController::class, 'bulkDestroyLibraryFolder'])->name('contents.bulk_destroy_library_folder');
    Route::post('contents/enrich-posters', [ContentPosterEnrichmentController::class, 'store'])->name('contents.enrich-posters');

    Route::get('m3u/import', [M3uImportController::class, 'create'])->name('m3u.import');
    Route::get('m3u/gestion', [M3uImportController::class, 'manage'])->name('m3u.manage');
    Route::post('m3u/purgar-remotas', [M3uImportController::class, 'purgeRemote'])->name('m3u.purge-remote');
    Route::post('m3u/podar-no-responden', [M3uImportController::class, 'cullDeadRemotes'])->name('m3u.cull-unreachable');
    // Barrido asíncrono (en background, sin bloquear el navegador)
    Route::post('m3u/podar-async', [M3uImportController::class, 'cullDeadRemotesAsync'])->name('m3u.cull-async');
    Route::get('m3u/podar-estado', [M3uImportController::class, 'cullStatus'])->name('m3u.cull-status');
    Route::post('m3u/escaneo-canales-sync', [M3uImportController::class, 'scanRemoteChannelsSync'])
        ->middleware('relax.upload')
        ->name('m3u.scan-channels-sync');
    Route::post('m3u/eliminar-canales-escaneo', [M3uImportController::class, 'deleteScannedUnreachableIds'])->name('m3u.delete-scanned-unreachable');
    Route::post('m3u/import', [M3uImportController::class, 'store'])
        ->middleware('relax.upload')
        ->name('m3u.import.store');

    Route::get('xtream', [XtreamSourceController::class, 'index'])->name('xtream.index');
    Route::get('xtream/create', [XtreamSourceController::class, 'create'])->name('xtream.create');
    Route::post('xtream', [XtreamSourceController::class, 'store'])->name('xtream.store');
    Route::delete('xtream/{xtreamSource}', [XtreamSourceController::class, 'destroy'])->name('xtream.destroy');
    Route::post('xtream/{xtreamSource}/sync', [XtreamSourceController::class, 'sync'])->name('xtream.sync');
    Route::post('xtream/{xtreamSource}/test', [XtreamSourceController::class, 'test'])->name('xtream.test');

    Route::get('diagnostics/channels', [ChannelDiagnosticsController::class, 'index'])->name('diagnostics.channels');
    Route::post('diagnostics/channels', [ChannelDiagnosticsController::class, 'diagnose'])->name('diagnostics.channels.diagnose');

    Route::get('active-sessions', [ActiveSessionsController::class, 'index'])
        ->name('active-sessions.index');
    Route::get('active-sessions/data', [ActiveSessionsController::class, 'data'])
        ->name('active-sessions.data');

    Route::get('library/carpetas', [LibraryFoldersController::class, 'index'])->name('library.folders.index');
    Route::post('library/carpetas/eliminar', [LibraryFoldersController::class, 'bulkDestroy'])->name('library.folders.bulk-destroy');
    Route::get('library/carpetas/caratulas', [LibraryFolderPosterController::class, 'index'])->name('library.folder-posters.index');
    Route::post('library/carpetas/caratulas', [LibraryFolderPosterController::class, 'store'])
        ->middleware('relax.upload')
        ->name('library.folder-posters.store');
    Route::delete('library/carpetas/caratulas/{libraryFolderPoster}', [LibraryFolderPosterController::class, 'destroy'])->name('library.folder-posters.destroy');

    Route::get('library/raidrive', [LocalLibraryController::class, 'index'])->name('library.raidrive');
    Route::post('library/raidrive/import', [LocalLibraryController::class, 'import'])
        ->middleware('relax.upload')
        ->name('library.raidrive.import');
    Route::post('library/raidrive/import-recursive', [LocalLibraryController::class, 'importRecursive'])
        ->middleware('relax.upload')
        ->name('library.raidrive.import-recursive');
    Route::post('library/raidrive/import-recursive-folders', [LocalLibraryController::class, 'importRecursiveFolders'])
        ->middleware('relax.upload')
        ->name('library.raidrive.import-recursive-folders');
    Route::post('library/raidrive/refresh-cache', [LocalLibraryController::class, 'refreshCache'])->name('library.raidrive.refresh-cache');
    Route::post('library/raidrive/sync-renames', [LocalLibraryController::class, 'syncRenamedFiles'])->name('library.raidrive.sync-renames');
});

Route::middleware(['auth', 'verified', 'role:reseller'])->prefix('reseller')->name('reseller.')->group(function () {
    Route::get('/', ResellerDashboardController::class)->name('dashboard');

    Route::get('network', [ResellerNetworkController::class, 'index'])->name('network.index');
    Route::get('network/resellers/create', [ResellerNetworkController::class, 'createReseller'])->name('network.resellers.create');
    Route::post('network/resellers', [ResellerNetworkController::class, 'storeReseller'])->name('network.resellers.store');
    Route::get('network/vendors/create', [ResellerNetworkController::class, 'createVendor'])->name('network.vendors.create');
    Route::post('network/vendors', [ResellerNetworkController::class, 'storeVendor'])->name('network.vendors.store');

    Route::get('customers', [ResellerCustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [ResellerCustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [ResellerCustomerController::class, 'store'])->name('customers.store');
    Route::post('customers/{customer}/renew', [ResellerCustomerController::class, 'renew'])->name('customers.renew');
    Route::post('customers/{customer}/suspend', [ResellerCustomerController::class, 'suspend'])->name('customers.suspend');
    Route::post('customers/{customer}/activate', [ResellerCustomerController::class, 'activate'])->name('customers.activate');

    Route::get('customers/{customer}/espacios', [PartnerCustomerStreamingProfileController::class, 'edit'])
        ->defaults('partner_route_prefix', 'reseller')
        ->name('customers.streaming-profiles.edit');
    Route::put('customers/{customer}/espacios/{profile}', [PartnerCustomerStreamingProfileController::class, 'update'])
        ->defaults('partner_route_prefix', 'reseller')
        ->name('customers.streaming-profiles.update');
    Route::post('customers/{customer}/profiles/{profile}/vendido', [PartnerCustomerStreamingProfileController::class, 'toggleSold'])
        ->defaults('partner_route_prefix', 'reseller')
        ->name('customers.profiles.sold');
});

Route::middleware(['auth', 'verified', 'role:vendor'])->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('/', VendorDashboardController::class)->name('dashboard');

    Route::get('customers', [VendorCustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [VendorCustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [VendorCustomerController::class, 'store'])->name('customers.store');
    Route::post('customers/{customer}/renew', [VendorCustomerController::class, 'renew'])->name('customers.renew');
    Route::post('customers/{customer}/suspend', [VendorCustomerController::class, 'suspend'])->name('customers.suspend');
    Route::post('customers/{customer}/activate', [VendorCustomerController::class, 'activate'])->name('customers.activate');

    Route::get('customers/{customer}/espacios', [PartnerCustomerStreamingProfileController::class, 'edit'])
        ->defaults('partner_route_prefix', 'vendor')
        ->name('customers.streaming-profiles.edit');
    Route::put('customers/{customer}/espacios/{profile}', [PartnerCustomerStreamingProfileController::class, 'update'])
        ->defaults('partner_route_prefix', 'vendor')
        ->name('customers.streaming-profiles.update');
    Route::post('customers/{customer}/profiles/{profile}/vendido', [PartnerCustomerStreamingProfileController::class, 'toggleSold'])
        ->defaults('partner_route_prefix', 'vendor')
        ->name('customers.profiles.sold');
});

Route::middleware(['auth', 'verified', 'role:customer', 'concurrent.sessions'])->prefix('app')->name('app.')->group(function () {
    Route::get('/plan-vencido', PlanExpiredController::class)->name('plan_expired');
});

Route::middleware(['auth', 'verified', 'role:customer', 'subscription.active', 'concurrent.sessions'])->prefix('app')->name('app.')->group(function () {
    Route::get('/profiles', [StreamingProfileController::class, 'index'])->name('profiles.index');
    Route::post('/profiles', [StreamingProfileController::class, 'select'])->name('profiles.select');
    Route::post('/profiles/cambiar', [StreamingProfileController::class, 'switch'])->name('profiles.switch');

    Route::middleware('streaming.profile')->group(function () {
        Route::get('/', AppHomeController::class)->name('home');
        Route::get('/content/{content}/reproducir', PlaybackRequestController::class)->name('playback.prepare');
        Route::get('/hero-preview/{content}/token', HeroPreviewUrlController::class)
            ->middleware('throttle:60,1')
            ->name('hero_preview.token');
    });
});

Route::middleware(['auth', 'verified', 'role:customer', 'subscription.active', 'concurrent.sessions', 'streaming.profile'])->prefix('player')->name('player.')->group(function () {
    Route::get('/{content}/{token}', [PlayerController::class, 'show'])->name('show');
});

require __DIR__.'/auth.php';
