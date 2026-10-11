<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Queries\Admin\PublicTransparency;
use Illuminate\View\View;

/**
 * Public Oasis pages that depend on a feature flag or on data, as opposed to the purely static pages that are
 * registered with Route::view() in routes/oasis.php.
 */
class PageController extends Controller
{
    /**
     * "Mitglied werden" (checkout).
     *
     * Returns 404 while `oasis.checkout_enabled` is false: without a payment provider there must be no order
     * button at all, so nobody can place a sham order (§ 312j BGB button only makes sense with a real checkout).
     */
    public function membership(): View
    {
        abort_unless(config('oasis.checkout_enabled'), 404);

        $user = auth()->user();
        $trialDaysLeft = $user?->trial_ends_at?->isFuture()
            ? (int) ceil(now()->diffInDays($user->trial_ends_at))
            : null;
        $trialEndDate = $user?->trial_ends_at?->timezone('Europe/Berlin')->format('d.m.Y');

        return view('oasis.mitglied', [
            'trialDaysLeft' => $trialDaysLeft,
            'trialEndDate' => $trialEndDate,
        ]);
    }

    /** "Transparenz": published monthly figures and member votes (see PublicTransparency). */
    public function transparency(PublicTransparency $transparency): View
    {
        return view('oasis.transparenz', ['transparency' => $transparency->data()]);
    }

    /**
     * "Entdecken": guest preview of public posts with sample content.
     *
     * Only available while `oasis.demo_pages` is true; the view marks itself as sample and noindex.
     */
    public function discover(): View
    {
        abort_unless(config('oasis.demo_pages'), 404);

        return view('oasis.entdecken');
    }
}
