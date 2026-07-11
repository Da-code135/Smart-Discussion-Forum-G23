<?php

namespace App\Providers;

use App\Models\User;
use App\Services\MessageStatusService;
use Illuminate\Support\Facades\Gate;
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
        // System Administrators transcend tenancy: they pass every Gate check.
        // This guarantees a System Admin can access any admin action even when
        // a dedicated Policy class is missing (e.g. BlacklistRecord, Warning).
        Gate::after(function (User $user, string $ability, ?bool $result) {
            if ($result !== null) {
                return $result;
            }

            if ($user->isSystemAdmin()) {
                return true;
            }
        });

        // Share unread message count with the sidebar on every page load.
        View::composer('components.sidebar', function ($view) {
            $user = auth()->user();

            if ($user) {
                $unreadCount = app(MessageStatusService::class)
                    ->getUnreadCounts($user->id)['total'];

                $view->with('unreadMessageCount', $unreadCount);
            } else {
                $view->with('unreadMessageCount', 0);
            }
        });
    }
}
