<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer([
            'layouts.partials.sidebar', 'student-skipping-grade._workspace-start',
        ], function ($view) {
            $request = request();
            if (!$request->attributes->has('skippingPendingCount')) {
                $request->attributes->set('skippingPendingCount', app(\App\Services\SkippingGradeNotifications::class)->pendingCount($request->user()));
            }
            $view->with('skippingPendingCount', $request->attributes->get('skippingPendingCount'));
        });
    }
}
