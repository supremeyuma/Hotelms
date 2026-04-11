<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentRecordService
{
    protected ?array $columns = null;

    public function hasTable(): bool
    {
        return Schema::hasTable('payments');
    }

    public function hasColumn(string $column): bool
    {
        return $this->hasTable() && isset($this->getColumns()[$column]);
    }

    public function findByReference(?string $reference): ?Payment
    {
        if (! $reference || ! $this->hasTable()) {
            return null;
        }

        $query = Payment::query();
        $matched = false;

        foreach (['reference', 'payment_reference', 'flutterwave_tx_ref', 'external_reference'] as $column) {
            if (! $this->hasColumn($column)) {
                continue;
            }

            $matched
                ? $query->orWhere($column, $reference)
                : $query->where($column, $reference);

            $matched = true;
        }

        return $matched ? $query->first() : null;
    }

    public function isIdempotencyKeyProcessed(string $idempotencyKey): bool
    {
        return $this->hasColumn('idempotency_key')
            && Payment::query()->where('idempotency_key', $idempotencyKey)->exists();
    }

    public function upsert(array $identity, array $attributes): ?Payment
    {
        if (! $this->hasTable()) {
            return null;
        }

        $identity = $this->filterIdentity($identity, $attributes);

        if ($identity === []) {
            return null;
        }

        $values = $this->filterAttributes($attributes);

        if ($values === []) {
            return $this->findByIdentity($identity, $attributes);
        }

        $timestamp = now();

        if ($this->hasColumn('created_at')) {
            $values['created_at'] = $timestamp;
        }

        if ($this->hasColumn('updated_at')) {
            $values['updated_at'] = $timestamp;
        }

        DB::table('payments')->updateOrInsert($identity, $values);

        return $this->findByIdentity($identity, array_merge($attributes, $identity));
    }

    public function update(Payment $payment, array $attributes): Payment
    {
        if (! $this->hasTable() || ! $this->hasColumn('id')) {
            return $payment;
        }

        $values = $this->filterAttributes($attributes);

        if ($this->hasColumn('updated_at')) {
            $values['updated_at'] = now();
        }

        if ($values !== []) {
            DB::table('payments')
                ->where('id', $payment->id)
                ->update($values);
        }

        return $payment->fresh() ?? $payment;
    }

    public function create(array $attributes): ?Payment
    {
        if (! $this->hasTable()) {
            return null;
        }

        $values = $this->filterAttributes($attributes);
        $timestamp = now();

        if ($this->hasColumn('created_at')) {
            $values['created_at'] = $timestamp;
        }

        if ($this->hasColumn('updated_at')) {
            $values['updated_at'] = $timestamp;
        }

        if ($values === []) {
            return null;
        }

        $id = DB::table('payments')->insertGetId($values);

        return Payment::query()->find($id);
    }

    protected function filterIdentity(array $identity, array $attributes): array
    {
        $filtered = [];

        foreach ($identity as $key => $value) {
            if ($value === null || ! $this->hasColumn($key)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        if ($filtered !== []) {
            return $filtered;
        }

        foreach (['reference', 'payment_reference', 'transaction_ref'] as $column) {
            $value = $attributes[$column] ?? null;

            if ($value !== null && $this->hasColumn($column)) {
                return [$column => $value];
            }
        }

        return [];
    }

    protected function filterAttributes(array $attributes): array
    {
        $filtered = [];
        $columns = $this->getColumns();

        if (($attributes['room_id'] ?? null) === null && isset($attributes['booking_id']) && isset($columns['room_id'])) {
            $attributes['room_id'] = Booking::query()->whereKey($attributes['booking_id'])->value('room_id');
        }

        foreach ($attributes as $key => $value) {
            if (! isset($columns[$key])) {
                continue;
            }

            if (in_array($key, ['meta', 'raw_response'], true) && is_array($value)) {
                $filtered[$key] = json_encode($value, JSON_THROW_ON_ERROR);
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    protected function findByIdentity(array $identity, array $attributes): ?Payment
    {
        $query = Payment::query();
        $matched = false;

        foreach ($identity as $key => $value) {
            $matched
                ? $query->where($key, $value)
                : $query->where($key, $value);

            $matched = true;
        }

        if ($matched) {
            return $query->first();
        }

        return $this->findByReference($attributes['reference'] ?? $attributes['payment_reference'] ?? null);
    }

    protected function getColumns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        if (! Schema::hasTable('payments')) {
            return $this->columns = [];
        }

        return $this->columns = array_flip(Schema::getColumnListing('payments'));
    }
}
