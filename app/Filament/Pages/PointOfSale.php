<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\CounterSale;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Support\LineTotals;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Sales 6b — a keyboard / barcode-scanner driven point of sale.
 *
 * Scan or search a product to drop it in the cart, adjust quantities and
 * discounts inline, then tender cash / card. On completion it builds a
 * {@see CounterSale} and runs the same {@see CounterSale::complete()} path as
 * the classic Counter Sales screen, so stock, invoicing and the till all behave
 * identically — this page is only a faster front end.
 */
class PointOfSale extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Point of Sale';

    protected static ?string $title = 'Point of Sale';

    protected static string $view = 'filament.pages.point-of-sale';

    /** @var array<int, array<string, mixed>> */
    public array $cart = [];

    public string $scan = '';

    public string $search = '';

    public bool $walkIn = true;

    public ?string $walkInName = null;

    public ?int $clientId = null;

    public ?int $providerId = null;

    public ?int $locationId = null;

    public string $paymentType = 'cash';

    public ?float $cashReceived = null;

    public bool $onAccount = false;

    public ?string $lastReceiptUrl = null;

    public ?string $lastSaleNo = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('create_counter_sale') ?? false;
    }

    public function mount(): void
    {
        $this->providerId = Auth::id();
        $this->paymentType = CompanySetting::current()->pos_default_payment_type ?: 'cash';
    }

    // ---- lookups for the view -------------------------------------------------

    /** @return array<int, string> */
    public function getProviderOptionsProperty(): array
    {
        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function getLocationOptionsProperty(): array
    {
        return Location::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function getClientOptionsProperty(): array
    {
        return Client::where('is_active', true)->orderBy('surname')->limit(200)
            ->get()->mapWithKeys(fn (Client $c) => [$c->id => $c->full_name])->all();
    }

    /** @return Collection<int, Product> */
    public function getSearchResultsProperty()
    {
        $term = trim($this->search);

        if (strlen($term) < 2) {
            return collect();
        }

        return Product::sellable('otc')
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%"))
            ->orderBy('name')->limit(12)->get();
    }

    // ---- cart actions -------------------------------------------------------

    public function addScan(): void
    {
        $term = trim($this->scan);
        $this->scan = '';

        if ($term === '') {
            return;
        }

        $product = Product::query()->sellable('otc')->scan($term)->first()
            ?? Product::query()->scan($term)->first();

        if (! $product) {
            Notification::make()->title("No product matches “{$term}”")->warning()->send();

            return;
        }

        $this->pushProduct($product);
    }

    public function addProduct(int $productId): void
    {
        $this->search = '';

        if ($product = Product::find($productId)) {
            $this->pushProduct($product);
        }
    }

    protected function pushProduct(Product $product): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['product_id'] === $product->id) {
                $this->cart[$i]['qty']++;
                $this->recalcLine($i);

                return;
            }
        }

        if ($product->tracksStock() && $product->qty_on_hand <= 0) {
            Notification::make()->title("{$product->name} is out of stock")->warning()->send();
        }

        $this->cart[] = [
            'product_id' => $product->id,
            'description' => $product->name,
            'qty' => 1,
            'unit_price_ex_tax' => (float) $product->sell_price_ex_tax,
            'tax_rate' => (float) $product->tax_rate,
            'discount_pct' => 0.0,
            'dispensing_fee' => $product->dispense_fee_always ? (float) $product->dispense_fee : 0.0,
            'regime_id' => $product->regime_id,
            'line_total_inc_tax' => 0.0,
        ];
        $this->recalcLine(array_key_last($this->cart));
    }

    public function incQty(int $i): void
    {
        if (isset($this->cart[$i])) {
            $this->cart[$i]['qty']++;
            $this->recalcLine($i);
        }
    }

    public function decQty(int $i): void
    {
        if (! isset($this->cart[$i])) {
            return;
        }

        if ($this->cart[$i]['qty'] <= 1) {
            $this->removeLine($i);

            return;
        }
        $this->cart[$i]['qty']--;
        $this->recalcLine($i);
    }

    public function removeLine(int $i): void
    {
        unset($this->cart[$i]);
        $this->cart = array_values($this->cart);
    }

    public function updatedCart(): void
    {
        foreach (array_keys($this->cart) as $i) {
            $this->recalcLine($i);
        }
    }

    protected function recalcLine(int $i): void
    {
        $line = $this->cart[$i];
        $t = LineTotals::forLine(
            max(0, (float) $line['qty']),
            max(0, (float) $line['unit_price_ex_tax']),
            (float) $line['tax_rate'],
            min(100, max(0, (float) $line['discount_pct'])),
            (float) ($line['dispensing_fee'] ?? 0),
        );
        $this->cart[$i]['line_total_inc_tax'] = $t['inc_tax'];
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->cashReceived = null;
        $this->lastReceiptUrl = null;
        $this->lastSaleNo = null;
    }

    // ---- totals ----------------------------------------------------------
    // Plain methods (not cached "computed properties") so every re-render
    // reflects the current cart, including the empty cart after a sale.

    /** @return array{subtotal: float, tax: float, total: float} */
    public function totals(): array
    {
        $ex = 0.0;
        $tax = 0.0;

        foreach ($this->cart as $l) {
            $t = LineTotals::forLine(
                (float) $l['qty'], (float) $l['unit_price_ex_tax'], (float) $l['tax_rate'],
                (float) $l['discount_pct'], (float) ($l['dispensing_fee'] ?? 0),
            );
            $ex += $t['ex_tax'];
            $tax += $t['tax'];
        }

        return [
            'subtotal' => round($ex, 2),
            'tax' => round($tax, 2),
            'total' => round($ex + $tax, 2),
        ];
    }

    public function change(): float
    {
        if ($this->paymentType !== 'cash' || $this->cashReceived === null) {
            return 0.0;
        }

        return round(max(0, (float) $this->cashReceived - $this->totals()['total']), 2);
    }

    // ---- checkout -------------------------------------------------------

    public function completeSale(): void
    {
        abort_unless(Auth::user()?->can('create_counter_sale'), 403);

        if ($this->cart === []) {
            Notification::make()->title('The cart is empty')->warning()->send();

            return;
        }

        if (! $this->walkIn && ! $this->clientId) {
            Notification::make()->title('Select a client or switch to walk-in')->warning()->send();

            return;
        }

        $takePayment = ! $this->onAccount;

        if ($takePayment && ! Auth::user()->can('process_payment')) {
            Notification::make()->title('You are not allowed to take payments — close on account instead')->danger()->send();

            return;
        }

        if ($takePayment && $this->paymentType === 'cash' && $this->cashReceived !== null
            && (float) $this->cashReceived + 0.001 < $this->totals()['total']) {
            Notification::make()->title('Cash received is less than the total due')->warning()->send();

            return;
        }

        $sale = CounterSale::create([
            'walk_in' => $this->walkIn,
            'walk_in_name' => $this->walkIn ? ($this->walkInName ?: 'Walk-in') : null,
            'client_id' => $this->walkIn ? null : $this->clientId,
            'provider_id' => $this->providerId,
            'location_id' => $this->locationId,
            'sale_date' => now()->toDateString(),
        ]);

        foreach ($this->cart as $line) {
            $sale->items()->create([
                'kind' => 'product',
                'product_id' => $line['product_id'],
                'description' => $line['description'],
                'qty' => $line['qty'],
                'unit_price_ex_tax' => $line['unit_price_ex_tax'],
                'tax_rate' => $line['tax_rate'],
                'discount_pct' => $line['discount_pct'],
                'dispensing_fee' => $line['dispensing_fee'] ?? 0,
                'regime_id' => $line['regime_id'] ?? null,
            ]);
        }
        $sale->refresh();

        $payment = $takePayment ? [
            'payment_type' => $this->paymentType,
            'cash_received' => $this->paymentType === 'cash' ? $this->cashReceived : null,
        ] : null;

        [$invoice, $paymentModel] = $sale->complete($payment);

        $change = $paymentModel?->change_given ?? 0;
        Notification::make()
            ->title("Sale {$sale->sale_no} complete — invoice {$invoice->invoice_no}"
                .($change > 0 ? ' · change '.CompanySetting::currencySymbol().number_format((float) $change, 2) : ''))
            ->success()->send();

        $this->lastReceiptUrl = route('counter-sales.receipt', $sale);
        $this->lastSaleNo = $sale->sale_no;

        // reset for the next customer
        $this->cart = [];
        $this->cashReceived = null;
        $this->walkInName = null;
        $this->clientId = null;
        $this->onAccount = false;
        $this->paymentType = CompanySetting::current()->pos_default_payment_type ?: 'cash';
    }
}
