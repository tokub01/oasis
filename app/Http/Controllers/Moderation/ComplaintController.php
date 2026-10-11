<?php

namespace App\Http\Controllers\Moderation;

use App\Actions\Moderation\ReviewComplaint;
use App\Actions\Moderation\SubmitComplaint;
use App\Enums\Moderation\Ground;
use App\Enums\Moderation\Outcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\ComplaintRequest;
use App\Http\Requests\Moderation\ReviewComplaintRequest;
use App\Models\ModerationComplaint;
use App\Models\ModerationDecision;
use App\Support\Moderation\ComplaintAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/** Public recipient-bound complaint forms and second-person staff review endpoints. */
class ComplaintController extends Controller
{
    /** Show the original decision and separate, signed POST action to its recipient. */
    public function create(Request $request, ModerationDecision $decision, ComplaintAccess $access): View
    {
        $recipient = $access->recipient($request, $decision);
        Gate::authorize('submitComplaint', [$decision, $recipient]);
        $displayDecision = clone $decision;
        if ($request->query('recipient') === 'reporter' && ! $request->routeIs('moderation.complaint.account')) {
            $displayDecision->facts = '';
            $displayDecision->explanation = 'Deine Meldung wurde geprüft. Ergebnis: '.$decision->outcome->label().'.';
            $displayDecision->rule_reference = '';
        }

        return view('moderation.complaint.create', [
            'decision' => $displayDecision->load('case'),
            'deadline' => $decision->notified_at->copy()->addMonthsNoOverflow(6),
            'storeUrl' => $request->routeIs('moderation.complaint.account')
                ? route('moderation.complaint.account.store', $decision)
                : URL::signedRoute('moderation.complaint.store', ['decision' => $decision->id, 'recipient' => $request->query('recipient')]),
        ]);
    }

    /** Store the authenticated or signed recipient's reason, without accepting a supplied identity. */
    public function store(ComplaintRequest $request, ModerationDecision $decision, ComplaintAccess $access, SubmitComplaint $action): View
    {
        $action->handle($decision, $access->recipient($request, $decision), $request->validated('reason'), $request->user());

        return view('moderation.complaint.thanks');
    }

    /** Let staff inspect the complaint; the original decider may read but cannot decide it. */
    public function show(ModerationComplaint $complaint): View
    {
        Gate::authorize('view', $complaint);

        return view('moderation.complaints.show', [
            'complaint' => $complaint->load(['case', 'decision.decider', 'submitter', 'reviewer']),
            'outcomes' => Outcome::cases(),
            'grounds' => Ground::cases(),
        ]);
    }

    /** Have a different staff member confirm or replace the decision with reasons. */
    public function decide(ReviewComplaintRequest $request, ModerationComplaint $complaint, ReviewComplaint $action): RedirectResponse
    {
        $action->handle($complaint, $request->user(), $request->validated());

        return to_route('moderation.complaints.show', $complaint)->with('status', 'Die Beschwerde wurde geprüft und das Ergebnis mitgeteilt.');
    }
}
