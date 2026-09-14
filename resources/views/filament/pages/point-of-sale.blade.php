@php($cur = \App\Models\CompanySetting::currencySymbol())
<x-filament-panels::page>
    <style>
        .pos-grid { display: grid; gap: 1.5rem; grid-template-columns: 1fr; }
        @media (min-width: 1024px) { .pos-grid { grid-template-columns: 3fr 2fr; align-items: start; } }
        .pos-card { border-radius: 0.75rem; background: rgb(255 255 255); padding: 1rem;
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); --tw-ring-inset: inset;
            box-shadow: 0 0 0 1px rgb(0 0 0 / 0.05); }
        .dark .pos-card { background: rgb(17 24 39); box-shadow: 0 0 0 1px rgb(255 255 255 / 0.1); }
        .pos-stack > * + * { margin-top: 1rem; }
        .pos-row { display: flex; gap: 0.5rem; }
        .pos-two { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        .pos-in { width: 100%; border-radius: 0.5rem; border: 1px solid rgb(209 213 219);
            padding: 0.375rem 0.5rem; font-size: 0.875rem; background: transparent; }
        .dark .pos-in { border-color: rgb(255 255 255 / 0.1); }
        select.pos-in { appearance: auto; -webkit-appearance: auto; background-image: none; }
        .pos-table { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
        .pos-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.05em; color: rgb(156 163 175);
            border-bottom: 1px solid rgb(243 244 246); }
        .pos-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid rgb(249 250 251); vertical-align: middle; }
        .dark .pos-table th, .dark .pos-table td { border-color: rgb(255 255 255 / 0.08); }
        .pos-qty { display: flex; align-items: center; justify-content: center; gap: 0.25rem; }
        .pos-qty button { border-radius: 0.25rem; background: rgb(243 244 246); padding: 0 0.5rem; line-height: 1.6rem; }
        .dark .pos-qty button { background: rgb(255 255 255 / 0.1); }
        .pos-qty input, .pos-cell-in { width: 3.5rem; border-radius: 0.25rem; border: 1px solid rgb(209 213 219);
            padding: 0.125rem 0.25rem; text-align: center; font-size: 0.875rem; background: transparent; }
        .pos-list { margin-top: 0.5rem; border-radius: 0.5rem; box-shadow: 0 0 0 1px rgb(0 0 0 / 0.05); overflow: hidden; }
        .pos-list button { display: flex; width: 100%; align-items: center; justify-content: space-between;
            padding: 0.5rem 0.75rem; font-size: 0.875rem; border-bottom: 1px solid rgb(243 244 246); }
        .pos-list button:hover { background: rgb(249 250 251); }
        .dark .pos-list button:hover { background: rgb(255 255 255 / 0.05); }
        .pos-muted { color: rgb(156 163 175); }
        .pos-right { text-align: right; }
        .pos-mono { font-variant-numeric: tabular-nums; font-family: ui-monospace, monospace; }
        .pos-totals { display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.125rem 0; }
        .pos-total-final { font-size: 1.05rem; font-weight: 700; border-top: 1px solid rgb(229 231 235); padding-top: 0.375rem; margin-top: 0.25rem; }
        .pos-empty { padding: 2.5rem 0; text-align: center; color: rgb(156 163 175); }
        .pos-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }
        .pos-done { border-radius: 0.75rem; background: rgb(240 253 244); padding: 1rem; font-size: 0.875rem;
            box-shadow: 0 0 0 1px rgb(22 163 74 / 0.2); }
        .dark .pos-done { background: rgb(34 197 94 / 0.1); }
    </style>

    <div class="pos-grid">
        {{-- LEFT --}}
        <div class="pos-stack">
            <div class="pos-card">
                <form wire:submit.prevent="addScan" class="pos-row">
                    <input type="text" class="pos-in" wire:model="scan" autofocus
                        wire:keydown.enter.prevent="addScan"
                        placeholder="Scan barcode or type a product code, then Enter" />
                    <x-filament::button type="submit" icon="heroicon-m-plus">Add</x-filament::button>
                </form>

                <div style="margin-top:0.75rem;">
                    <input type="text" class="pos-in" wire:model.live.debounce.300ms="search" placeholder="Search products by name…" />
                    @if ($this->searchResults->isNotEmpty())
                        <div class="pos-list">
                            @foreach ($this->searchResults as $product)
                                <button type="button" wire:click="addProduct({{ $product->id }})">
                                    <span>
                                        <span style="font-weight:500;">{{ $product->name }}</span>
                                        <span class="pos-muted">
                                            @if ($product->barcode) · {{ $product->barcode }} @endif
                                            @if ($product->tracksStock()) · {{ (int) $product->qty_on_hand }} on hand @endif
                                        </span>
                                    </span>
                                    <span class="pos-mono">{{ $cur }}{{ number_format((float) $product->sell_price_inc_tax, 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="pos-card" style="padding:0;">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>Item</th><th style="text-align:center;">Qty</th><th>Price</th><th>Disc %</th>
                            <th class="pos-right">Total</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($cart as $i => $line)
                        <tr wire:key="line-{{ $i }}">
                            <td>{{ $line['description'] }}</td>
                            <td>
                                <div class="pos-qty">
                                    <button type="button" wire:click="decQty({{ $i }})">&minus;</button>
                                    <input type="number" min="0" step="1" wire:model.lazy="cart.{{ $i }}.qty" />
                                    <button type="button" wire:click="incQty({{ $i }})">+</button>
                                </div>
                            </td>
                            <td><input class="pos-cell-in" style="width:5rem;" type="number" min="0" step="0.01" wire:model.lazy="cart.{{ $i }}.unit_price_ex_tax" /></td>
                            <td><input class="pos-cell-in" style="width:4rem;" type="number" min="0" max="100" step="1" wire:model.lazy="cart.{{ $i }}.discount_pct" /></td>
                            <td class="pos-right pos-mono">{{ $cur }}{{ number_format((float) $line['line_total_inc_tax'], 2) }}</td>
                            <td><button type="button" wire:click="removeLine({{ $i }})" class="pos-muted">&times;</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pos-empty">Cart is empty — scan or search to add products.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if ($cart)
                <x-filament::button color="gray" size="sm" wire:click="clearCart" icon="heroicon-m-trash">Clear cart</x-filament::button>
            @endif
        </div>

        {{-- RIGHT --}}
        <div class="pos-stack">
            <div class="pos-card pos-stack">
                <label class="pos-check">
                    <input type="checkbox" wire:model.live="walkIn" /> Walk-in customer
                </label>
                @if ($walkIn)
                    <input type="text" class="pos-in" wire:model="walkInName" placeholder="Customer name (optional)" />
                @else
                    <select class="pos-in" wire:model.live="clientId">
                        <option value="">— select client —</option>
                        @foreach ($this->clientOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="pos-two">
                    <select class="pos-in" wire:model="providerId">
                        <option value="">Served by…</option>
                        @foreach ($this->providerOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <select class="pos-in" wire:model="locationId" @disabled(! $this->canSwitchLocation())>
                        <option value="">Branch…</option>
                        @foreach ($this->locationOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @php($t = $this->totals())
            <div class="pos-card">
                <div class="pos-totals"><span class="pos-muted">Subtotal</span><span class="pos-mono">{{ $cur }}{{ number_format($t['subtotal'], 2) }}</span></div>
                <div class="pos-totals"><span class="pos-muted">Tax</span><span class="pos-mono">{{ $cur }}{{ number_format($t['tax'], 2) }}</span></div>
                <div class="pos-totals pos-total-final"><span>Total</span><span class="pos-mono">{{ $cur }}{{ number_format($t['total'], 2) }}</span></div>

                <div class="pos-stack" style="margin-top:1rem;">
                    <label class="pos-check">
                        <input type="checkbox" wire:model.live="onAccount" @disabled($walkIn) />
                        Charge to account (no payment now)
                    </label>

                    @unless ($onAccount)
                        <select class="pos-in" wire:model.live="paymentType">
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit card</option>
                            <option value="eftpos">EFTPOS</option>
                            <option value="cheque">Cheque</option>
                        </select>

                        @if ($paymentType === 'cash')
                            <input type="number" step="0.01" min="0" class="pos-in" wire:model.live="cashReceived" placeholder="Cash received ({{ $cur }})" />
                            <div class="pos-totals">
                                <span class="pos-muted">Change</span>
                                <span class="pos-mono" style="font-size:1.15rem;font-weight:600;color:rgb(22 163 74);">{{ $cur }}{{ number_format($this->change(), 2) }}</span>
                            </div>
                        @endif
                    @endunless

                    <x-filament::button style="width:100%;" size="lg" wire:click="completeSale" wire:loading.attr="disabled"
                        icon="heroicon-m-check-circle" :disabled="empty($cart)">
                        {{ $onAccount ? 'Charge to account' : 'Take payment' }}
                    </x-filament::button>
                </div>
            </div>

            @if ($lastReceiptUrl)
                <div class="pos-done">
                    <p style="font-weight:500;">Sale {{ $lastSaleNo }} completed.</p>
                    <a href="{{ $lastReceiptUrl }}" target="_blank" style="margin-top:0.5rem;display:inline-block;font-weight:500;text-decoration:underline;">
                        Print receipt
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
