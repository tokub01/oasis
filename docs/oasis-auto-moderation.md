# Oasis automated content check (stage 10)

Public texts are checked automatically for insults, hate, threats, extremism, harassment (Mobbing) and sexual content.
The result is a hint for the member ("Nochmal lesen?"), a report for the moderation team and, for clear cases, a hold
until a human reviewed the Tropfen – a human always decides (DSA Art. 17, GDPR Art. 22). Encrypted chat is never checked (the server cannot read it; participants report messages themselves).

## What is checked, when

| Text | Trigger |
|---|---|
| Tropfen, replies, carries | before sending (tone hint) and after publishing (event `PostPublished`) |
| Profile (bio, location) | when created or changed (`Profile::saved`) |
| Oasis (name, description, house rules) | when created or changed (`Oasis::saved`) |

Images are not checked (the model reads text only).

## How

- `App\Ai\Agents\ContentCheck` – Laravel AI SDK agent with structured output (`insult`, `hate`, `threat`, `extremism`,
  `harassment`, `sexual`, `sexual_minors` as 0..1, `reason` in German). The instructions draw the line between a
  political opinion (allowed, Art. 5 GG; DSA Art. 14(4) asks for proportionate rules) and hate: devaluing or
  dehumanising people, also in right-wing populist wording, is `hate`; Nazi glorification, Holocaust denial,
  banned symbols, slogans and codes and incitement (§§ 86a, 130 StGB) are `extremism`; targeting one person
  (exposing, ridiculing, excluding, rumours, rallying others, doxxing) is `harassment`; offering or asking for sex
  or paid meetings (also coded: "TG", "Taschengeld") is `solicitation`, held like the abusive categories. Provider and model from `config('oasis.moderation')`
  (default Mistral `mistral-small-latest`, EU). The SDK's built-in classification supports only OpenAI/OpenRouter,
  hence a structured agent. **Only the text is sent** – no names, usernames, ids or e-mail addresses.
- `App\Support\Moderation\ContentAssessor` – one entry point; caches a verdict 15 minutes by text hash (pre-check and
  background check cost one request); falls back to `WordListCheck` (`resources/moderation/wortliste.php`).
- `App\Jobs\Moderation\CheckContent` – queued, 5 tries with growing pauses; after the last failure the word list
  decides (`failed`). Publishing never fails because of the check.
- `oasis:moderation-sweep` (every 10 minutes) – re-queues Tropfen of the last two days without a check and checks
  pending for more than 30 minutes.
- `App\Actions\Moderation\ApplyContentVerdict` – stores `content_checks` (scores and reason, never the text) and acts.

## Thresholds (`config/oasis.php`, `moderation`)

| Value | Effect |
|---|---|
| `nudge_threshold` 0.6 (insult/hate/threat) | "Nochmal lesen?" before sending; the member can send unchanged ("So senden") |
| `report_threshold` 0.7 (any category) | moderation case, source `content_check` (urgent for sexual content or threats) |
| `hold_threshold` 0.85 (sexual) or report threshold for `sexual_minors` | Tropfen held (`posts.held_at`): only author and staff see it; Art. 17 mail to the author (`ContentHeldNotice`) |
| `hold_abuse_threshold` 0.8 (insult, hate, threat, extremism, harassment, solicitation) | Tropfen held, like above |

Holding happens **when the Tropfen is sent**: `PublishPost` asks the assessor (usually a cache hit from the
composer's check) and saves a clear case with `held_at` and a pending `content_checks` row, so nobody else ever sees
it; mention hints are not sent (the Tropfen is not visible to them). The background check then confirms it, opens
the case and sends the notice; if it does not confirm the hold and no case exists, the hold is lifted. The author
sees "Zurückgehalten und dem Moderationsteam gemeldet" in the Tropfen with what was detected
(`ContentVerdict::holdReason`, `Post::holdReason`) and the model's reason. Extremism and threats are urgent cases.

A word-list hit reaches the report threshold but never a hold threshold; the list has only single words, so extremist
slogans of several words are left to the language model. Staff see the scores, reason and text in
the case. Deciding "Keine Maßnahme" releases a held Tropfen (`ReleaseHeldContent`, also on an overturned complaint);
the decision mail states that detection was automated.

## Privacy

- Processor: Mistral AI (Paris), EU processing, DPA required; API data not used for training. Mistral keeps API
  requests up to 30 days for abuse monitoring unless zero data retention is agreed – clarify before launch.
- Stored by Oasis: scores, reason, source, time – retention `oasis.privacy.records_years`.
- Privacy policy section 14 and the processor list describe this.
- Tests never call the provider: `phpunit.xml` sets `OASIS_AI_MODERATION=false`; `tests/Feature/AutoModeration`
  enables it with a faked agent.

## Configuration

`.env`: `MISTRAL_API_KEY`, optional `OASIS_AI_MODERATION=false` (word list only), `OASIS_MODERATION_MODEL`.
A queue worker must run in production (`QUEUE_CONNECTION=database`).
