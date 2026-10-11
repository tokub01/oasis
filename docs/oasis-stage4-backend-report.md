# Stage 4a backend handoff

Implemented the complete backend assignment from `stage4-moderation-brief.md`, including both addenda. No commits,
pushes, dependencies or config keys added. Claude owns the staff/public UI and Livewire queue; those files were not
replaced with placeholders because the real views were already present when integration tests ran.

## Delivered

- String-backed roles with German labels and User cast; database and new model objects default to member. Role is
  excluded from public mass assignment. Admin HTTP role management and `oasis:role {email} {role}` operator command.
- Staff middleware requires moderator/admin plus configured, confirmed Fortify 2FA. German redirect for missing 2FA,
  403 for members. Policies also protect actions, including the second-person rule even for admins.
- Polymorphic cases, human statements of reasons, complaints and append-only events, with stable content_report morph
  alias. Content report observer opens a unique case; existing notices are backfilled. Public report storage includes
  case creation in the same transaction. Decisions, assignment and complaint transitions lock cases and write audit
  entries in the same transaction.
- Recipient-bound public signed forms plus authenticated affected-account forms. Stored identity overrides any email
  posted by a client. German validation, five complaint POSTs/minute/IP, six months from notice, duplicate protection,
  independent complaints from both parties, second-human review, replacement decisions with supersedes history.
- German Berlin-time notices: reporter receives outcome/remedies, affected account receives full Art. 17 statement;
  reporter complaint view redacts full facts/reasons too. Complaint result includes human review reasons. Mails run
  after commit; operator retry `oasis:moderation-notify` skips recorded deliveries and obsolete decisions.
- Audit model events and dedicated Eloquent builder prohibit mutation, including bulk update/delete/upsert/numeric
  writes. Retention deletes cases and cascades dependent history; unresolved cases and current complaint windows are
  protected. Report evidence stays until its case is pruned. Null records_years disables pruning.
- TransparencyQuery aggregates half-open periods, outcomes/grounds, received/overturned complaints and median initial
  decision time without recipient identities. Route/view data contract is in `docs/oasis-moderation.md`.

Schema additions beyond the proposal: decisions.notified_at and complaints.review_explanation. Nullable user FKs
preserve case evidence after account deletion. Claude was notified before implementation of these additions.

## Verification

- `./vendor/bin/sail artisan test --parallel --processes=1`: **239 passed, 1402 assertions**, 12.24 seconds.
  Includes **30 backend moderation tests**. Test slot was explicitly granted by Claude in stage4-test-slot.txt;
  no PHP test process overlapped Claude's tests. UI-specific tests have not been authored yet in this checkout.
- Pint `--test` on all **52 owned PHP files**: passed (including the backend test file).
- PHPStan level 7 on the owned application/bootstrap/routes/migration files: passed. Tests are outside the project's
  configured PHPStan paths; an exploratory run including Pest closures reported unsupported test binding, so the final
  static check used the project's normal source scope.
- `sail artisan migrate`: all three new migrations applied to the development database.
- `sail artisan route:list --path=moderation`: all 12 routes registered.
- `sail artisan view:cache`: passed with Claude's real moderation views.
- `git diff --check`: passed.

Initial verification uncovered and fixed: encrypted test data evaluated before application boot; MySQL denying trigger
creation without SUPER with binary logging on; missing in-memory User role default; stale cached relation in one test.
No database privileges or server settings were changed. Append-only guarantees cover Eloquent access; raw SQL is reserved
for trusted operators/migrations. External mail and DB delivery evidence cannot be atomic, so a process crash between
sending and recording may produce duplicate delivery on retry. These boundaries are documented.

## Remaining integration

Claude reviews and commits. Actual post/profile/chat enforcement, in-app reports and the public transparency UI remain
later stages as scoped by the brief. No admin account was promoted and no live moderation notices were sent.
Claude added persistent EnsureStaff protection for Livewire updates in AppServiceProvider; this is Claude's file change,
not included in the owned file list below.

## Owned files created or changed

- `app/Actions/Moderation/AssignCase.php`
- `app/Actions/Moderation/ChangeRole.php`
- `app/Actions/Moderation/DecideCase.php`
- `app/Actions/Moderation/NotifyComplaint.php`
- `app/Actions/Moderation/NotifyDecision.php`
- `app/Actions/Moderation/OpenCase.php`
- `app/Actions/Moderation/ReviewComplaint.php`
- `app/Actions/Moderation/SubmitComplaint.php`
- `app/Console/Commands/OasisRole.php`
- `app/Console/Commands/RetryModerationNotices.php`
- `app/Enums/Moderation/CaseStatus.php`
- `app/Enums/Moderation/ComplaintStatus.php`
- `app/Enums/Moderation/EventType.php`
- `app/Enums/Moderation/Ground.php`
- `app/Enums/Moderation/Outcome.php`
- `app/Enums/Moderation/Priority.php`
- `app/Enums/Role.php`
- `app/Http/Controllers/Moderation/CaseController.php`
- `app/Http/Controllers/Moderation/ComplaintController.php`
- `app/Http/Controllers/Moderation/RoleController.php`
- `app/Http/Controllers/Oasis/ContentReportController.php`
- `app/Http/Middleware/EnsureStaff.php`
- `app/Http/Requests/Moderation/AssignCaseRequest.php`
- `app/Http/Requests/Moderation/ComplaintRequest.php`
- `app/Http/Requests/Moderation/DecisionRequest.php`
- `app/Http/Requests/Moderation/ModerationRequest.php`
- `app/Http/Requests/Moderation/ReviewComplaintRequest.php`
- `app/Http/Requests/Moderation/RoleRequest.php`
- `app/Mail/Moderation/ComplaintResult.php`
- `app/Mail/Moderation/DecisionNotice.php`
- `app/Models/Builders/ModerationEventBuilder.php`
- `app/Models/ContentReport.php`
- `app/Models/ModerationCase.php`
- `app/Models/ModerationComplaint.php`
- `app/Models/ModerationDecision.php`
- `app/Models/ModerationEvent.php`
- `app/Models/User.php`
- `app/Observers/ContentReportObserver.php`
- `app/Policies/ModerationCasePolicy.php`
- `app/Policies/ModerationComplaintPolicy.php`
- `app/Policies/ModerationDecisionPolicy.php`
- `app/Providers/ModerationServiceProvider.php`
- `app/Queries/Moderation/TransparencyQuery.php`
- `app/Support/Moderation/ComplaintAccess.php`
- `bootstrap/app.php`
- `bootstrap/providers.php`
- `database/migrations/2026_10_11_300000_add_role_to_users_table.php`
- `database/migrations/2026_10_11_300001_create_moderation_tables.php`
- `database/migrations/2026_10_11_300002_protect_and_backfill_moderation.php`
- `docs/oasis-moderation.md`
- `docs/oasis-product-plan.md`
- `docs/oasis-stage4-backend-report.md`
- `docs/oasis-website.md`
- `resources/views/moderation/mail/complaint-result.blade.php`
- `resources/views/moderation/mail/decision.blade.php`
- `routes/moderation.php`
- `routes/web.php`
- `tests/Feature/Moderation/BackendTest.php`
