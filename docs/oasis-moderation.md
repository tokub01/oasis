# Oasis moderation backend (stage 4a)

Roles: `App\Enums\Role::{Member,Moderator,Admin}`; `label()` and `isStaff()`; cast on `User::role`.
Role is deliberately excluded from user mass assignment. All moderation enums live in `App\Enums\Moderation`:
`CaseStatus`, `Outcome`, `Ground`, `Priority`, `ComplaintStatus`, `EventType`; PascalCase cases and German `label()`.

## Pages and authorization (Livewire, stage 12)

Staff pages use `auth`, `verified`, `staff` (staff role and confirmed 2FA; `staff` is persistent Livewire middleware,
so it also runs on every Livewire update request); members get 403, unconfirmed staff redirect to `app.settings`
with German flash `status`. Each component checks its gate/policy on render and again in every action before
validating; the Actions check once more inside their transaction.

| URL | Name | Component | Actions (gate/policy) |
|---|---|---|---|
| /moderation | moderation.index | `Moderation\Overview` (embeds `Moderation\Queue`) | – (viewModeration) |
| /moderation/cases/{case} | moderation.cases.show | `Moderation\CaseDetail` | `assign` (assign), `decide` (decide) |
| /moderation/complaints/{complaint} | moderation.complaints.show | `Moderation\ComplaintReview` | `review` (reviewComplaint) |
| /moderation/roles | moderation.roles.index | `Moderation\Roles` | `save(userId)` (manageRoles; not the own account) |
| /moderation/complaint/{decision} | moderation.complaint.create | `Moderation\ComplaintForm` | `submit` (signed link + submitComplaint + 5/min per IP) |
| /app/moderation/complaint/{decision} | moderation.complaint.account | `Moderation\ComplaintForm` (`via` = account) | `submit` (auth, affected account + submitComplaint + 5/min per IP) |

Public signed URLs include `recipient=reporter|subject`, never an email. The `signed` middleware checks the link
when the form opens; the selector is then a `#[Locked]` property, and `App\Support\Moderation\ComplaintAccess`
resolves the stored email server-side on every action. The email never enters the page snapshot.

## Form data

`App\Livewire\Moderation\Forms\DecisionForm` holds the statement of reasons (DSA Art. 17): `outcome`, `ground`,
`rule_reference` (255), `facts` and `explanation` (10000), all required; errors are keyed `decision.<field>`. The
case page uses it for the first decision. The complaint review needs `result=upheld|overturned`; "upheld" needs
`explanation` (10000), "overturned" a complete DecisionForm whose explanation is also the reason for the result.
Assignment: `assigned_to` must be an existing staff id. Complaint: `reason` (10000); one per decision and recipient.
Role change: a Role enum value per account (`roles.<id>`). German messages and field names come from
`App\Livewire\Moderation\Concerns\ModerationValidation`. Results are shown in the component (`role="status"`).

Models: `ModerationCase`, `ModerationDecision`, `ModerationComplaint`, `ModerationEvent` in `App\Models`.
Relations use `case`, `decider`, `assignee`, `subjectUser`, `reviewer`, `latestDecision`, `supersedesDecision`.
All proposed table/column names preserved. Added `moderation_decisions.notified_at` (actual notice timestamp) and
`moderation_complaints.review_explanation` (stored complaint result reasons). User references become null on account
deletion; case deletion cascades its history. Source morph uses stable `content_report` alias.

Moderators see the case source's reason and URL, but reporter name/email are hidden on the model serialization;
there is no export route. They must not be rendered by staff templates.

## Workflow, notices and retention

ContentReportObserver creates a unique case and its opened event. HTTP report storage includes this in the report's
transaction; a migration backfills pre-stage notices. Assignment and decisions lock the case; every state change
writes its event in the same transaction. Actions also check policies (including the second-person review rule).
One complaint per decision/recipient is accepted; duplicate submission gets a German validation message. Independent
party complaints may be open together; the case remains complaint_open until all have received a human review. Only the latest decision is open to complaint; an overturned decision
creates a new history entry referencing the latest decision via `supersedes_decision_id`. Historical pending
complaints retain their original decision reference and second-person check; an upheld historical complaint does not
undo subsequent decisions. A replacement has its own notice/window.

DecisionNotice sends reporters only an outcome and remedies, affected accounts a full Art. 17 statement. The public
reporter complaint view also redacts facts and full reasons. ComplaintResult includes the human review explanation.
Mail is sent after commit: a transport failure retains the decision and is recoverable with
`./vendor/bin/sail artisan oasis:moderation-notify`. Each successful recipient is recorded with a notified event;
recorded deliveries are skipped on retry; superseded decisions are not sent late. External transport and DB delivery evidence cannot be made one atomic
operation; a process crash between them can cause duplicate delivery. `notified_at` advances on each successful
party's notice, giving all parties at least six months. No tracking or email export endpoint is provided.

Audit updates/deletes are rejected by model events and a dedicated Eloquent builder, including bulk Eloquent writes.
Parent case deletion cascades history for retention. Raw DB/SQL access is reserved for trusted migrations/operators;
MySQL triggers were avoided because the Sail database user cannot create them with binary logging enabled. Account
FK references become null without deleting case history. Decided/closed cases prune only after the configured years
from their last state change and after the current decision’s notice window; open/review/complaint cases never prune. ContentReport
pruning waits until the case is gone. A null records_years setting disables pruning as on the other public forms.

## Eskalation (threat to life, child abuse)

Two situations leave the normal queue (`App\Enums\Moderation\Escalation`). **Oasis never contacts an authority
itself**; it secures the evidence and guides staff, who report by hand and record it.

| Type | Trigger | Effect |
|---|---|---|
| `threat_to_life` – Gefahr für Leib und Leben (Art. 18 DSA) | staff, "Eskalieren" on the case | urgent, on top of the queue, evidence secured, checklist (110 in acute danger, otherwise the competent authority) |
| `child_abuse` – Missbrauchsdarstellung (§§ 184b, 184c StGB) | staff, or automatically when the text check scores `sexual_minors` ≥ `report_threshold` | as above, plus quarantine: the Tropfen is held and `posts.quarantined_at` set – `PostImageController` serves its images to nobody, staff included; the card shows "Bilder gesperrt" |

- `EscalateCase` (once per case, locked) sets `escalation`, `escalated_at`, `escalated_by` (null = automatic), priority
  urgent, calls `SecureEvidence`, quarantines, writes the event and mails `EscalationAlert` to `oasis.mail.moderation`
  (type and link only, no content, no names).
- `SecureEvidence` stores `moderation_evidence` rows: a text snapshot with the account as it is now (id, username,
  name, e-mail, registration) and a **copy** of each image below `evidence/case-<id>/` with SHA-256 and size. Evidence
  is independent of the content, so deleting the Tropfen or the account keeps it (Art. 17 Abs. 3 lit. e DSGVO).
- `oasis.moderation.evidence_days` (90): `ModerationEvidence::prunable` deletes rows and files afterwards.
- Case page: checklist, the authority contact from `oasis.moderation.authority_contact` (null = still to be decided,
  shown as such), "Meldung vermerken" (authority and reference, staff), evidence as metadata with text behind a
  disclosure; admins only: ZIP download for the police (`moderation.cases.evidence`, files plus `beweise.json`),
  extend by 90 days, delete evidence, lift quarantine. Every step is a case event (`Escalated`, `AuthorityReported`,
  `EvidenceDownloaded`, `EvidenceExtended`, `EvidenceDeleted`, `QuarantineLifted`).
- Tests: `tests/Feature/Moderation/EscalationTest.php`.

## Operator and transparency API

`./vendor/bin/sail artisan oasis:role EMAIL member|moderator|admin` bootstraps the first admin; staff still need 2FA.
`App\Queries\Moderation\TransparencyQuery::forPeriod(CarbonInterface $from, CarbonInterface $until): array`
uses [from, until). Keys: `notices_received`, `decisions_by_outcome`, `decisions_by_ground`, `complaints_received`,
`complaints_overturned` (reviewed in the period), `median_time_to_decision_seconds` (initial decisions only; null
without decisions). Outcome/ground maps include complaint replacement decisions and omit zero categories.
No new config keys or dependencies were added. Future source models need a morph alias and recipient adapter;
posts/chat enforcement is outside stage 4a, so outcomes currently record a decision rather than changing content.
