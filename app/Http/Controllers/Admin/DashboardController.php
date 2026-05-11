<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Content;
use App\Models\LibraryFolderPoster;
use App\Models\User;
use App\Services\LocalMediaService;
use App\Support\SubscriptionTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $customers = User::query()->where('role', UserRole::Customer);

        $totalContent = Content::query()->count();
        $contentVodTotal = Content::query()->where('type', ContentType::Vod)->count();
        $contentVodLocal = Content::query()
            ->where('type', ContentType::Vod)
            ->where('stream_url', 'like', LocalMediaService::LOCAL_PREFIX.'%')
            ->count();
        $contentVodRemote = max(0, $contentVodTotal - $contentVodLocal);
        $contentLive = Content::query()->where('type', ContentType::Live)->count();
        $contentSeries = Content::query()->where('type', ContentType::Series)->count();
        $contentSeriesDistinctFolders = (int) Content::query()
            ->where('type', ContentType::Series)
            ->whereNotNull('library_folder')
            ->where('library_folder', '!=', '')
            ->distinct()
            ->count('library_folder');
        $contentActive = Content::query()->where('is_active', true)->count();
        $contentInactive = Content::query()->where('is_active', false)->count();

        $activeCustomers = (clone $customers)->where('status', UserStatus::Active)->count();
        $expiredCustomers = (clone $customers)->where('status', UserStatus::Expired)->count();
        $suspendedCustomers = (clone $customers)->where('status', UserStatus::Suspended)->count();
        $customersForPct = max(1, $activeCustomers + $expiredCustomers + $suspendedCustomers);
        $contentForPct = max(1, $totalContent);

        $customersNearExpiry = $this->customersExpiringWithinDays(3, 18);

        $pctContentVod = (int) round(100 * $contentVodTotal / $contentForPct);

        return view('admin.dashboard', [
            'totalUsers' => User::query()->count(),
            'activeCustomers' => $activeCustomers,
            'expiredCustomers' => $expiredCustomers,
            'suspendedCustomers' => $suspendedCustomers,
            'totalResellers' => User::query()->where('role', UserRole::Reseller)->count(),
            'totalVendors' => User::query()->where('role', UserRole::Vendor)->count(),
            'totalContent' => $totalContent,
            'contentVodTotal' => $contentVodTotal,
            'contentVodLocal' => $contentVodLocal,
            'contentVodRemote' => $contentVodRemote,
            'contentLive' => $contentLive,
            'contentSeries' => $contentSeries,
            'contentSeriesDistinctFolders' => $contentSeriesDistinctFolders,
            'contentActive' => $contentActive,
            'contentInactive' => $contentInactive,
            'folderPosterOverrides' => LibraryFolderPoster::query()->count(),
            'pctCustomersActive' => (int) round(100 * $activeCustomers / $customersForPct),
            'pctContentVod' => $pctContentVod,
            'pctContentLive' => (int) round(100 * $contentLive / $contentForPct),
            'pctContentSeries' => (int) round(100 * $contentSeries / $contentForPct),
            'pctContentActive' => (int) round(100 * $contentActive / $contentForPct),
            'customersNearExpiry' => $customersNearExpiry,
            'recentAccess' => AccessLog::query()
                ->with(['user', 'content'])
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    /**
     * Clientes con ventana de suscripción aún abierta y fin inclusivo dentro de los próximos N días.
     *
     * @return Collection<int, User>
     */
    private function customersExpiringWithinDays(int $days, int $maxList): Collection
    {
        $days = max(1, min(30, $days));
        $maxList = max(5, min(50, $maxList));

        $nowTz = Carbon::now(SubscriptionTime::timezone());
        $horizonEnd = $nowTz->copy()->addDays($days);

        return User::query()
            ->where('role', UserRole::Customer)
            ->whereNotNull('expires_at')
            ->whereIn('status', [UserStatus::Active, UserStatus::Suspended])
            ->with('parent')
            ->orderBy('expires_at')
            ->limit(120)
            ->get()
            ->filter(function (User $u) use ($nowTz, $horizonEnd): bool {
                if (! SubscriptionTime::isDateWindowOpen($u->expires_at)) {
                    return false;
                }
                $end = SubscriptionTime::inclusiveEndBoundary($u->expires_at);
                if ($end === null) {
                    return false;
                }

                return $end->greaterThan($nowTz) && $end->lessThanOrEqualTo($horizonEnd);
            })
            ->sortBy(fn (User $u): int => (int) (SubscriptionTime::inclusiveEndBoundary($u->expires_at)?->timestamp ?? PHP_INT_MAX))
            ->take($maxList)
            ->values();
    }
}
