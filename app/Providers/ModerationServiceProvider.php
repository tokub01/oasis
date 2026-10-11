<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\ChatReport;
use App\Models\ContentReport;
use App\Models\ModerationCase;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use App\Models\User;
use App\Observers\ChatReportObserver;
use App\Observers\ContentReportObserver;
use App\Policies\ModerationCasePolicy;
use App\Policies\ModerationComplaintPolicy;
use App\Policies\ModerationDecisionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Registers the moderation domain without coupling it to authentication or public page controllers. */
class ModerationServiceProvider extends ServiceProvider
{
    /** Bind case policies, role gates, stable report morph names and the complaint limiter. */
    public function boot(): void
    {
        Relation::morphMap([
            'content_report' => ContentReport::class,
            'chat_report' => ChatReport::class,
        ]);
        ContentReport::observe(ContentReportObserver::class);
        ChatReport::observe(ChatReportObserver::class);
        Gate::policy(ModerationCase::class, ModerationCasePolicy::class);
        Gate::policy(ModerationComplaint::class, ModerationComplaintPolicy::class);
        Gate::policy(ModerationDecision::class, ModerationDecisionPolicy::class);
        Gate::define('viewModeration', fn (User $user) => $user->role->isStaff()
            && $user->two_factor_secret && $user->two_factor_confirmed_at !== null);
        Gate::define('manageRoles', fn (User $user) => $user->role === Role::Admin && Gate::forUser($user)->allows('viewModeration'));
        RateLimiter::for('moderation-complaints', fn (Request $request) => Limit::perMinute(5)->by($request->ip())
            ->response(fn () => response('Zu viele Beschwerden. Bitte versuche es in einer Minute erneut.', 429)));
    }
}
