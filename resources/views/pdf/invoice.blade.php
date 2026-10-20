@php
    use App\Support\Money;
    $seller = $invoice['seller'];
    $client = $invoice['client'];
    $intra = $invoice['is_intra_state'];
    $hasDiscount = $invoice['discount_total'] > 0;
    $date = fn ($value) => $value?->format('d M Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $invoice['title'] }} {{ $invoice['display_number'] }}</title>
<style>
    @page { margin: 32px 36px; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #18181b; line-height: 1.45; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 0; }
    .muted { color: #52525b; }
    .faint { color: #71717a; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; }
    .right { text-align: right; }
    .label { font-size: 8px; font-weight: bold; letter-spacing: 0.06em; color: #71717a; text-transform: uppercase; }
    .box { border: 1px solid #e4e4e7; border-radius: 6px; padding: 10px 12px; }
    .items th { background: #f4f4f5; font-size: 8px; letter-spacing: 0.04em; color: #52525b; text-transform: uppercase; padding: 7px 8px; text-align: left; }
    .items td { padding: 8px; border-bottom: 1px solid #e4e4e7; }
    .totals td { padding: 3px 0; }
    .grand td { border-top: 2px solid #18181b; padding-top: 6px; font-size: 12px; font-weight: bold; }
    .draft { position: fixed; top: 300px; left: 0; right: 0; text-align: center; font-size: 110px; color: #f4f4f5; font-weight: bold; letter-spacing: 0.1em; z-index: -1; }
</style>
</head>
<body>
@if ($invoice['is_draft'])
    <div class="draft">DRAFT</div>
@endif

<table>
    <tr>
        <td style="width: 62%;">
            <table>
                <tr>
                    @if ($invoice['logo'])
                        <td style="width: 56px; padding-right: 12px;"><img src="{{ $invoice['logo'] }}" style="width: 52px; height: 52px;" alt=""></td>
                    @endif
                    <td>
                        <div style="font-size: 14px; font-weight: bold;">{{ $seller['legal_name'] ?? '[Legal name]' }}</div>
                        @if (filled($seller['trade_name'] ?? null) && $seller['trade_name'] !== $seller['legal_name'])
                            <div class="muted">Trading as {{ $seller['trade_name'] }}</div>
                        @endif
                        <div class="muted">{{ collect([$seller['address_line'] ?? null, $seller['city'] ?? null, $seller['state_name'] ?? null])->filter()->join(', ') }}{{ filled($seller['pincode'] ?? null) ? ' – '.$seller['pincode'] : '' }}</div>
                        @if (filled($seller['gstin'] ?? null))
                            <div class="muted">GSTIN <b style="color:#18181b">{{ $seller['gstin'] }}</b>@if (filled($seller['pan'] ?? null)) · PAN {{ $seller['pan'] }}@endif</div>
                        @endif
                        @if (filled($seller['udyam_number'] ?? null))
                            <div class="muted">Udyam <b style="color:#18181b">{{ $seller['udyam_number'] }}</b>@if (filled($seller['msme_category'] ?? null)) · {{ $seller['msme_category'] }} enterprise @endif</div>
                        @endif
                        @if (filled($seller['email'] ?? null) || filled($seller['phone'] ?? null))
                            <div class="faint">{{ collect([$seller['email'] ?? null, $seller['phone'] ?? null])->filter()->join(' · ') }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="right">
            <div style="font-size: 17px; font-weight: bold; color: #3730a3; letter-spacing: 0.03em;">{{ strtoupper($invoice['title']) }}</div>
            <div class="mono" style="font-size: 11px; font-weight: bold;">{{ $invoice['display_number'] }}</div>
            <div class="faint">Original for recipient</div>
        </td>
    </tr>
</table>

<table style="margin-top: 16px;">
    <tr>
        <td style="width: 50%; padding-right: 8px;">
            <div class="box">
                <div class="label">Bill to</div>
                <div style="font-size: 11px; font-weight: bold; margin-top: 2px;">{{ $client['name'] }}</div>
                @if (filled($client['billing_address'] ?? null) || filled($client['city'] ?? null))
                    <div class="muted">{{ collect([$client['billing_address'] ?? null, $client['city'] ?? null, $client['state_name'] ?? null])->filter()->join(', ') }}{{ filled($client['pincode'] ?? null) ? ' – '.$client['pincode'] : '' }}</div>
                @endif
                <div class="muted">GSTIN: {{ $client['gstin'] ?? 'Unregistered' }}@if (filled($client['state_code'] ?? null)) · State code {{ $client['state_code'] }}@endif</div>
                @if (filled($client['attention'] ?? null))
                    <div class="muted">Attn: {{ $client['attention'] }}</div>
                @endif
            </div>
        </td>
        <td style="width: 50%; padding-left: 8px;">
            <div class="box">
                <table>
                    <tr><td class="faint">Invoice date</td><td class="right"><b>{{ $date($invoice['issue_date']) }}</b></td></tr>
                    <tr><td class="faint">Due date</td><td class="right"><b>{{ $date($invoice['due_date']) }}</b></td></tr>
                    <tr><td class="faint">Place of supply</td><td class="right"><b>{{ $invoice['place_of_supply_name'] ?? '' }} ({{ $invoice['place_of_supply'] }})</b></td></tr>
                    <tr><td class="faint">Reverse charge</td><td class="right"><b>No</b></td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 16px;">
    <thead>
        <tr>
            <th style="width: 18px;">#</th>
            <th>Description</th>
            <th style="width: 52px;">SAC</th>
            <th class="right" style="width: 34px;">Qty</th>
            <th class="right" style="width: 78px;">Rate</th>
            @if ($hasDiscount)
                <th class="right" style="width: 66px;">Discount</th>
            @endif
            <th class="right" style="width: 40px;">GST</th>
            <th class="right" style="width: 84px;">Taxable</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice['items'] as $index => $item)
            <tr>
                <td class="faint">{{ $index + 1 }}</td>
                <td>
                    <b>{{ $item['description'] }}</b>
                    @if ($item['period_start'] && $item['period_end'])
                        <div class="faint">Period {{ $date($item['period_start']) }} – {{ $date($item['period_end']) }}</div>
                    @endif
                </td>
                <td class="mono">{{ $item['sac_code'] }}</td>
                <td class="right">{{ $item['quantity'] }}</td>
                <td class="right">{{ Money::format($item['unit_price'], false) }}</td>
                @if ($hasDiscount)
                    <td class="right">{{ $item['discount'] > 0 ? Money::format($item['discount'], false) : '–' }}</td>
                @endif
                <td class="right">{{ rtrim(rtrim($item['tax_rate'], '0'), '.') }}%</td>
                <td class="right"><b>{{ Money::format($item['taxable_amount'], false) }}</b></td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 14px;">
    <tr>
        <td style="width: 55%; padding-right: 16px;">
            <table class="items" style="font-size: 8.5px;">
                <thead>
                    <tr>
                        <th>SAC</th>
                        <th class="right">Taxable</th>
                        @if ($intra)
                            <th class="right">CGST</th>
                            <th class="right">SGST</th>
                        @else
                            <th class="right">IGST</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice['tax_summary'] as $row)
                        @php $rate = (float) $row['tax_rate']; @endphp
                        <tr>
                            <td class="mono">{{ $row['sac_code'] }}</td>
                            <td class="right">{{ Money::format($row['taxable'], false) }}</td>
                            @if ($intra)
                                <td class="right">{{ Money::format($row['cgst'], false) }} <span class="faint">@ {{ $rate / 2 }}%</span></td>
                                <td class="right">{{ Money::format($row['sgst'], false) }} <span class="faint">@ {{ $rate / 2 }}%</span></td>
                            @else
                                <td class="right">{{ Money::format($row['igst'], false) }} <span class="faint">@ {{ $rate }}%</span></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td>
            <table class="totals">
                <tr><td class="muted">Taxable value</td><td class="right">{{ Money::format($invoice['taxable_total']) }}</td></tr>
                @if ($intra)
                    <tr><td class="muted">CGST</td><td class="right">{{ Money::format($invoice['cgst_total']) }}</td></tr>
                    <tr><td class="muted">SGST</td><td class="right">{{ Money::format($invoice['sgst_total']) }}</td></tr>
                @else
                    <tr><td class="muted">IGST</td><td class="right">{{ Money::format($invoice['igst_total']) }}</td></tr>
                @endif
                @if ($invoice['round_off'] !== 0)
                    <tr><td class="muted">Round off</td><td class="right">{{ Money::format($invoice['round_off']) }}</td></tr>
                @endif
                <tr class="grand"><td>Total</td><td class="right">{{ Money::format($invoice['total']) }}</td></tr>
                @if ($invoice['amount_settled'] > 0)
                    <tr><td class="muted">Received</td><td class="right">{{ Money::format(-$invoice['amount_settled']) }}</td></tr>
                    <tr><td><b>Balance due</b></td><td class="right"><b>{{ Money::format($invoice['balance_due']) }}</b></td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<div style="margin-top: 12px; padding: 8px 10px; background: #f4f4f5; border-radius: 6px;">
    <span class="muted">Amount in words:</span> <b>{{ $invoice['amount_in_words'] }}</b>
</div>

<table style="margin-top: 14px;">
    <tr>
        <td style="padding-right: 12px;">
            <div class="box">
                <div class="label" style="margin-bottom: 4px;">Pay to</div>
                <table>
                    <tr><td class="faint" style="width: 90px;">Account name</td><td>{{ $seller['bank_account_name'] ?? '' }}</td></tr>
                    @if (filled($seller['bank_name'] ?? null))
                        <tr><td class="faint">Bank</td><td>{{ $seller['bank_name'] }}{{ filled($seller['bank_branch'] ?? null) ? ', '.$seller['bank_branch'] : '' }}</td></tr>
                    @endif
                    @if (filled($seller['bank_account_number'] ?? null))
                        <tr><td class="faint">Account no.</td><td class="mono">{{ $seller['bank_account_number'] }}</td></tr>
                    @endif
                    @if (filled($seller['bank_ifsc'] ?? null))
                        <tr><td class="faint">IFSC</td><td class="mono">{{ $seller['bank_ifsc'] }}</td></tr>
                    @endif
                    @if (filled($seller['upi_id'] ?? null))
                        <tr><td class="faint">UPI</td><td>{{ $seller['upi_id'] }}</td></tr>
                    @endif
                </table>
            </div>
        </td>
        @if ($invoice['upi_qr'])
            <td style="width: 130px;">
                <div class="box" style="text-align: center;">
                    <img src="{{ $invoice['upi_qr'] }}" style="width: 96px; height: 96px;" alt="UPI QR code">
                    <div style="font-weight: bold; margin-top: 4px;">Scan to pay {{ Money::format($invoice['balance_due']) }}</div>
                </div>
            </td>
        @endif
    </tr>
</table>

@if (filled($invoice['notes']))
    <div style="margin-top: 12px;" class="muted">{!! nl2br(e($invoice['notes'])) !!}</div>
@endif

<table style="margin-top: 18px;">
    <tr>
        <td style="padding-right: 24px; font-size: 8.5px;" class="muted">
            <div class="label" style="margin-bottom: 3px;">Terms</div>
            @foreach ($invoice['terms'] as $index => $term)
                <div>{{ $index + 1 }}. {{ $term }}</div>
            @endforeach
        </td>
        <td style="width: 180px; text-align: center;">
            <div class="muted" style="font-size: 8.5px;">For {{ $seller['legal_name'] ?? '' }}</div>
            <div style="height: 40px; border-bottom: 1px solid #a1a1aa;"></div>
            <div style="font-size: 8.5px; font-weight: bold; margin-top: 3px;">{{ ($seller['signatory_name'] ?? null) ?: 'Authorised signatory' }}</div>
        </td>
    </tr>
</table>

<div class="faint" style="text-align: center; margin-top: 18px; font-size: 8px;">This is a computer-generated invoice.</div>
</body>
</html>
