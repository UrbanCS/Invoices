@php
    $language = in_array($client->default_language, ['fr', 'en'], true) ? $client->default_language : 'fr';
    $t = fn ($key, $replace = []) => trans('statement.'.$key, $replace, $language);
    $i = fn ($key) => trans('invoice.'.$key, [], $language);
    $parameters = ['month' => $month, 'year' => $year, 'client_id' => $client->id];
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t('title') }} - {{ $client->name }} - {{ $month }}/{{ $year }}</title>
    <style>
        @page { margin: 32px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #173b31; font-size: 10px; line-height: 1.4; }
        h1 { font-size: 23px; margin: 0 0 5px; }
        h2 { font-size: 18px; margin: 16px 0 4px; }
        .header { border-bottom: 3px solid #173b31; padding-bottom: 14px; }
        .muted { color: #5b6862; }
        .client { padding: 14px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cfdbd4; padding: 7px 5px; vertical-align: top; }
        th { background: #e8f4ed; text-align: left; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .right { text-align: right; white-space: nowrap; }
        .totals { width: 55%; margin: 18px 0 0 auto; page-break-inside: avoid; }
        .grand { font-weight: bold; background: #e8f4ed; }
        .note { margin-top: 18px; }
        .closing { page-break-inside: avoid; }
        .actions { margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 12px; }
        .actions a { display: inline-block; padding: 10px 16px; color: white; background: #173b31; border-radius: 6px; font-size: 14px; text-decoration: none; }
        @media print { .actions { display: none; } }
        @if(!$isPdf)
        body { max-width: 1050px; margin: 32px auto; padding: 0 20px; font-size: 14px; }
        .table-wrap { overflow-x: auto; }
        @endif
    </style>
</head>
<body>
@if(!$isPdf)
    <nav class="actions">
        <a href="{{ route('account-statements.index', $parameters) }}">{{ $t('back') }}</a>
        <a href="{{ route('account-statements.summary', [...$parameters, 'pdf' => 1]) }}">{{ $t('download') }}</a>
    </nav>
@endif
<header class="header">
    <h1>{{ $settings?->display_name ?? 'Nettoyeur Villeneuve' }}</h1>
    <div>{{ $settings?->legal_name }}</div>
    <div>{{ trim(($settings?->address ?? '').' '.($settings?->city ?? '').' '.($settings?->province ?? '').' '.($settings?->postal_code ?? '')) }}</div>
    <h2>{{ $t('title') }}</h2>
    <div>{{ $t('subtitle') }} - {{ sprintf('%02d/%04d', $month, $year) }}</div>
    <div class="muted">{{ $t('generated') }} {{ now()->format('Y-m-d') }}</div>
</header>
<div class="client">
    <strong>{{ $client->name }}</strong><br>
    {{ $client->billing_address }}<br>
    {{ trim(($client->city ?? '').' '.($client->province ?? '').' '.($client->postal_code ?? '')) }}
</div>
<p>{{ $t('count', ['count' => $statement['rows']->count()]) }}</p>
<div class="table-wrap">
    <table>
        <thead><tr>
            <th>{{ $i('date') }}</th><th>{{ $i('invoice') }}</th><th>{{ $i('status') }}</th>
            <th class="right">{{ $t('net') }}</th><th class="right">{{ $t('taxes') }}</th>
            <th class="right">{{ $i('total') }}</th><th class="right">{{ $t('paid') }}</th><th class="right">{{ $t('balance') }}</th>
        </tr></thead>
        <tbody>
            @foreach($statement['rows'] as $row)
                <tr>
                    <td>{{ $row['invoice']->invoice_date?->format('Y-m-d') }}</td>
                    <td>{{ $row['invoice']->invoice_number }}</td>
                    <td>{{ $i('statuses.'.$row['invoice']->status) }}</td>
                    @foreach(['net_cents', 'tax_cents', 'total_cents', 'paid_cents', 'balance_cents'] as $field)
                        <td class="right">{{ $money->format($row[$field], $language) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="closing">
<table class="totals">
    @foreach(['net_cents' => 'net', 'tax_cents' => 'taxes', 'total_cents' => 'invoiced', 'paid_cents' => 'paid', 'balance_cents' => 'balance_total'] as $field => $label)
        <tr class="{{ $field === 'balance_cents' ? 'grand' : '' }}"><td>{{ $t($label) }}</td><td class="right">{{ $money->format($statement[$field], $language) }}</td></tr>
    @endforeach
</table>
<p class="note muted">{{ $t('explanation') }}</p>
<p class="muted">{{ $t('balance_note') }}</p>
</div>
</body>
</html>
