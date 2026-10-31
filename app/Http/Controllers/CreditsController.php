<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->refresh();

        return response()->json([
            'balance' => $user->credits,
            'recent' => $this->rows($user->creditTransactions()->latest()->limit(10)->get()),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $rows = $request->user()
            ->creditTransactions()
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => $this->rows(collect($rows->items())),
            'next_cursor' => $rows->nextPageUrl(),
        ]);
    }

    /**
     * Rewarded-ad credit grant. Contract for the AdMob SSV flow: in production
     * the `ref` carries the SSV transaction id verified server-side — this
     * endpoint exists now so the earn→spend loop is testable in free_mode.
     */
    public function earn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['rewarded_ad', 'daily_bonus'])],
            'ref' => ['nullable', 'string', 'max:128'],
        ]);

        // A ref may only grant once — replay-safe for the SSV flow.
        if (! empty($data['ref'])) {
            $existing = CreditTransaction::where('user_id', $request->user()->id)
                ->where('ref', $data['ref'])
                ->exists();
            abort_if($existing, 409, 'reward already claimed');
        }

        $amount = (int) config("cricketamigo.flags.credits.{$data['kind']}_amount", 0);
        abort_if($amount <= 0, 422, 'unknown reward');

        $tx = CreditTransaction::record(
            $request->user(),
            $amount,
            $data['kind'],
            $data['ref'] ?? null,
        );

        return response()->json([
            'balance' => $tx->balance_after,
            'granted' => $amount,
        ], 201);
    }

    private function rows($rows): array
    {
        return $rows->map(fn (CreditTransaction $t) => [
            'id' => $t->id,
            'amount' => $t->amount,
            'balance_after' => $t->balance_after,
            'kind' => $t->kind,
            'created_at' => $t->created_at,
        ])->all();
    }
}
