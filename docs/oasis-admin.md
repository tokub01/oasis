# Oasis admin, transparency and member votes (stage 6)

## Access

`routes/admin.php`, prefix `/admin`, names `admin.*`, middleware `auth`, `verified`, `staff` (role + confirmed 2FA,
otherwise redirect to 2FA setup) and `can:administrate` (gate in `GovernanceServiceProvider`: admins with confirmed
2FA, same rule as role management). The staff layout shows "Statistik", "Transparenz" and "Abstimmungen" to admins.
The first admin is made with `sail artisan oasis:role <email> admin`.

## Statistics modules

| Module | Route | Audience | Query |
|---|---|---|---|
| Admin statistics | `/admin` (period 7/30/90/365 days) | admins | `App\Queries\Admin\AdminStats` |
| Deine Zahlen | `/app/statistik` | the member | `App\Queries\Network\MemberStats` (docs/oasis-network.md) |
| Oase · Statistik | `/oasen/{slug}/statistik` | hosts | `App\Queries\Network\OasisStats` |
| Transparenz | `/transparenz` | public | `App\Queries\Admin\PublicTransparency` |

`AdminStats` combines:
- members and money from `App\Queries\Membership\MembershipStats` (stage 1/5): accounts, trials active/started/
  converted/expired, paying members per tier, MRR from local subscription data, cancellations, founding seats, gifts;
- network: Tropfen total/period, active authors, oases, 12 weeks of new accounts and Tropfen;
- chat: metadata counts only (identities, conversations, stored ciphertexts, messages in the period);
- moderation: open/in-review cases plus `TransparencyQuery::forPeriod` (notices, decisions, complaints, median time).

All modules show sums only – never lists of people and never content.

## Transparency reports

`/admin/transparenz` publishes the figures of one month (`transparency_reports`, unique per month; publishing a
month again corrects it): paying members, sponsored seats, income and cost items (label → euro). The form is
prefilled with the live paying-member count and MRR and the cost labels of the previous report – admins check them
against the books before publishing. The public page shows the newest report; without any report it falls back to
`config('oasis.transparency')`, where null renders "Veröffentlichen wir ab dem Start". Coverage = income / costs.

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
