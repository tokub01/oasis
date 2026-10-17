<?php

namespace App\Livewire\Admin;

use App\Actions\Privacy\ManageDataBreach;
use App\Models\DataBreach;
use App\Support\Privacy\BreachReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Register of personal data breaches on /admin/datenpannen (route admin.breaches): record a breach, see the 72-hour
 * deadline, copy the drafted report (BreachReport), and record the report, the notice to the people affected, notes
 * and the end. Admins only; Oasis sends nothing to the authority or to the people affected by itself.
 */
class DataBreaches extends Component
{
    /** @var array<string, mixed> new breach */
    public array $form = ['title' => '', 'discovered_at' => '', 'description' => '', 'data_categories' => '',
        'affected_people' => '', 'risk' => 'risk', 'consequences' => '', 'measures' => ''];

    /** @var array<int, string> authority and reference per breach id */
    public array $references = [];

    /** @var array<int, string> note per breach id */
    public array $notes = [];

    #[Locked]
    public ?string $status = null;

    public function mount(): void
    {
        Gate::authorize('administrate');
    }

    public function record(ManageDataBreach $breaches): void
    {
        Gate::authorize('administrate');
        $input = $this->form;
        $input['affected_people'] = $input['affected_people'] === '' ? null : $input['affected_people'];
        $this->resetErrorBag();
        try {
            $breach = $breaches->record(auth()->user(), $input);
        } catch (ValidationException $invalid) {
            foreach ($invalid->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return;
        }
        $this->reset('form');
        $this->status = $breach->mustReport()
            ? 'Erfasst. Die Meldung an die Aufsichtsbehörde ist bis '.$breach->reportDueAt()->timezone('Europe/Berlin')->format('d.m.Y H:i').' Uhr fällig.'
            : 'Erfasst und dokumentiert. Eine Meldung ist bei dieser Einstufung nicht nötig.';
    }

    public function recordReport(int $id, ManageDataBreach $breaches): void
    {
        $this->step($id, 'references', fn (DataBreach $breach) => $breaches->recordAuthorityReport($breach, auth()->user(), $this->references[$id] ?? ''), 'Die Meldung ist vermerkt.');
    }

    public function recordNotified(int $id, ManageDataBreach $breaches): void
    {
        $this->step($id, null, fn (DataBreach $breach) => $breaches->recordPeopleNotified($breach, auth()->user()), 'Die Benachrichtigung ist vermerkt.');
    }

    public function addNote(int $id, ManageDataBreach $breaches): void
    {
        $this->step($id, 'notes', fn (DataBreach $breach) => $breaches->addNote($breach, auth()->user(), $this->notes[$id] ?? ''), 'Der Nachtrag ist gespeichert.');
    }

    public function close(int $id, ManageDataBreach $breaches): void
    {
        $this->step($id, null, fn (DataBreach $breach) => $breaches->close($breach, auth()->user()), 'Die Datenpanne ist abgeschlossen.');
    }

    public function render(): View
    {
        Gate::authorize('administrate');
        $breaches = DataBreach::orderByRaw('closed_at is null desc')->latest('discovered_at')->get();

        return view('livewire.admin.data-breaches', [
            'breaches' => $breaches,
            'reports' => $breaches->mapWithKeys(fn (DataBreach $breach) => [$breach->id => BreachReport::text($breach)]),
            'risks' => DataBreach::RISKS,
        ])->layout('layouts.staff', ['title' => 'Datenpannen', 'active' => 'breaches']);
    }

    /** Run one step on an open breach; a refused input is shown at its field ($bag.$id). */
    private function step(int $id, ?string $bag, callable $action, string $done): void
    {
        Gate::authorize('administrate');
        $breach = DataBreach::whereNull('closed_at')->findOrFail($id);
        $this->resetErrorBag();
        try {
            $action($breach);
        } catch (ValidationException $invalid) {
            $this->addError(($bag ?? 'close').'.'.$id, $invalid->validator->errors()->first());

            return;
        }
        if ($bag !== null) {
            unset($this->{$bag}[$id]);
        }
        $this->status = $done;
    }
}
