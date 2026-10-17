# Oasis admin, transparency and member votes (stage 6)

## Access

`routes/admin.php`, prefix `/admin`, names `admin.*`, middleware `auth`, `verified`, `staff` (role + confirmed 2FA,
otherwise redirect to 2FA setup) and `can:administrate` (gate in `GovernanceServiceProvider`: admins with confirmed
2FA, same rule as role management). The staff layout shows "Statistik", "Abstimmungen" and "Zahlungen" to admins.
The first admin is made with `sail artisan oasis:role <email> admin`.

## Statistics modules

| Module | Route | Audience | Query |
|---|---|---|---|
| Admin statistics | `/admin` (period 7/30/90/365 days) | admins | `App\Queries\Admin\AdminStats` |
| Deine Zahlen | `/app/statistik` | the member | `App\Queries\Network\MemberStats` (docs/oasis-network.md) |
| Oase · Statistik | `/oasen/{slug}/statistik` | hosts | `App\Queries\Network\OasisStats` |

`AdminStats` combines:
- members and money from `App\Queries\Membership\MembershipStats` (stage 1/5): accounts, trials active/started/
  converted/expired, paying members per tier, MRR from local subscription data, cancellations, founding seats, gifts;
- network: Tropfen total/period, active authors, oases, 12 weeks of new accounts and Tropfen;
- chat: metadata counts only (identities, conversations, stored ciphertexts, messages in the period);
- moderation: open/in-review cases plus `TransparencyQuery::forPeriod` (notices, decisions, complaints, median time).

All statistics modules show sums only – never lists of people and never content. The payment search below is the one
admin page with personal data, limited to payments.

## Payment search

`/admin/zahlungen` (`admin.payments`, `App\Livewire\Admin\Payments`) serves bookkeeping and questions about payments:

- Searches `membership_payments` by name or e-mail (invoice snapshot or account), username, record number (`#12`),
  Mollie payment id (`tr_…`) or customer id (`cst_…`), with a status filter (incl. "Erstattet").
- "Zahlung bei Mollie prüfen" / "Alle Zahlungen der Person bei Mollie" and "Direkt bei Mollie suchen" (for a typed
  `tr_…`/`cst_…` without a local record) call Mollie read-only through `MollieGateway::lookupPayment`,
  `lookupCustomer` and `customerPayments`. The "Im Mollie-Dashboard öffnen" link is Mollie's own `_links.dashboard`;
  only `https://…mollie.com/` links are shown.
- Every lookup is logged (`Admin Mollie lookup`: admin id, kind, Mollie id – no names or e-mail addresses). Mollie
  errors become a German message; details stay in the log.

Payment records outlive deleted accounts as accounting documents (§ 147 AO, § 257 HGB): `membership_payments.user_id`
is set to null, the invoice snapshot and `mollie_customer_id` stay. `MembershipPayment::prunable` removes paid records
`oasis.privacy.payment_records_years` (8) after the end of their calendar year and unpaid checkout attempts after 90
days.

## Transparency reports (removed)

The public Transparenz page and its admin form were removed in October 2026. The table `transparency_reports` is kept
untouched (no data deleted) in case monthly figures are published again later.

## Member votes

- Admins create votes at `/admin/abstimmungen` (question, explanation, start, end, 2–8 distinct answers).
- Members vote at `/app/abstimmungen` (`app.votes`), delivered as the Livewire 4 component
  `App\Livewire\Governance\Votes` (`Route::livewire`). Gate `vote`: verified account with an active paid or gifted
  membership ("Eine Stimme bei jeder Abstimmung", any tier). Trial accounts can read but not vote.
- **Secret ballot**: `Votes::cast(int $voteId, CastBallot $castBallot)` authorizes via `Gate::authorize('vote')` and
  delegates to `App\Actions\Governance\CastBallot`. `vote_participations` stores only vote, member and day (one ballot
  each via the primary key); the choice only increments `vote_options.votes_count` in the same transaction. No row
  links a member to an option.
- Results (shares and number of ballots) are shown only after the end – in the app, in the admin list and on the
  public Transparenz page; running votes show title, end date and answers.

## Tests

`tests/Feature/Admin/AdminTest.php`.
