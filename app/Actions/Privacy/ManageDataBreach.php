<?php

namespace App\Actions\Privacy;

use App\Mail\Privacy\DataBreachAlert;
use App\Models\DataBreach;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records data breaches and every later step in their log (Art. 33 Abs. 5 DSGVO). A breach with risk alerts the
 * operator inbox at once (title and deadline, no details). Reports and notices are made by a person and recorded here.
 */
class ManageDataBreach
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function record(User $admin, array $input): DataBreach
    {
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:160'],
            'discovered_at' => ['required', 'date', 'before_or_equal:now'],
            'description' => ['required', 'string', 'max:5000'],
            'data_categories' => ['required', 'string', 'max:1000'],
            'affected_people' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'risk' => ['required', Rule::in(array_keys(DataBreach::RISKS))],
            'consequences' => ['required', 'string', 'max:5000'],
            'measures' => ['required', 'string', 'max:5000'],
        ], [
            'discovered_at.before_or_equal' => 'Der Zeitpunkt der Entdeckung kann nicht in der Zukunft liegen.',
        ])->validate();

        $breach = DataBreach::create($data + ['created_by' => $admin->id, 'log' => [$this->entry($admin, 'Datenpanne erfasst.')]]);
        if ($breach->mustReport()) {
            Mail::to(config('oasis.mail.operator'))->send(new DataBreachAlert($breach));
        }

        return $breach;
    }

    /** @throws ValidationException */
    public function recordAuthorityReport(DataBreach $breach, User $admin, string $reference): void
    {
        $reference = $this->text($reference, 'reference', 3, 160, 'Gib Behörde und Aktenzeichen an (3 bis 160 Zeichen).');
        $late = now()->greaterThan($breach->reportDueAt()) ? ' Nach Ablauf der 72 Stunden – Gründe für die Verzögerung in der Meldung angeben (Art. 33 Abs. 1 DSGVO).' : '';
        $breach->forceFill(['authority_reported_at' => now(), 'authority_reference' => $reference,
            'log' => [...$breach->log, $this->entry($admin, 'Meldung an die Aufsichtsbehörde vermerkt: '.$reference.'.'.$late)]])->save();
    }

    public function recordPeopleNotified(DataBreach $breach, User $admin): void
    {
        $breach->forceFill(['people_notified_at' => now(),
            'log' => [...$breach->log, $this->entry($admin, 'Benachrichtigung der Betroffenen vermerkt (Art. 34 DSGVO).')]])->save();
    }

    /** @throws ValidationException */
    public function addNote(DataBreach $breach, User $admin, string $note): void
    {
        $note = $this->text($note, 'note', 3, 2000, 'Ein Nachtrag braucht 3 bis 2000 Zeichen.');
        $breach->forceFill(['log' => [...$breach->log, $this->entry($admin, $note)]])->save();
    }

    /** @throws ValidationException when a report or notice the risk requires is still missing */
    public function close(DataBreach $breach, User $admin): void
    {
        if ($breach->reportOutstanding() || ($breach->mustNotifyPeople() && $breach->people_notified_at === null)) {
            throw ValidationException::withMessages(['close' => 'Erst abschließen, wenn Meldung und Benachrichtigung vermerkt sind, die das Risiko verlangt.']);
        }
        $breach->forceFill(['closed_at' => now(), 'log' => [...$breach->log, $this->entry($admin, 'Abgeschlossen.')]])->save();
    }

    /** @return array{at: string, by: string, text: string} */
    private function entry(User $admin, string $text): array
    {
        return ['at' => now()->toIso8601String(), 'by' => $admin->display_name, 'text' => $text];
    }

    private function text(string $value, string $field, int $min, int $max, string $message): string
    {
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $value;
    }
}
