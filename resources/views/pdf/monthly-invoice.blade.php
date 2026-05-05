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
        .totals { margin-left: auto; margin-top: 14px; width: 42%; }
        .grand td { color: #0f3f2f; font-size: 13px; font-weight: bold; }
        .footer { border-top: 2px solid #0f3f2f; font-weight: bold; margin-top: 18px; padding-top: 9px; text-align: center; }
    </style>
</head>
<body>
@php
    $client = $invoice->client;
    $invoiceLanguage = $client?->default_language ?? 'fr';
    $categories = collect($invoice->category_snapshot ?? []);
    $singleCategory = $categories->count() === 1;
    $businessLogo = $settings?->logo_path ? public_path('storage/'.$settings->logo_path) : null;
    $clientLogo = $client?->logo_path ? public_path('storage/'.$client->logo_path) : null;
    $entryTotals = $invoice->entries
        ->groupBy(fn ($entry) => $entry->service_day.'-'.($entry->client_category_id ?? 'none'))
        ->map(fn ($entries) => $entries->sum('amount_cents'));
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

<table class="invoice-table">
    <thead>
        <tr>
            <th style="width: 50px;">Jour</th>
            @foreach($categories as $category)
                <th class="right">{{ $singleCategory ? 'Montant' : ($category['name'] ?? 'Montant') }}</th>
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
                    @endphp
                    <td class="right">{{ $sum ? $money->format($sum, $invoiceLanguage) : '' }}</td>
                @endforeach
            </tr>
        @endfor
    </tbody>
</table>

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
