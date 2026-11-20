@extends('forms.layout')

{{-- INVENTORY CUSTODIAN SLIP - portrait, from "Supply Unit receiptreports_2.pdf" (SDO_Reports_Reference_Pack).
     The whole slip sits in one ruled box; the body has vertical rules only, as on the source. --}}

@section('margin', '24px 26px 34px 26px')
@section('logo', '64px')

@section('styles')
    .slip { width: 100%; border: 1.4px solid #000; }
    .ics-grid { width: 100%; }
    .ics-grid th { font-size: 7.5px; border: 0.8px solid #000; padding: 2px; }
    .ics-grid td { font-size: 8.5px; border-left: 0.8px solid #000; border-right: 0.8px solid #000; padding: 3px; vertical-align: top; }
    .ics-grid td.first { border-left: 0; }
    .ics-grid th.first { border-left: 0; }
    .ics-grid td.last, .ics-grid th.last { border-right: 0; }
@endsection

@section('content')
    @php
        $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
        $bodyHeight = max(80, 470 - 30 * count($form['lines']));
    @endphp

    <table class="slip">
        <tr>
            <td style="padding: 10px 6px 4px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 20%; text-align: right;"><img class="logo" src="{{ $logos['deped'] }}" alt=""></td>
                        <td style="width: 60%;" class="center">
                            <div class="bold" style="font-size: 16px; display: inline-block; border-bottom: 0.8px solid #000; padding-bottom: 2px;">INVENTORY CUSTODIAN SLIP</div>
                            @foreach (\App\Services\FormService::ICS_HEADER as $line)
                                <div class="bold" style="font-size: 9.5px; margin-top: {{ $loop->first ? 6 : 1 }}px;">{{ $line }}</div>
                            @endforeach
                        </td>
                        <td style="width: 20%;"><img class="logo" src="{{ $logos['sdo'] }}" alt=""></td>
                    </tr>
                </table>

                <table style="width: 100%; margin-top: 12px; font-size: 9px;">
                    <tr>
                        <td style="width: 15%;">ENTITY NAME:</td>
                        <td class="line" style="width: 40%;">{{ $form['entity_name'] ?? '' }}&nbsp;</td>
                        <td style="width: 3%;"></td>
                        <td style="width: 10%;">ICS NO.</td>
                        <td class="line bold" style="width: 32%; font-size: 10px;">{{ $form['number'] ?? '' }}&nbsp;</td>
                    </tr>
                    <tr>
                        <td>FUND CLUSTER:</td>
                        <td class="line">{{ $form['fund_cluster'] ?? '' }}&nbsp;</td>
                        <td colspan="3"></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 0;">
                <table class="ics-grid">
                    <thead>
                        <tr>
                            <th rowspan="2" class="first" style="width: 7%;">QTY</th>
                            <th rowspan="2" style="width: 7%;">UNIT</th>
                            <th colspan="2" style="width: 20%;">AMOUNT</th>
                            <th rowspan="2" style="width: 36%;">DESCRIPTION</th>
                            <th rowspan="2" style="width: 16%;">Inventory<br>Item No.</th>
                            <th rowspan="2" class="last" style="width: 14%;">Estimated<br>Useful Life</th>
                        </tr>
                        <tr>
                            <th style="width: 10%;">UNIT COST</th>
                            <th style="width: 10%;">TOTAL COST</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($form['lines'] as $line)
                            <tr>
                                <td class="first center">{{ number_format($line['quantity']) }}</td>
                                <td class="center">{{ $line['unit'] }}</td>
                                <td class="num">{{ $money($line['unit_cost']) }}</td>
                                <td class="num">{{ $money($line['total_cost']) }}</td>
                                <td>{{ $line['description'] }}</td>
                                <td class="center">{!! nl2br(e(implode("\n", $line['item_numbers']))) !!}</td>
                                <td class="last center">{{ $line['useful_life'] }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="first" style="height: {{ $bodyHeight }}px;"></td><td></td><td></td><td></td>
                            <td class="center" style="vertical-align: bottom; padding-bottom: 16px;">{!! nl2br(e(implode("\n", $form['details'] ?? []))) !!}</td>
                            <td></td><td class="last"></td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 0; border-top: 0.8px solid #000;">
                <table style="width: 100%; page-break-inside: avoid;">
                    <tr>
                        <td style="width: 50%; border-right: 0.8px solid #000; padding: 3px 6px 8px; vertical-align: top;">
                            <div class="bold" style="font-size: 8.5px;">RECEIVED FROM:</div>
                            <div class="line center bold" style="margin: 24px 26px 0;">{{ $form['received_from']['name'] ?? '' }}&nbsp;</div>
                            <div class="center" style="font-size: 7.5px;">{{ $form['received_from']['designation'] ?? '' }}</div>
                        </td>
                        <td style="width: 50%; padding: 3px 6px 8px; vertical-align: top;">
                            <div class="bold" style="font-size: 8.5px;">RECEIVED BY:</div>
                            <div class="line center bold" style="margin: 24px 26px 0;">{{ $form['received_by']['name'] ?? '' }}&nbsp;</div>
                            <div class="center" style="font-size: 7.5px;">{{ $form['received_by']['designation'] ?? '' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
