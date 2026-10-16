<?php

namespace App\Http\Controllers\Oasis;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Shows the confirmation page after a successful form submission.
 *
 * Each form component (app/Livewire/Oasis) redirects here with the flash key `oasis.submitted.<form>`. Without that
 * key (someone opens the URL directly) the page is a 404, so a confirmation is never shown for something that did not
 * happen.
 * One route per form is registered in routes/oasis.php with the form name as route default.
 */
class ConfirmationController extends Controller
{
    /** Forms that have a confirmation page; each has a view `oasis.bestaetigung.<form>`. */
    public const FORMS = ['gruendung', 'kuendigen', 'widerrufen', 'melden', 'gemeinsam'];

    /**
     * @param  string  $form  One of {@see self::FORMS}, set as route default.
     */
    public function __invoke(string $form): View
    {
        abort_unless(session('oasis.submitted.'.$form), 404);

        return view('oasis.bestaetigung.'.$form);
    }
}
