<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

#[Fillable(['user_id', 'amount', 'balance_after', 'kind', 'ref', 'meta'])]
class CreditTransaction extends Model
{
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ledger write — always inside a transaction so balance_after is correct.
     * Positive amount credits, negative debits; throws on insufficient funds.
     */
    public static function record(
        User $user,
        int $amount,
        string $kind,
        ?string $ref = null,
        ?array $meta = null,
    ): self {
        if ($amount === 0) {
            throw new InvalidArgumentException('amount cannot be 0');
        }

        return DB::transaction(function () use ($user, $amount, $kind, $ref, $meta) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $balance = $locked->credits + $amount;
            if ($balance < 0) {
                throw new RuntimeException('insufficient credits');
            }
            // credits is intentionally not fillable (users must not be able
            // to self-assign via PUT /me) — ledger writes use forceFill.
            $locked->forceFill(['credits' => $balance])->save();

            return self::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $balance,
                'kind' => $kind,
                'ref' => $ref,
                'meta' => $meta,
            ]);
        });
    }
}
