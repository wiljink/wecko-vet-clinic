<?php

namespace App\Support\Reports;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Every method returns the same shape:
 *   [key, title, subtitle, period => [from,to], tiles => [...], sections => [...], notes => [...]]
 * consumed by BaseReport (screen + CSV + PDF).
 */
class ReportBuilder
{
    private function frame(string $key, string $title, CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y'),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'tiles' => [],
            'sections' => [],
            'notes' => [],
        ];
    }

    public function salesVat(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('sales_vat', 'Sales & VAT', $from, $to);

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->leftJoin('groups', 'groups.id', '=', 'products.group_id')
            ->whereBetween('invoices.invoice_date', [$from->toDateString(), $to->toDateString()])
            ->where('invoices.status', '!=', 'void')
            ->groupBy('groups.name')
            ->selectRaw('COALESCE(groups.name, ?) as grp, SUM(invoice_items.line_total_ex_tax) as net, SUM(invoice_items.line_tax) as vat, SUM(invoice_items.line_total_inc_tax) as gross', ['Uncategorised'])
            ->orderByDesc('gross')
            ->get();

        $r['tiles'] = [
            ['label' => 'Gross sales (inc VAT)', 'type' => 'money', 'value' => $rows->sum('gross')],
            ['label' => 'Net sales (ex VAT)', 'type' => 'money', 'value' => $rows->sum('net')],
            ['label' => 'VAT collected', 'type' => 'money', 'value' => $rows->sum('vat')],
        ];
        $r['sections'][] = [
            'title' => 'By product / service group',
            'columns' => [['label' => 'Group'], ['label' => 'Net', 'type' => 'money'], ['label' => 'VAT', 'type' => 'money'], ['label' => 'Gross', 'type' => 'money']],
            'rows' => $rows->map(fn ($x) => [$x->grp, $x->net, $x->vat, $x->gross])->all(),
            'total' => ['Total', $rows->sum('net'), $rows->sum('vat'), $rows->sum('gross')],
        ];
        $r['notes'][] = 'VAT is output tax on sales for the period; input tax on purchases is tracked separately.';

        return $r;
    }

    public function paymentsReceived(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('payments_received', 'Payments Received', $from, $to);

        $rows = Payment::whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('is_refund', false)
            ->selectRaw('payment_type, COUNT(*) as n, SUM(amount) as total')
            ->groupBy('payment_type')->get();

        $refunds = (float) Payment::whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()])->where('is_refund', true)->sum('amount');

        $r['tiles'] = [
            ['label' => 'Total received', 'type' => 'money', 'value' => $rows->sum('total')],
            ['label' => 'Transactions', 'type' => 'number', 'value' => $rows->sum('n')],
            ['label' => 'Refunds paid out', 'type' => 'money', 'value' => $refunds],
        ];
        $r['sections'][] = [
            'title' => 'By method',
            'columns' => [['label' => 'Method'], ['label' => 'Count', 'type' => 'number'], ['label' => 'Amount', 'type' => 'money']],
            'rows' => $rows->map(fn ($x) => [Payment::TYPES[$x->payment_type] ?? $x->payment_type, $x->n, $x->total])->all(),
            'total' => ['Total', $rows->sum('n'), $rows->sum('total')],
        ];

        return $r;
    }

    public function cashSales(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('cash_sales', 'Cash Sales', $from, $to);

        $rows = Payment::whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('payment_type', 'cash')->where('is_refund', false)
            ->with('client')->orderBy('received_at')->get();

        $r['tiles'] = [
            ['label' => 'Cash taken', 'type' => 'money', 'value' => $rows->sum('amount')],
            ['label' => 'Transactions', 'type' => 'number', 'value' => $rows->count()],
        ];
        $r['sections'][] = [
            'title' => 'Cash payments',
            'columns' => [['label' => 'Date', 'type' => 'date'], ['label' => 'Receipt'], ['label' => 'Client'], ['label' => 'Amount', 'type' => 'money']],
            'rows' => $rows->map(fn (Payment $p) => [$p->received_at->toDateString(), $p->payment_no, $p->client?->full_name, $p->amount])->all(),
            'total' => ['', '', 'Total', $rows->sum('amount')],
        ];

        return $r;
    }

    public function profitAndLoss(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('profit_loss', 'Sales / Profit', $from, $to);

        $revenue = (float) Invoice::whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', 'void')->sum('subtotal_ex_tax');

        $cogs = (float) StockMovement::where('type', 'sale')
            ->whereBetween('moved_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('SUM(ABS(qty_change) * unit_cost_ex_tax) as c')->value('c');

        $gross = $revenue - $cogs;

        $r['tiles'] = [
            ['label' => 'Revenue (ex VAT)', 'type' => 'money', 'value' => $revenue],
            ['label' => 'Cost of goods sold', 'type' => 'money', 'value' => $cogs],
            ['label' => 'Gross profit', 'type' => 'money', 'value' => $gross],
            ['label' => 'Gross margin', 'type' => 'percent', 'value' => $revenue > 0 ? $gross / $revenue * 100 : 0],
        ];
        $r['sections'][] = [
            'title' => 'Summary',
            'columns' => [['label' => 'Line'], ['label' => 'Amount', 'type' => 'money']],
            'rows' => [['Revenue (ex VAT)', $revenue], ['Less: COGS', -$cogs], ['Gross profit', $gross]],
        ];
        $r['notes'][] = 'COGS uses the recorded unit cost on each stock movement at time of sale. Service revenue carries no COGS.';

        return $r;
    }

    public function transactionSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('transaction_summary', 'Transaction Summary', $from, $to);

        $d = [$from->toDateString(), $to->toDateString()];
        $dt = [$from->startOfDay(), $to->endOfDay()];

        $invoices = Invoice::whereBetween('invoice_date', $d)->where('status', '!=', 'void');
        $payments = Payment::whereBetween('received_at', $dt);

        $r['sections'][] = [
            'title' => 'Counts and totals',
            'columns' => [['label' => 'Type'], ['label' => 'Count', 'type' => 'number'], ['label' => 'Value', 'type' => 'money']],
            'rows' => [
                ['Invoices raised', (clone $invoices)->count(), (clone $invoices)->sum('total')],
                ['Consultations', \App\Models\Consultation::whereBetween('consult_date', $dt)->count(), \App\Models\Consultation::whereBetween('consult_date', $dt)->sum('total_inc_tax')],
                ['Counter sales', \App\Models\CounterSale::whereBetween('sale_date', $d)->count(), \App\Models\CounterSale::whereBetween('sale_date', $d)->sum('total_inc_tax')],
                ['Payments received', (clone $payments)->where('is_refund', false)->count(), (clone $payments)->where('is_refund', false)->sum('amount')],
                ['Refunds', (clone $payments)->where('is_refund', true)->count(), (clone $payments)->where('is_refund', true)->sum('amount')],
                ['Account adjustments', \App\Models\AccountAdjustment::whereBetween('adjusted_on', $d)->count(), \App\Models\AccountAdjustment::whereBetween('adjusted_on', $d)->sum('amount')],
            ],
        ];

        return $r;
    }

    public function agingOfAccounts(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('aging', 'Aging of Accounts', $from, $to);
        $r['subtitle'] = 'As at '.$to->format('d M Y');

        $rows = [];
        $tot = ['current' => 0, 'b1' => 0, 'b2' => 0, 'b3' => 0, 'total' => 0];

        Client::has('invoices')->with('invoices')->chunk(200, function ($clients) use (&$rows, &$tot, $to) {
            foreach ($clients as $client) {
                $a = \App\Support\ClientLedger::aging($client, $to);
                if ($a['total'] == 0) {
                    continue;
                }
                $rows[] = [$client->full_name, $a['current'], $a['b1'], $a['b2'], $a['b3'], $a['total']];
                foreach ($tot as $k => $v) {
                    $tot[$k] += $a[$k];
                }
            }
        });

        usort($rows, fn ($a, $b) => $b[5] <=> $a[5]);

        $agingLabels = \App\Support\ClientLedger::agingLabels();

        $r['tiles'] = [
            ['label' => 'Total outstanding', 'type' => 'money', 'value' => $tot['total']],
            ['label' => 'Overdue', 'type' => 'money', 'value' => $tot['b1'] + $tot['b2'] + $tot['b3']],
            ['label' => 'Accounts in debt', 'type' => 'number', 'value' => count($rows)],
        ];
        $r['sections'][] = [
            'title' => 'By client',
            'columns' => [['label' => 'Client'], ['label' => $agingLabels['current'], 'type' => 'money'], ['label' => $agingLabels['b1'], 'type' => 'money'], ['label' => $agingLabels['b2'], 'type' => 'money'], ['label' => $agingLabels['b3'], 'type' => 'money'], ['label' => 'Total', 'type' => 'money']],
            'rows' => $rows,
            'total' => ['Total', $tot['current'], $tot['b1'], $tot['b2'], $tot['b3'], $tot['total']],
        ];

        return $r;
    }

    public function accountReconciliation(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('reconciliation', 'Account Reconciliation', $from, $to);

        $rows = [];
        $flagged = 0;

        Client::has('invoices')->with(['invoices.allocations', 'payments', 'accountAdjustments'])->chunk(200, function ($clients) use (&$rows, &$flagged) {
            foreach ($clients as $client) {
                $invoiced = (float) $client->invoices->where('status', '!=', 'void')->sum('total');
                $allocated = (float) $client->invoices->flatMap->allocations->sum('amount');
                $paid = (float) $client->payments->where('is_refund', false)->sum('amount');
                $refunded = (float) $client->payments->where('is_refund', true)->sum('amount');
                $adj = (float) $client->accountAdjustments->sum(fn ($a) => $a->direction === 'debit' ? $a->amount : -$a->amount);
                $expected = round($invoiced + $adj - $allocated, 2);
                $balance = $client->currentBalance();
                $diff = round($balance - $expected, 2);

                if (abs($diff) < 0.01 && abs($paid - $refunded - $allocated) < 0.01) {
                    continue;
                }

                $flagged++;
                $rows[] = [$client->full_name, $invoiced, $paid, $refunded, $adj, $balance, $diff];
            }
        });

        $r['tiles'] = [
            ['label' => 'Accounts flagged', 'type' => 'number', 'value' => $flagged],
        ];
        $r['sections'][] = [
            'title' => 'Accounts needing attention (unapplied credit or balance mismatch)',
            'columns' => [['label' => 'Client'], ['label' => 'Invoiced', 'type' => 'money'], ['label' => 'Paid', 'type' => 'money'], ['label' => 'Refunded', 'type' => 'money'], ['label' => 'Adjustments', 'type' => 'money'], ['label' => 'Balance', 'type' => 'money'], ['label' => 'Unapplied', 'type' => 'money']],
            'rows' => $rows,
        ];
        $r['notes'][] = 'Rows show where payments exceed allocations (credit on account) or the computed balance differs from invoiced − paid − adjusted.';

        return $r;
    }

    public function inventoryValuation(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('inventory_valuation', 'Inventory Valuation', $from, $to);
        $r['subtitle'] = 'As at today, at unit cost (ex VAT)';

        $rows = Product::query()->whereIn('products.kind', ['product', 'vaccine'])->where('products.is_active', true)
            ->leftJoin('groups', 'groups.id', '=', 'products.group_id')
            ->selectRaw('COALESCE(groups.name, ?) grp, SUM(products.qty_on_hand) qty, SUM(products.qty_on_hand * products.unit_cost_ex_tax) val', ['Uncategorised'])
            ->groupBy('groups.name')->orderByDesc('val')->get();

        $r['tiles'] = [
            ['label' => 'Stock at cost', 'type' => 'money', 'value' => $rows->sum('val')],
            ['label' => 'Units on hand', 'type' => 'number', 'value' => $rows->sum('qty')],
        ];
        $r['sections'][] = [
            'title' => 'By group',
            'columns' => [['label' => 'Group'], ['label' => 'Units', 'type' => 'number'], ['label' => 'Value at cost', 'type' => 'money']],
            'rows' => $rows->map(fn ($x) => [$x->grp, $x->qty, $x->val])->all(),
            'total' => ['Total', $rows->sum('qty'), $rows->sum('val')],
        ];

        return $r;
    }

    public function priceList(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('price_list', 'Price List', $from, $to);
        $r['subtitle'] = 'Current active items';

        $rows = Product::with('group')->where('is_active', true)->orderBy('kind')->orderBy('name')->get();

        $r['sections'][] = [
            'title' => 'Products, vaccines & services',
            'columns' => [['label' => 'Code'], ['label' => 'Name'], ['label' => 'Group'], ['label' => 'Kind'], ['label' => 'Price ex VAT', 'type' => 'money'], ['label' => 'Price inc VAT', 'type' => 'money']],
            'rows' => $rows->map(fn (Product $p) => [$p->code, $p->name, $p->group?->name, ucfirst($p->kind), $p->sell_price_ex_tax, $p->sell_price_inc_tax])->all(),
        ];

        return $r;
    }

    public function remindersDue(CarbonInterface $from, CarbonInterface $to): array
    {
        $r = $this->frame('reminders_due', 'Reminders Due', $from, $to);

        $rows = Reminder::pending()
            ->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
            ->with('patient', 'client', 'reminderType')
            ->orderBy('due_on')->get();

        $byCat = $rows->groupBy('category')->map->count();

        $r['tiles'] = collect(Reminder::CATEGORIES)
            ->map(fn ($label, $key) => ['label' => $label, 'type' => 'number', 'value' => $byCat[$key] ?? 0])
            ->values()->all();

        $r['sections'][] = [
            'title' => 'Pending reminders',
            'columns' => [['label' => 'Due', 'type' => 'date'], ['label' => 'Category'], ['label' => 'Type'], ['label' => 'Patient'], ['label' => 'Client'], ['label' => 'Contact']],
            'rows' => $rows->map(fn (Reminder $x) => [
                $x->due_on->toDateString(),
                Reminder::CATEGORIES[$x->category] ?? $x->category,
                $x->reminderType?->name,
                $x->patient?->name,
                $x->client?->full_name,
                $x->client?->mobile_phone ?: $x->client?->email,
            ])->all(),
        ];

        return $r;
    }
}
