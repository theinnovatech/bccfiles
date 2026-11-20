@extends('forms.layout')

{{-- INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE SEMI-EXPENDABLE PROPERTY - landscape, from
     "Supply Unit receiptreports_3.pdf" (SDO_Reports_Reference_Pack). 17 leaf columns: nine under
     INVENTORY and eight under INSPECTION and DISPOSAL (DISPOSAL, Appraised Value, RECORD OF SALE). --}}

@section('margin', '20px 22px 30px 22px')
@section('logo', '42px')

@section('styles')
    .iirup { width: 100%; font-size: 6.6px; }
    .iirup th { border: 0.8px solid #000; font-size: 6.6px; padding: 1px 2px; }
    .iirup td { border: 0.8px solid #000; padding: 2px; vertical-align: top; }
    .iirup .band th { font-size: 8px; }
    .cert td { vertical-align: top; font-size: 6.8px; }
@endsection

@section('content')
    @php
        $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
        $qty = fn ($v) => $v === null ? '' : number_format((int) $v);
        $filler = max(0, 4 - count($form['lines']));
        $widths = [5.6, 9, 8.6, 3.2, 5.2, 5.1, 6.1, 4.7, 7.3, 3, 3.8, 4.8, 4.6, 2.9, 4, 4.2, 3.7];
    @endphp

    <table style="width: 100%;">
        <tr>
            <td style="width: 22%; text-align: right; padding-right: 20px;"><img class="logo" src="{{ $logos['deped'] }}" alt=""></td>
            <td style="width: 56%;" class="center">
                <div class="bold" style="font-size: 11px;">{{ \App\Services\FormService::IIRUP_TITLE }}</div>
                <div style="font-size: 8.5px;">As Of {{ \Carbon\Carbon::parse($form['as_of'])->format('F j, Y') }}</div>
                @if (!empty($form['number']))<div class="bold" style="font-size: 8.5px;">IIRUP No. {{ $form['number'] }}</div>@endif
            </td>
            <td style="width: 22%; padding-left: 20px;"><img class="logo" src="{{ $logos['sdo'] }}" alt=""></td>
        </tr>
    </table>

    <table style="width: 100%; margin: 6px 0 4px; font-size: 8px;">
        <tr>
            <td style="width: 7%; vertical-align: bottom;" class="bold">Entity Name:</td>
            <td style="width: 15%; vertical-align: bottom;">{{ $form['entity_name'] ?? '' }}</td>
            <td style="width: 20%;" class="center">
                <div class="bold line">{{ $form['accountable_officer']['name'] ?? '' }}&nbsp;</div>
                <div class="caption">(Name of Accountable Officer)</div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 20%;" class="center">
                <div class="bold line">{{ $form['accountable_officer']['designation'] ?? '' }}&nbsp;</div>
                <div class="caption">(Designation)</div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 14%;" class="center">
                <div class="bold line">{{ $form['station'] ?? '' }}&nbsp;</div>
                <div class="caption">(Station)</div>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 14%; vertical-align: top;">
                <div class="bold">Fund Cluster:</div>
                <div>{{ $form['fund_cluster'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <table class="iirup">
        <thead>
            <tr class="band">
                <th colspan="9">INVENTORY</th>
                <th colspan="8">INSPECTION and DISPOSAL</th>
            </tr>
            <tr>
                <th rowspan="2" style="width: {{ $widths[0] }}%;">Date</th>
                <th rowspan="2" style="width: {{ $widths[1] }}%;">Particulars/ Articles</th>
                <th rowspan="2" style="width: {{ $widths[2] }}%;">Semi- expendable Property No.</th>
                <th rowspan="2" style="width: {{ $widths[3] }}%;">QTY</th>
                <th rowspan="2" style="width: {{ $widths[4] }}%;">Unit Cost</th>
                <th rowspan="2" style="width: {{ $widths[5] }}%;">Total Cost</th>
                <th rowspan="2" style="width: {{ $widths[6] }}%;">Accumulated Impairment Losses</th>
                <th rowspan="2" style="width: {{ $widths[7] }}%;">Carrying Amount</th>
                <th rowspan="2" style="width: {{ $widths[8] }}%;">Remarks</th>
                <th colspan="5">DISPOSAL</th>
                <th rowspan="2" style="width: {{ $widths[14] }}%;">Appraised Value</th>
                <th colspan="2">RECORD OF SALE</th>
            </tr>
            <tr>
                <th style="width: {{ $widths[9] }}%;">Sale</th>
                <th style="width: {{ $widths[10] }}%;">Transfer</th>
                <th style="width: {{ $widths[11] }}%;">Destruction</th>
                <th style="width: {{ $widths[12] }}%;">Others (Specify)</th>
                <th style="width: {{ $widths[13] }}%;">Total</th>
                <th style="width: {{ $widths[15] }}%;">OR No.</th>
                <th style="width: {{ $widths[16] }}%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($form['lines'] as $line)
                <tr>
                    <td class="center">{{ $line['date'] ? \Carbon\Carbon::parse($line['date'])->format('m/d/Y') : '' }}</td>
                    <td>{{ $line['particulars'] }}</td>
                    <td class="center">{{ $line['property_number'] }}</td>
                    <td class="center">{{ $qty($line['quantity']) }}</td>
                    <td class="num">{{ $money($line['unit_cost']) }}</td>
                    <td class="num">{{ $money($line['total_cost']) }}</td>
                    <td class="num">{{ $money($line['impairment']) }}</td>
                    <td class="num">{{ $money($line['carrying_amount']) }}</td>
                    <td>{{ $line['remarks'] }}</td>
                    <td class="center">{{ $qty($line['sale']) }}</td>
                    <td class="center">{{ $qty($line['transfer']) }}</td>
                    <td class="center">{{ $qty($line['destruction']) }}</td>
                    <td class="center">
                        @if ($line['others'] !== null){{ $qty($line['others']) }}@if ($line['others_specify'])<br>@endif @endif{{ $line['others_specify'] }}
                    </td>
                    <td class="center">{{ $qty($line['disposal_total']) }}</td>
                    <td class="num">{{ $money($line['appraised_value']) }}</td>
                    <td class="center">{{ $line['or_number'] }}</td>
                    <td class="num">{{ $money($line['sale_amount']) }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $filler; $i++)
                <tr>@for ($c = 0; $c < 17; $c++)<td style="height: 22px;"></td>@endfor</tr>
            @endfor
            <tr>
                <td class="bold">Total</td>
                <td></td><td></td><td></td><td></td>
                <td class="num bold">{{ $money($form['total_cost']) }}</td>
                @for ($c = 0; $c < 11; $c++)<td></td>@endfor
            </tr>
        </tbody>
    </table>

    <table class="cert" style="width: 100%; border: 0.8px solid #000; border-top: 0; page-break-inside: avoid;">
        <tr>
            <td style="width: 61%; border-right: 0.8px solid #000; padding: 5px 8px 6px;">
                <div>{{ \App\Services\FormService::IIRUP_REQUEST_TEXT }}</div>
                <table style="width: 100%; margin-top: 10px;">
                    <tr>
                        <td style="width: 47%;">
                            <div class="bold" style="font-size: 7.5px;">Requested by:</div>
                            <div class="line center bold" style="margin-top: 10px;">{{ $form['accountable_officer']['name'] ?? '' }}&nbsp;</div>
                            <div class="caption">(Signature over Printed Name of Accountable Officer)</div>
                            <div class="line center" style="margin-top: 10px;">{{ $form['accountable_officer']['designation'] ?? '' }}&nbsp;</div>
                            <div class="caption">(Designation of Accountable Officer)</div>
                        </td>
                        <td style="width: 6%;"></td>
                        <td style="width: 47%;">
                            <div class="bold" style="font-size: 7.5px;">Approved by:</div>
                            <div class="line center bold" style="margin-top: 10px;">{{ $form['approved_by']['name'] ?? '' }}&nbsp;</div>
                            <div class="caption">&nbsp;</div>
                            <div class="line center" style="margin-top: 10px;">{{ $form['approved_by']['designation'] ?? '' }}&nbsp;</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 39%; padding: 5px 8px 6px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; padding-right: 6px;" class="center">
                            <div style="height: 46px;">{{ \App\Services\FormService::IIRUP_INSPECTED_TEXT }}</div>
                            <div class="line center bold" style="margin-top: 12px;">{{ $form['inspection_officer']['name'] ?? '' }}&nbsp;</div>
                            <div class="caption">(Signature Over Printed Name of Inspection Officer)</div>
                            @if (!empty($form['inspection_officer']['designation']))<div class="caption">{{ $form['inspection_officer']['designation'] }}</div>@endif
                        </td>
                        <td style="width: 50%; padding-left: 6px;" class="center">
                            <div style="height: 46px;">{{ \App\Services\FormService::IIRUP_WITNESS_TEXT }}</div>
                            <div class="line center bold" style="margin-top: 12px;">{{ $form['witness']['name'] ?? '' }}&nbsp;</div>
                            <div class="caption">(Signature over Printed Name of Witness)</div>
                            @if (!empty($form['witness']['designation']))<div class="bold" style="font-size: 7.5px;">{{ $form['witness']['designation'] }}</div>@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (($form['excluded_count'] ?? 0) > 0)
        <div style="font-size: 6.5px; color: #6b7280; margin-top: 3px;">
            {{ $form['excluded_count'] }} unit(s) For Disposal / Disposed are not listed because their property class is not "Semi-expendable property".
        </div>
    @endif
@endsection
