<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Public Oasis pages that depend on a feature flag or on data, as opposed to the purely static pages that are
 * registered with Route::view() in routes/oasis.php.
 */
class PageController extends Controller
{
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
