<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CustomerProfile;
use App\Models\Plan;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\CategoryPolicy;
use App\Policies\ContentPolicy;
use App\Policies\CustomerProfilePolicy;
use App\Policies\PlanPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(CustomerProfile::class, CustomerProfilePolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Content::class, ContentPolicy::class);

        User::observe(UserObserver::class);

        Route::bind('customer', function (string $value): User {
            $user = User::query()->whereKey($value)->firstOrFail();

            if (! $user->isCustomer()) {
                abort(404);
            }

            $auth = auth()->user();
            if ($auth !== null && ! $auth->isAdmin()
                && ($auth->isReseller() || $auth->isVendor())
                && (int) $user->parent_id !== (int) $auth->id) {
                abort(404);
            }

            return $user;
        });

        $streamingComposer = function (\Illuminate\View\View $view): void {
            $id = session('streaming_profile_id');
            $prof = null;
            $letter = '?';
            if ($id !== null && auth()->check()) {
                $prof = CustomerProfile::query()
                    ->where('user_id', auth()->id())
                    ->where('id', $id)
                    ->first();
                if ($prof !== null) {
                    $letter = mb_strtoupper(mb_substr(trim((string) $prof->name), 0, 1));
                }
            }
            $view->with([
                'streamingProfileActive' => $prof,
                'streamingProfileLetter' => $letter,
                'streamingSection' => request()->routeIs('app.home') ? strtolower((string) request()->query('section', 'todas')) : 'todas',
            ]);
        };

        View::composer('components.streaming-shell', $streamingComposer);
        View::composer('app.home', $streamingComposer);
    }
}
