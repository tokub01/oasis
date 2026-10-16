<?php

namespace Tests\Support;

use App\Livewire\Oasis\CancellationDeclaration;
use App\Livewire\Oasis\ContentNotice;
use App\Livewire\Oasis\OrganisationEnquiry;
use App\Livewire\Oasis\WaitlistSignup;
use App\Livewire\Oasis\WithdrawalDeclaration;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Submits the public Oasis forms (Livewire components in app/Livewire/Oasis) the way a visitor would. */
final class PublicForms
{
    /** Form components by the page key used in route names (oasis.<page>, oasis.bestaetigung.<page>). */
    public const COMPONENTS = [
        'gruendung' => WaitlistSignup::class,
        'kuendigen' => CancellationDeclaration::class,
        'widerrufen' => WithdrawalDeclaration::class,
        'melden' => ContentNotice::class,
        'gemeinsam' => OrganisationEnquiry::class,
    ];

    /**
     * Fill the form fields and press submit. Errors are reported as form.<field> (and "throttle").
     *
     * @param  array<string, mixed>  $fields  field name => value, as typed into the form
     */
    public static function submit(string $page, array $fields): Testable
    {
        $form = Livewire::test(self::COMPONENTS[$page]);
        foreach ($fields as $field => $value) {
            $form->set('form.'.$field, $value);
        }

        return $form->call('submit');
    }
}
