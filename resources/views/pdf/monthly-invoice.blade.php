<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { color: #173b31; font-family: Helvetica, Arial, sans-serif; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cfdad3; padding: 5px; vertical-align: top; }
        th { background: #e8f4ed; color: #0f3f2f; font-weight: bold; text-align: left; }
        .no-border, .no-border td { border: 0; }
        .header { border-bottom: 3px solid #0f3f2f; margin-bottom: 14px; padding-bottom: 10px; }
        .brand { color: #0f3f2f; font-size: 22px; font-weight: bold; }
        .muted { color: #65736d; }
        .right { text-align: right; }
        .logo { max-height: 48px; max-width: 110px; }
        .client-box { margin-bottom: 14px; }
        .invoice-table td { height: 17px; }
        .itemized-heading { color: #0f3f2f; font-size: 14px; font-weight: bold; margin: 14px 0 7px; }
        .itemized-table { table-layout: fixed; }
        .itemized-table th, .itemized-table td { padding: 6px 5px; }
        .itemized-table tr { page-break-inside: avoid; }
        .totals { margin-left: auto; margin-top: 14px; width: 42%; }
        .grand td { color: #0f3f2f; font-size: 13px; font-weight: bold; }
        .footer { border-top: 2px solid #0f3f2f; font-weight: bold; margin-top: 18px; padding-top: 9px; text-align: center; }
        body.style-hotel .header { border-bottom-color: #1d6f50; }
        body.style-hotel th { background: #dff2ea; color: #0b4b35; }
        body.style-hotel .brand, body.style-hotel .grand td { color: #0b4b35; }
        body.style-compact { font-size: 9px; }
        body.style-compact .brand { font-size: 18px; }
        body.style-compact th, body.style-compact td { padding: 4px; }
        body.style-compact .invoice-table td { height: 14px; }
    </style>
</head>
<body class="style-{{ $invoice->client?->invoice_style ?? 'standard' }}">
@php
    $client = $invoice->client;
    $invoiceLanguage = $client?->default_language ?? 'fr';
    $categories = collect($invoice->category_snapshot ?? []);
    $singleCategory = $categories->count() === 1;
    $useItemizedLayout = $categories->count() > 8;
    $businessLogo = $settings?->logo_path ? public_path('storage/'.$settings->logo_path) : null;
    $clientLogo = $client?->logo_path ? public_path('storage/'.$client->logo_path) : null;
    $categoriesById = $categories->keyBy(fn ($category) => (string) ($category['id'] ?? ''));
    $entryTotals = $invoice->entries
        ->groupBy(fn ($entry) => $entry->service_day.'-'.($entry->client_category_id ?? 'none'))
        ->map(fn ($entries) => $entries->sum('amount_cents'));
    $lineItems = $invoice->entries
        ->sortBy(fn ($entry) => sprintf('%02d-%08d', $entry->service_day, $entry->id))
        ->flatMap(function ($entry) use ($categoriesById) {
            $category = $categoriesById->get((string) $entry->client_category_id, []);
            $serviceLabel = isset($category['service_type'])
                ? App\Models\ClientCategory::serviceLabel($category['service_type'])
                : null;
            $audienceLabel = isset($category['audience'])
                ? App\Models\ClientCategory::audienceLabel($category['audience'])
                : null;
            $details = collect($entry->item_details ?? []);

            if ($details->isEmpty()) {
                return $entry->amount_cents > 0 ? [[
                    'day' => $entry->service_day,
                    'service' => collect([$serviceLabel, $audienceLabel])->filter()->join(' · '),
                    'label' => $category['name'] ?? $entry->category_name_snapshot,
                    'quantity' => null,
                    'unit_price_cents' => null,
                    'total_cents' => $entry->amount_cents,
                ]] : [];
            }

            return $details->map(fn ($detail) => [
                'day' => $entry->service_day,
                'service' => collect([$serviceLabel, $audienceLabel])->filter()->join(' · '),
                'label' => $detail['label'] ?? $category['name'] ?? $entry->category_name_snapshot,
                'quantity' => $detail['quantity'] ?? null,
                'unit_price_cents' => $detail['unit_price_cents'] ?? null,
                'total_cents' => $detail['total_cents'] ?? 0,
            ])->all();
        })
        ->values();
@endphp

<div class="header">
    <table class="no-border">
        <tr>
            <td style="width: 90px;">
                @if($businessLogo && file_exists($businessLogo))
                    <img class="logo" src="{{ $businessLogo }}" alt="Logo">
                @endif
            </td>
            <td>
                <div class="brand">{{ $settings?->display_name ?? 'Nettoyeur Villeneuve' }}</div>
                <div>{{ $settings?->legal_name }}</div>
                <div>{{ trim(($settings?->address ?? '').' '.($settings?->city ?? '').' '.($settings?->province ?? '').' '.($settings?->postal_code ?? '')) }}</div>
                <div>TPS/TVH: {{ $settings?->gst_number }} @if($settings?->qst_number) &nbsp; TVQ: {{ $settings->qst_number }} @endif</div>
            </td>
            <td class="right" style="width: 210px;">
                <div class="brand">Facture {{ $invoice->invoice_number }}</div>
                <div>Date: {{ $invoice->invoice_date?->format('Y-m-d') }}</div>
                <div>Période: {{ $invoice->invoice_month }}/{{ $invoice->invoice_year }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="client-box">
    <tr>
        <td style="width: 55%;">
            @if($clientLogo && file_exists($clientLogo))
                <img class="logo" src="{{ $clientLogo }}" alt="Logo client"><br>
            @endif
            <strong>Facturé à</strong><br>
            {{ $client?->name ?? 'Client supprimé' }}<br>
            {{ $client?->billing_address }}<br>
            {{ trim(($client?->city ?? '').' '.($client?->province ?? '').' '.($client?->postal_code ?? '')) }}
        </td>
        <td>
            <strong>Statut</strong><br>
            {{ match($invoice->status) {
                'draft' => 'Brouillon',
                'approved' => 'Approuvée',
                'sent' => 'Envoyée',
                'paid' => 'Payée',
                'cancelled' => 'Annulée',
                default => $invoice->status,
            } }}
            @if($invoice->notes)
                <br><br><strong>Notes</strong><br>{{ $invoice->notes }}
            @endif
        </td>
    </tr>
</table>

@if($useItemizedLayout)
    <div class="itemized-heading">Détail des items facturés</div>
    <table class="itemized-table">
        <thead>
            <tr>
                <th style="width: 34px;">Jour</th>
                <th style="width: 140px;">Service / section</th>
                <th>Item</th>
                <th class="right" style="width: 45px;">Qté</th>
                <th class="right" style="width: 72px;">Prix unit.</th>
                <th class="right" style="width: 72px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lineItems as $lineItem)
                <tr>
                    <td><strong>{{ $lineItem['day'] }}</strong></td>
                    <td class="muted" style="font-size: 8px;">{{ $lineItem['service'] ?: '—' }}</td>
                    <td><strong>{{ $lineItem['label'] }}</strong></td>
                    <td class="right">
                        @if($lineItem['quantity'] !== null)
                            {{ rtrim(rtrim(number_format((float) $lineItem['quantity'], 2, ',', ' '), '0'), ',') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="right">
                        {{ $lineItem['unit_price_cents'] !== null ? $money->format($lineItem['unit_price_cents'], $invoiceLanguage) : '—' }}
                    </td>
                    <td class="right"><strong>{{ $money->format($lineItem['total_cents'], $invoiceLanguage) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding: 18px; text-align: center;">Aucun item facturé.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@else
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 50px;">Jour</th>
                @foreach($categories as $category)
                    <th class="right">
                        @if(! $singleCategory && isset($category['service_type'], $category['audience']))
                            <span class="muted" style="display: block; font-size: 7px; font-weight: normal;">
                                {{ App\Models\ClientCategory::serviceLabel($category['service_type']) }}
                                · {{ App\Models\ClientCategory::audienceLabel($category['audience']) }}
                            </span>
                        @endif
                        {{ $singleCategory ? 'Montant' : ($category['name'] ?? 'Montant') }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @for($day = 1; $day <= 31; $day++)
                <tr>
                    <td><strong>{{ $day }}</strong></td>
                    @foreach($categories as $category)
                        @php
                            $categoryId = $category['id'] ?? 'none';
                            $sum = $entryTotals->get($day.'-'.$categoryId, 0);
                            $cellEntries = $invoice->entries
                                ->where('service_day', $day)
                                ->where('client_category_id', $categoryId === 'none' ? null : $categoryId);
                        @endphp
                        <td class="right">
                            @if($sum)
                                <strong>{{ $money->format($sum, $invoiceLanguage) }}</strong>
                            @endif
                            @foreach($cellEntries as $entry)
                                @foreach($entry->item_details ?? [] as $detail)
                                    <div class="muted" style="font-size: 8px; margin-top: 2px;">
                                        Qté {{ $detail['quantity'] ?? '' }}
                                        × Prix unit. {{ $money->format($detail['unit_price_cents'] ?? 0, $invoiceLanguage) }}
                                        = {{ $money->format($detail['total_cents'] ?? 0, $invoiceLanguage) }}
                                    </div>
                                @endforeach
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
@endif

<table class="totals">
    <tr>
        <td>Sous-total</td>
        <td class="right">{{ $money->format($invoice->subtotal_cents, $invoiceLanguage) }}</td>
    </tr>
    @foreach($invoice->adjustments as $adjustment)
        <tr>
            <td>{{ $adjustment->label }}</td>
            <td class="right">
                {{ in_array($adjustment->type, ['discount', 'credit'], true) ? '-' : '' }}{{ $money->format($adjustment->amount_cents, $invoiceLanguage) }}
            </td>
        </tr>
    @endforeach
    @foreach($invoice->tax_profile_snapshot ?? [] as $tax)
        <tr>
            <td>{{ $tax['label'] ?? 'Taxe' }}</td>
            <td class="right">{{ $money->format($tax['amount_cents'] ?? 0, $invoiceLanguage) }}</td>
        </tr>
    @endforeach
    <tr class="grand">
        <td>Grand total</td>
        <td class="right">{{ $money->format($invoice->grand_total_cents, $invoiceLanguage) }}</td>
    </tr>
</table>

<div class="footer">
    <div>{{ $invoice->thank_you_message }}</div>
    <div class="muted">{{ $invoice->payment_instructions }}</div>
    <div>Nettoyeur Villeneuve</div>
</div>
</body>
</html>
