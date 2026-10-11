<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use App\Support\DemoProfiles;
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

        return view('oasis.mitglied');
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

    /**
     * Public profile in the guest preview.
     *
     * Only sample profiles from {@see DemoProfiles} exist; any other handle is a 404 instead of a made-up profile.
     */
    public function profile(string $handle): View
    {
        abort_unless(config('oasis.demo_pages'), 404);

        $profile = DemoProfiles::find($handle);
        abort_if($profile === null, 404);

        return view('oasis.profil', [
            'handle' => strtolower($handle),
            'profile' => $profile,
        ]);
    }
}
