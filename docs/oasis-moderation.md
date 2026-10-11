# Oasis moderation backend (stage 4a)

Roles: `App\Enums\Role::{Member,Moderator,Admin}`; `label()` and `isStaff()`; cast on `User::role`.
Role is deliberately excluded from user mass assignment. All moderation enums live in `App\Enums\Moderation`:
`CaseStatus`, `Outcome`, `Ground`, `Priority`, `ComplaintStatus`, `EventType`; PascalCase cases and German `label()`.

## Routes and authorization

Staff routes use `auth`, `verified`, `staff` (staff role and confirmed 2FA); members get 403, unconfirmed staff
redirect to `app.settings` with German flash `status`. Every controller/action also checks its gate/policy.

| Method | URL | Name | Gate/policy |
|---|---|---|---|
| GET | /moderation | moderation.index | viewModeration |
| GET | /moderation/cases/{case} | moderation.cases.show | view on case |
| POST | /moderation/cases/{case}/assign | moderation.cases.assign | assign on case |
| POST | /moderation/cases/{case}/decide | moderation.cases.decide | decide on case |
| GET | /moderation/complaints/{complaint} | moderation.complaints.show | view on complaint |
| POST | /moderation/complaints/{complaint}/decide | moderation.complaints.decide | reviewComplaint on complaint |
| GET | /moderation/roles | moderation.roles.index | manageRoles |
| PUT | /moderation/roles/{user} | moderation.roles.update | manageRoles |
| GET | /moderation/complaint/{decision} | moderation.complaint.create | valid signature + submitComplaint |
| POST | /moderation/complaint/{decision} | moderation.complaint.store | valid signature + submitComplaint + throttle |
| GET | /app/moderation/complaint/{decision} | moderation.complaint.account | auth + submitComplaint (affected user) |
| POST | /app/moderation/complaint/{decision} | moderation.complaint.account.store | auth + submitComplaint + throttle |

Public signed URLs include `recipient=reporter|subject`, never an email. The server resolves the recipient from
stored records. Signed GET and POST URLs are separate; use the supplied `storeUrl` as form action.

## Exact view data

| View | Variables and loaded relations |
|---|---|
| moderation.index | no variables; Claude's Livewire queue queries cases with policy authorization |
| moderation.cases.show | `case: ModerationCase` with `source`, `subjectUser`, `assignee`, `decisions.decider`, `complaints.reviewer`, `events.actor`; `staff: Collection<User>` (id, display_name, role); `outcomes: list<Outcome>`, `grounds: list<Ground>` |
| moderation.complaints.show | `complaint: ModerationComplaint` with `case`, `decision.decider`, `submitter`, `reviewer`; `outcomes: list<Outcome>`, `grounds: list<Ground>` |
| moderation.roles.index | `users: LengthAwarePaginator<User>` (id, display_name, email, role), 25/page; `roles: list<Role>` |
| moderation.complaint.create | `decision: ModerationDecision` with `case` (no source/contact exposed); `storeUrl: string`; `deadline: CarbonInterface` |
| moderation.complaint.thanks | no variables; rendered directly following a successful POST |

Assignment: `assigned_to` required existing staff id. Decision: `outcome`, `ground`, `rule_reference` (255),
`facts` and `explanation` (10000). Review: `result=upheld|overturned`, `explanation` (10000); overturned additionally
requires `outcome`, `ground`, `rule_reference`, `facts`. Complaint: `reason` (10000); email comes from authenticated
account or signed recipient, never a user-supplied email. Role update: `role` enum value. Mutations flash `status`.

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

## Operator and transparency API

`./vendor/bin/sail artisan oasis:role EMAIL member|moderator|admin` bootstraps the first admin; staff still need 2FA.
`App\Queries\Moderation\TransparencyQuery::forPeriod(CarbonInterface $from, CarbonInterface $until): array`
uses [from, until). Keys: `notices_received`, `decisions_by_outcome`, `decisions_by_ground`, `complaints_received`,
`complaints_overturned` (reviewed in the period), `median_time_to_decision_seconds` (initial decisions only; null
without decisions). Outcome/ground maps include complaint replacement decisions and omit zero categories.
No new config keys or dependencies were added. Future source models need a morph alias and recipient adapter;
posts/chat enforcement is outside stage 4a, so outcomes currently record a decision rather than changing content.
