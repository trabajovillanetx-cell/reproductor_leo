<?php

namespace App\Services;

use App\Models\ResellerCredit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bolsa de créditos compartida por revendedores y vendedores (tabla reseller_credits.reseller_id = users.id).
 */
class CreditLedgerService
{
    public function balance(User $user): int
    {
        if (! $user->holdsCredits()) {
            return 0;
        }

        return (int) (ResellerCredit::query()->where('reseller_id', $user->id)->value('credits') ?? 0);
    }

    public function setBalance(User $user, int $credits): void
    {
        if (! $user->holdsCredits()) {
            return;
        }

        ResellerCredit::query()->updateOrCreate(
            ['reseller_id' => $user->id],
            ['credits' => max(0, $credits)]
        );
    }

    /**
     * Transfiere créditos entre titulares (ej. revendedor → hijo). Descuenta del origen y suma al destino.
     */
    public function transfer(User $from, User $to, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        if (! $from->holdsCredits() || ! $to->holdsCredits()) {
            throw ValidationException::withMessages([
                'credits' => 'Solo se pueden transferir créditos entre cuentas de revendedor o vendedor.',
            ]);
        }

        DB::transaction(function () use ($from, $to, $amount): void {
            $ids = [$from->id, $to->id];
            sort($ids);

            foreach ($ids as $id) {
                ResellerCredit::query()->firstOrCreate(
                    ['reseller_id' => $id],
                    ['credits' => 0]
                );
            }

            foreach ($ids as $id) {
                ResellerCredit::query()->where('reseller_id', $id)->lockForUpdate()->first();
            }

            $fromRow = ResellerCredit::query()->where('reseller_id', $from->id)->first();
            $fromBal = (int) ($fromRow?->credits ?? 0);

            if ($fromBal < $amount) {
                throw ValidationException::withMessages([
                    'credits' => 'Créditos insuficientes. Contactá a tu proveedor.',
                ]);
            }

            $fromRow?->decrement('credits', $amount);

            $toRow = ResellerCredit::query()->where('reseller_id', $to->id)->first();
            if ($toRow === null) {
                throw ValidationException::withMessages([
                    'credits' => 'No se pudo acreditar al destinatario.',
                ]);
            }
            $toRow->increment('credits', $amount);
        });
    }

    public function decrement(User $user, int $amount): void
    {
        if ($amount <= 0 || ! $user->holdsCredits()) {
            return;
        }

        DB::transaction(function () use ($user, $amount): void {
            $row = ResellerCredit::query()
                ->where('reseller_id', $user->id)
                ->lockForUpdate()
                ->first();

            $bal = (int) ($row?->credits ?? 0);

            if ($bal < $amount) {
                throw ValidationException::withMessages([
                    'plan_id' => 'Créditos insuficientes. Contactá a tu proveedor.',
                ]);
            }

            if ($row !== null) {
                $row->decrement('credits', $amount);
            }
        });
    }
}
