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
