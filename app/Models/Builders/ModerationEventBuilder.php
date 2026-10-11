<?php

namespace App\Models\Builders;

use App\Models\ModerationEvent;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** Reject bulk Eloquent mutations that bypass model events; case retention uses database FK cascades.
 *
 * @extends Builder<ModerationEvent>
 */
class ModerationEventBuilder extends Builder
{
    /** Audit records can only be appended, never changed through a bulk model update.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.');
    }

    /** Bulk model deletion is disallowed; parent retention owns history deletion. */
    public function delete(): mixed
    {
        throw new LogicException('Moderationsereignisse dürfen nicht gelöscht werden.');
    }

    /** Force deletion must not bypass append-only history either. */
    public function forceDelete(): mixed
    {
        throw new LogicException('Moderationsereignisse dürfen nicht gelöscht werden.');
    }

    /** Numeric mutation must not bypass audit immutability.
     *
     * @param  string  $column
     * @param  int|float  $amount
     * @param  array<string, mixed>  $extra
     */
    public function increment($column, $amount = 1, array $extra = []): int
    {
        throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.');
    }

    /** Numeric mutation must not bypass audit immutability.
     *
     * @param  string  $column
     * @param  int|float  $amount
     * @param  array<string, mixed>  $extra
     */
    public function decrement($column, $amount = 1, array $extra = []): int
    {
        throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.');
    }

    /** Bulk numeric changes are forbidden for append-only history.
     *
     * @param  array<string, int|float>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): int
    {
        throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.');
    }

    /** Bulk numeric changes are forbidden for append-only history.
     *
     * @param  array<string, int|float>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): int
    {
        throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.');
    }

    /** An upsert may replace existing history, so callers must append with create or insert instead.
     *
     * @param  array<array<string, mixed>>  $values
     * @param  array<string>|string  $uniqueBy
     * @param  array<string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw new LogicException('Moderationsereignisse dürfen nicht geändert werden.');
    }
}
