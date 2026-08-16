<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { color: #173b31; font-family: Helvetica, Arial, sans-serif; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cfdad3; padding: 4px 5px; vertical-align: top; }
        th { background: #e8f4ed; color: #0f3f2f; font-weight: bold; text-align: left; }
        .no-border, .no-border td { border: 0; }
        .header { border-bottom: 3px solid #0f3f2f; margin-bottom: 12px; padding-bottom: 8px; }
        .brand { color: #0f3f2f; font-size: 20px; font-weight: bold; }
        .muted { color: #65736d; }
        .right { text-align: right; }
        .center { text-align: center; }
        .logo { max-height: 46px; max-width: 105px; }
        .client-box { margin-bottom: 12px; }
        .monthly-table td { height: 13px; }
        .monthly-table .subtotal td { background: #e8f4ed; font-weight: bold; }
        .heading { color: #0f3f2f; font-size: 14px; font-weight: bold; margin: 12px 0 6px; }
        .detail-table { table-layout: fixed; }
        .detail-table tr { page-break-inside: avoid; }
        .detail-table th, .detail-table td { padding: 5px 4px; }
        .totals { margin-left: auto; margin-top: 12px; width: 46%; }
        .grand td { color: #0f3f2f; font-size: 12px; font-weight: bold; }
        .footer { border-top: 2px solid #0f3f2f; font-weight: bold; margin-top: 14px; padding-top: 8px; text-align: center; }
        .page-break { page-break-before: always; }
        body.style-hotel .header { border-bottom-color: #1d6f50; }
        body.style-hotel th { background: #dff2ea; color: #0b4b35; }
        body.style-hotel .brand, body.style-hotel .grand td { color: #0b4b35; }
    </style>
</head>
<body class="style-{{ $invoice->client?->invoice_style ?? 'standard' }}">
@php
    $client = $invoice->client;
    $invoiceLanguage = $client?->default_language ?? 'fr';
    $businessLogo = $settings?->logo_path ? public_path('storage/'.$settings->logo_path) : null;
    $clientLogo = $client?->logo_path ? public_path('storage/'.$client->logo_path) : null;
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
                <div><strong>Type: employés et clients</strong></div>
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

<table class="monthly-table">
    <thead>
        <tr>
            <th style="width: 55px;">Jour</th>
            <th class="right">EMPLOYÉS</th>
            <th class="right">CLIENTS</th>
            <th class="right">TOTAL</th>
        </tr>
    </thead>
    <tbody>
        @for($day = 1; $day <= 31; $day++)
            @php($daily = $dailyBillingTotals->get($day))
            <tr>
                <td><strong>{{ $day }}</strong></td>
                <td class="right">{{ $daily['employee'] ? $money->format($daily['employee'], $invoiceLanguage) : '' }}</td>
                <td class="right">{{ $daily['hotel_guest'] ? $money->format($daily['hotel_guest'], $invoiceLanguage) : '' }}</td>
                <td class="right"><strong>{{ array_sum($daily) ? $money->format(array_sum($daily), $invoiceLanguage) : '' }}</strong></td>
            </tr>
        @endfor
        <tr class="subtotal">
            <td>Sous-totaux</td>
            <td class="right">{{ $money->format($billingSubtotals['employee'], $invoiceLanguage) }}</td>
            <td class="right">{{ $money->format($billingSubtotals['hotel_guest'], $invoiceLanguage) }}</td>
            <td class="right">{{ $money->format(array_sum($billingSubtotals), $invoiceLanguage) }}</td>
        </tr>
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

<div class="page-break"></div>
<div class="heading">Détail des items facturés</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 28px;">Jour</th>
            <th style="width: 58px;">Type</th>
            <th style="width: 105px;">Nom</th>
            <th style="width: 82px;">Référence</th>
            <th>Item</th>
            <th class="right" style="width: 34px;">Qté</th>
            <th class="right" style="width: 58px;">Prix unit.</th>
            <th class="right" style="width: 62px;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineItems as $lineItem)
            <tr>
                <td><strong>{{ $lineItem['day'] }}</strong></td>
                <td>{{ $lineItem['billing_label'] }}</td>
                <td><strong>{{ $lineItem['person_name'] ?: '—' }}</strong></td>
                <td>
                    {{ $lineItem['reference_label'] }}: {{ $lineItem['reference_number'] ?: '—' }}
                    @if($lineItem['department_number'])
                        <br><span class="muted">Dépt.: {{ $lineItem['department_number'] }}</span>
                    @endif
                </td>
                <td>
                    <strong>{{ $lineItem['label'] }}</strong>
                    @if($lineItem['service'])
                        <br><span class="muted" style="font-size: 7px;">{{ $lineItem['service'] }}</span>
                    @endif
                </td>
                <td class="right">
                    {{ $lineItem['quantity'] !== null ? rtrim(rtrim(number_format((float) $lineItem['quantity'], 2, ',', ' '), '0'), ',') : '—' }}
                </td>
                <td class="right">
                    {{ $lineItem['unit_price_cents'] !== null ? $money->format($lineItem['unit_price_cents'], $invoiceLanguage) : '—' }}
                </td>
                <td class="right"><strong>{{ $money->format($lineItem['total_cents'], $invoiceLanguage) }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center" style="padding: 18px;">Aucun item facturé.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <div>{{ $invoice->thank_you_message }}</div>
    <div class="muted">{{ $invoice->payment_instructions }}</div>
    <div>Nettoyeur Villeneuve</div>
</div>
</body>
</html>
