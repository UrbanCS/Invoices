@php
    $client = $invoice->client;
    $invoiceLanguage = in_array($client?->default_language, ['fr', 'en'], true)
        ? $client->default_language
        : 'fr';
    $t = fn (string $key, array $replace = []) => trans('invoice.'.$key, $replace, $invoiceLanguage);
    $businessLogo = $settings?->logo_path ? public_path('storage/'.$settings->logo_path) : null;
    $clientLogo = $client?->logo_path ? public_path('storage/'.$client->logo_path) : null;
    $formatQuantity = fn ($quantity) => $quantity === null
        ? '—'
        : rtrim(rtrim(number_format(
            (float) $quantity,
            2,
            $invoiceLanguage === 'fr' ? ',' : '.',
            $invoiceLanguage === 'fr' ? ' ' : ',',
        ), '0'), $invoiceLanguage === 'fr' ? ',' : '.');
@endphp
<!doctype html>
<html lang="{{ $invoiceLanguage }}">
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
        .monthly-table td { height: 10px; padding-top: 2px; padding-bottom: 2px; }
        .monthly-table .subtotal td { background: #e8f4ed; font-weight: bold; }
        .heading { color: #0f3f2f; font-size: 14px; font-weight: bold; margin: 12px 0 6px; }
        .detail-table { table-layout: fixed; }
        .detail-table tr, .detail-group { page-break-inside: avoid; }
        .detail-table th, .detail-table td { padding: 5px 4px; }
        .detail-stack > div { min-height: 24px; }
        .detail-stack > div + div { border-top: 1px solid #e4ebe7; margin-top: 4px; padding-top: 4px; }
        .totals { margin-left: auto; margin-top: 12px; width: 46%; }
        .invoice-closing { page-break-inside: avoid; }
        .grand td { color: #0f3f2f; font-size: 12px; font-weight: bold; }
        .page-break { page-break-before: always; }
        body.style-hotel .header { border-bottom-color: #1d6f50; }
        body.style-hotel th { background: #dff2ea; color: #0b4b35; }
        body.style-hotel .brand, body.style-hotel .grand td { color: #0b4b35; }
    </style>
</head>
<body class="style-{{ $invoice->client?->invoice_style ?? 'standard' }}">
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
                <div>{{ $t('gst_hst') }}: {{ $settings?->gst_number }} @if($settings?->qst_number) &nbsp; {{ $t('qst') }}: {{ $settings->qst_number }} @endif</div>
            </td>
            <td class="right" style="width: 210px;">
                <div class="brand">{{ $t('invoice') }} {{ $invoice->invoice_number }}</div>
                <div>{{ $t('date') }}: {{ $invoice->invoice_date?->format('Y-m-d') }}</div>
                <div>{{ $t('period') }}: {{ $invoice->invoice_month }}/{{ $invoice->invoice_year }}</div>
                <div><strong>{{ $t('type') }}: {{ $t('invoice_type') }}</strong></div>
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
            <strong>{{ $t('bill_to') }}</strong><br>
            {{ $client?->name ?? $t('deleted_client') }}<br>
            {{ $client?->billing_address }}<br>
            {{ trim(($client?->city ?? '').' '.($client?->province ?? '').' '.($client?->postal_code ?? '')) }}
        </td>
        <td>
            <strong>{{ $t('status') }}</strong><br>
            {{ $t('statuses.'.$invoice->status) }}
            @if($invoice->notes)
                <br><br><strong>{{ $t('notes') }}</strong><br>{{ $invoice->notes }}
            @endif
        </td>
    </tr>
</table>

<table class="monthly-table">
    <thead>
        <tr>
            <th style="width: 55px;">{{ $t('day') }}</th>
            <th class="right">{{ $t('employees') }}</th>
            <th class="right">{{ $t('clients') }}</th>
            <th class="right">{{ $t('total') }}</th>
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
            <td>{{ $t('subtotals') }}</td>
            <td class="right">{{ $money->format($billingSubtotals['employee'], $invoiceLanguage) }}</td>
            <td class="right">{{ $money->format($billingSubtotals['hotel_guest'], $invoiceLanguage) }}</td>
            <td class="right">{{ $money->format(array_sum($billingSubtotals), $invoiceLanguage) }}</td>
        </tr>
    </tbody>
</table>

<div class="page-break"></div>
<div class="heading">{{ $t('detail_heading') }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 28px;">{{ $t('day') }}</th>
            <th style="width: 58px;">{{ $t('type') }}</th>
            <th style="width: 105px;">{{ $t('name') }}</th>
            <th style="width: 82px;">{{ $t('reference') }}</th>
            <th>{{ $t('item') }}</th>
            <th class="right" style="width: 34px;">{{ $t('quantity') }}</th>
            <th class="right" style="width: 58px;">{{ $t('unit_price') }}</th>
            <th class="right" style="width: 62px;">{{ $t('total') }}</th>
        </tr>
    </thead>
    @forelse($groupedLineItems as $group)
        <tbody class="detail-group">
            @foreach($group['items'] as $item)
                <tr>
                    @if($loop->first)
                        <td rowspan="{{ $group['items']->count() }}"><strong>{{ $group['day'] }}</strong></td>
                        <td rowspan="{{ $group['items']->count() }}">{{ $t('billing_types.'.$group['billing_type']) }}</td>
                        <td rowspan="{{ $group['items']->count() }}"><strong>{{ $group['person_name'] ?: '—' }}</strong></td>
                        <td rowspan="{{ $group['items']->count() }}">
                            {{ $t('tag_number') }}: {{ $group['reference_number'] ?: '—' }}
                            @if($group['room_number'])
                                <br><span class="muted">{{ $t('room_number') }}: {{ $group['room_number'] }}</span>
                            @endif
                            @if($group['department_number'])
                                <br><span class="muted">{{ $t('department') }}: {{ $group['department_number'] }}</span>
                            @endif
                        </td>
                    @endif
                    <td><strong>{{ $item['label'] }}</strong></td>
                    <td class="right">{{ $formatQuantity($item['quantity']) }}</td>
                    <td class="right">{{ $item['unit_price_cents'] !== null ? $money->format($item['unit_price_cents'], $invoiceLanguage) : '—' }}</td>
                    @if($loop->first)
                        <td rowspan="{{ $group['items']->count() }}" class="right group-total"><strong>{{ $money->format($group['total_cents'], $invoiceLanguage) }}</strong></td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    @empty
        <tbody><tr><td colspan="8" class="center" style="padding: 18px;">{{ $t('no_items') }}</td></tr></tbody>
    @endforelse
</table>

<div class="invoice-closing">
<table class="totals">
    <tr>
        <td>{{ $t('subtotal') }}</td>
        <td class="right">{{ $money->format($invoice->subtotal_cents, $invoiceLanguage) }}</td>
    </tr>
    @if($invoice->adjustments->isNotEmpty())
        <tr><th colspan="2">{{ $t('adjustments') }}</th></tr>
    @endif
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
            <td>{{ $tax['label'] ?? $t('tax') }}</td>
            <td class="right">{{ $money->format($tax['amount_cents'] ?? 0, $invoiceLanguage) }}</td>
        </tr>
    @endforeach
    <tr class="grand">
        <td>{{ $t('grand_total') }}</td>
        <td class="right">{{ $money->format($invoice->grand_total_cents, $invoiceLanguage) }}</td>
    </tr>
</table>
</div>
</body>
</html>
