<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CompanySetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds a client's account statement (running-balance transaction list) and
 * the aged-debt buckets used by Account Balances, Statements and the Aging report.
 */
class ClientLedger
{
    /**
     * Transaction rows between two dates, each with a running balance.
     *
     * @return array{opening: float, rows: Collection, closing: float}
     */
    public static function statement(Client $client, CarbonInterface $from, CarbonInterface $to): array
    {
        $all = static::transactions($client);

        $opening = $all->where('date', '<', $from->toDateString())->sum('signed');
        $running = $opening;

        $rows = $all
            ->filter(fn ($t) => $t['date'] >= $from->toDateString() && $t['date'] <= $to->toDateString())
            ->sortBy(['date', 'sort'])
            ->map(function ($t) use (&$running) {
                $running = round($running + $t['signed'], 2);

                return [...$t, 'balance' => $running];
            })
            ->values();

        return ['opening' => round($opening, 2), 'rows' => $rows, 'closing' => round($running, 2)];
    }

    /** Every ledger transaction for the client, oldest first. */
    public static function transactions(Client $client): Collection
    {
        $rows = collect();

        foreach ($client->invoices()->where('status', '!=', 'void')->get() as $invoice) {
            $rows->push([
                'date' => $invoice->invoice_date->toDateString(),
                'sort' => 1,
                'type' => 'Invoice',
                'reference' => $invoice->invoice_no,
                'particulars' => 'Invoice '.$invoice->invoice_no,
                'debit' => (float) $invoice->total,
                'credit' => 0.0,
                'signed' => (float) $invoice->total,
            ]);
        }

        foreach ($client->payments()->get() as $payment) {
            $sign = $payment->is_refund ? 1 : -1;
            $rows->push([
                'date' => $payment->received_at->toDateString(),
                'sort' => 2,
                'type' => $payment->is_refund ? 'Refund' : 'Payment',
                'reference' => $payment->payment_no,
                'particulars' => ($payment->is_refund ? 'Refund — ' : 'Payment — ').\App\Models\Payment::TYPES[$payment->payment_type],
                'debit' => $payment->is_refund ? (float) $payment->amount : 0.0,
                'credit' => $payment->is_refund ? 0.0 : (float) $payment->amount,
                'signed' => $sign * (float) $payment->amount,
            ]);
        }

        foreach ($client->accountAdjustments()->get() as $adj) {
            $sign = $adj->direction === 'debit' ? 1 : -1;
            $rows->push([
                'date' => $adj->adjusted_on->toDateString(),
                'sort' => 3,
                'type' => ucfirst($adj->direction).' adjustment',
                'reference' => $adj->reference,
                'particulars' => $adj->reason,
                'debit' => $adj->direction === 'debit' ? (float) $adj->amount : 0.0,
                'credit' => $adj->direction === 'credit' ? (float) $adj->amount : 0.0,
                'signed' => $sign * (float) $adj->amount,
            ]);
        }

        return $rows->sortBy(['date', 'sort'])->values();
    }

    /**
     * Aged outstanding balance by bucket width (15 or 30 days).
     *
     * @return array{current: float, b1: float, b2: float, b3: float, total: float}
     */
    public static function aging(Client $client, ?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $width = CompanySetting::current()->accounting_period_days ?: 30;

        $buckets = ['current' => 0.0, 'b1' => 0.0, 'b2' => 0.0, 'b3' => 0.0];

        foreach ($client->invoices()->outstanding()->get() as $invoice) {
            $age = $invoice->invoice_date->diffInDays($asOf);
            $balance = (float) $invoice->balance;

            match (true) {
                $age <= $width => $buckets['current'] += $balance,
                $age <= $width * 2 => $buckets['b1'] += $balance,
                $age <= $width * 3 => $buckets['b2'] += $balance,
                default => $buckets['b3'] += $balance,
            };
        }

        // Net off any unapplied credit (advance payments / credit adjustments).
        $credit = $client->currentBalance() - array_sum($buckets);
        if ($credit < 0) {
            $buckets['current'] = round($buckets['current'] + $credit, 2);
        }

        $buckets = array_map(fn ($v) => round($v, 2), $buckets);
        $buckets['total'] = round(array_sum($buckets), 2);

        return $buckets;
    }

    /**
     * Day-range labels for the {@see aging()} buckets, based on the configured
     * accounting period width (e.g. "0-30 days", "31-60 days", "61-90 days", "90+ days").
     *
     * @return array{current: string, b1: string, b2: string, b3: string}
     */
    public static function agingLabels(): array
    {
        $width = CompanySetting::current()->accounting_period_days ?: 30;

        return [
            'current' => "0-{$width} days",
            'b1' => ($width + 1).'-'.($width * 2).' days',
            'b2' => ($width * 2 + 1).'-'.($width * 3).' days',
            'b3' => ($width * 3).'+ days',
        ];
    }
}
