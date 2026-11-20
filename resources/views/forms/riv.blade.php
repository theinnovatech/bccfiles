@extends('forms.layout')

{{-- LOCAL RIV - portrait, from "Supply Unit receiptreports_1.pdf" (SDO_Reports_Reference_Pack). --}}

@section('styles')
    .riv-grid { border: 1.4px solid #000; }
    .riv-grid th { font-size: 10px; height: 18px; }
    .riv-grid td { height: 18px; font-size: 9px; }
    .sign-label { font-size: 8px; font-weight: bold; }
@endsection

@section('content')
    @php
        $minRows = 16;
        $filler = max(0, $minRows - count($form['lines']));
        $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
    @endphp

    <table style="width: 100%;">
        <tr>
            <td style="width: 26%; text-align: right; padding-right: 14px;"><img class="logo" src="{{ $logos['deped'] }}" alt=""></td>
            <td style="width: 48%;" class="center">
                <div class="bold" style="font-size: 11px;">{{ \App\Services\FormService::RIV_HEADER[0] }}</div>
                <div style="font-size: 9.5px; margin-top: 2px;">{{ \App\Services\FormService::RIV_HEADER[1] }}</div>
                <div class="bold" style="font-size: 10px; margin-top: 2px;">{{ \App\Services\FormService::RIV_HEADER[2] }}</div>
                <div style="font-size: 9.5px; margin-top: 2px;">{{ \App\Services\FormService::RIV_HEADER[3] }}</div>
            </td>
            <td style="width: 26%; padding-left: 14px;"><img class="logo" src="{{ $logos['sdo'] }}" alt=""></td>
        </tr>
    </table>

    <table style="width: 34%; margin: 6px 0 0 auto; font-size: 10px;">
        <tr>
            <td class="bold" style="width: 34px;">No:</td>
            <td class="line" style="font-size: 9.5px;">{{ $form['number'] ?? '' }}&nbsp;</td>
        </tr>
        <tr><td colspan="2" style="height: 8px;"></td></tr>
        <tr>
            <td class="bold">Date:</td>
            <td class="line" style="font-size: 9.5px;">{{ $form['date'] ? \Carbon\Carbon::parse($form['date'])->format('F j, Y') : '' }}&nbsp;</td>
        </tr>
    </table>

    <div class="center bold" style="font-size: 18px; margin: 10px 0 12px;">LOCAL RIV</div>
    <div style="font-size: 10px;">To Supply Office:</div>
    <div style="font-size: 10px; margin: 2px 0 2px 36px;">Pls. furnish the following:</div>

    <table class="grid ruled riv-grid">
        <thead>
            <tr>
                <th style="width: 7%;">QTY</th>
                <th style="width: 9%;">UNIT</th>
                <th style="width: 55%;">ITEM/DESCRIPTION</th>
                <th style="width: 14%;">UNIT COST</th>
                <th style="width: 15%;">TOTAL COST</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($form['lines'] as $line)
                <tr>
                    <td class="center">{{ number_format($line['quantity']) }}</td>
                    <td class="center">{{ $line['unit'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $money($line['unit_cost']) }}</td>
                    <td class="num">{{ $money($line['total_cost']) }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $filler; $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 18px; page-break-inside: avoid;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-top: 14px;">
                <div class="sign-label">APPROVED;</div>
                <div style="height: 22px;"></div>
                <div class="bold" style="font-size: 10px; display: inline-block; border-bottom: 0.8px solid #000; min-width: 160px;">{{ strtoupper($form['approved_by']['name'] ?? '') }}&nbsp;</div>
                <div style="font-size: 9.5px; margin-top: 2px;">{{ $form['approved_by']['designation'] ?? '' }}</div>
            </td>
            <td style="width: 14%;"></td>
            <td style="width: 34%; vertical-align: top;">
                <div class="sign-label">REQUESTED BY:</div>
                <div class="line center" style="margin-top: 16px; font-weight: bold;">{{ $form['requested_by']['name'] ?? '' }}&nbsp;</div>
                <div class="center" style="font-size: 7.5px;">{{ $form['requested_by']['designation'] ?? '' }}</div>
                <div class="sign-label" style="margin-top: 12px;">RECEIVED BY:</div>
                <div class="line center" style="margin-top: 16px; font-weight: bold;">{{ $form['received_by']['name'] ?? '' }}&nbsp;</div>
                <div class="center" style="font-size: 7.5px;">{{ $form['received_by']['designation'] ?? '' }}</div>
            </td>
        </tr>
    </table>
@endsection
