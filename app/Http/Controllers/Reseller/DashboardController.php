<?php

namespace App\Http\Controllers\Reseller;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ResellerCredit;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $q = User::query()->whereDirectCustomerOf((int) auth()->id());

        $credits = ResellerCredit::query()->where('reseller_id', auth()->id())->value('credits') ?? 0;

        return view('reseller.dashboard', [
            'totalClients' => (clone $q)->count(),
            'activeClients' => (clone $q)->where('status', UserStatus::Active)->count(),
            'expiredClients' => (clone $q)->where('status', UserStatus::Expired)->count(),
            'credits' => $credits,
        ]);
    }
}
