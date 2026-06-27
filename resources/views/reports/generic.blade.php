<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #001e5a;
            margin: 0;
            padding: 0;
        }

        .report-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .report-header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .logo-left {
            width: 18%;
            text-align: left;
        }

        .logo-right {
            width: 18%;
            text-align: right;
        }

        .header-center {
            width: 64%;
            text-align: center;
        }

        .report-logo {
            height: 58px;
            width: auto;
        }

        .org-line {
            font-size: 9px;
            color: #5b7fbf;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .dept-line {
            margin-top: 2px;
            font-size: 11px;
            font-weight: bold;
            color: #0038a8;
            text-transform: uppercase;
        }

        .system-line {
            margin-top: 2px;
            font-size: 10px;
            color: #002a7a;
        }

        .report-title {
            margin-top: 6px;
            font-size: 15px;
            font-weight: bold;
            color: #0038a8;
        }

        .meta {
            margin-top: 4px;
            font-size: 9px;
            color: #5b7fbf;
        }

        .stock-card-header {
            text-align: center;
            margin-bottom: 0;
        }

        .stock-card-regional-office {
            margin-top: 6px;
            font-size: 11px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
        }

        .stock-card-agency {
            margin-top: 4px;
            font-size: 11px;
            font-style: italic;
            color: #000;
        }

        .stock-card-title {
            margin-top: 0;
            margin-bottom: 0;
            font-size: 20px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stock-card-full-line {
            height: 1px;
            background: #000;
            margin: 10px 0 12px;
        }

        table.stock-card-info-table,
        table.stock-card-movements-table {
            width: 100%;
            border-collapse: collapse;
            color: #000;
        }

        table.stock-card-info-table td,
        table.stock-card-movements-table th,
        table.stock-card-movements-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: middle;
            font-size: 10px;
        }

        table.stock-card-info-table td {
            height: 42px;
        }

        table.stock-card-info-table .info-item {
            width: 32%;
        }

        table.stock-card-info-table .info-description {
            width: 36%;
        }

        table.stock-card-info-table .info-stock {
            width: 32%;
            padding: 0;
        }

        table.stock-card-info-table .info-stock-row {
            padding: 5px 8px;
            border-bottom: 1px solid #000;
        }

        table.stock-card-info-table .info-stock-row:last-child {
            border-bottom: none;
        }

        table.stock-card-movements-table th {
            background: #fff;
            font-weight: bold;
            text-align: center;
        }

        table.stock-card-movements-table thead tr:last-child th {
            border-bottom: 3px double #000;
        }

        table.stock-card-movements-table .col-date {
            width: 11%;
            text-align: left;
        }

        table.stock-card-movements-table .col-reference {
            width: 16%;
            text-align: left;
        }

        table.stock-card-movements-table .col-receipt {
            width: 9%;
            text-align: center;
        }

        table.stock-card-movements-table .col-issuance-qty {
            width: 9%;
            text-align: center;
        }

        table.stock-card-movements-table .col-office {
            width: 18%;
            text-align: left;
        }

        table.stock-card-movements-table .col-balance {
            width: 9%;
            text-align: center;
        }

        table.stock-card-movements-table .col-days {
            width: 14%;
            text-align: center;
        }

        table.stock-card-movements-table tbody td.text-center {
            text-align: center;
        }

        table.stock-card-movements-table tbody tr {
            height: 22px;
        }

        .property-card-header {
            text-align: center;
            margin-bottom: 0;
        }

        .property-card-title {
            margin-top: 0;
            margin-bottom: 0;
            font-size: 20px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .property-card-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 0;
        }

        .property-card-meta-table td {
            border: none;
            padding: 2px 0;
            font-size: 10px;
            vertical-align: bottom;
        }

        .property-card-meta-label {
            white-space: nowrap;
            padding-right: 6px;
        }

        .property-card-meta-line {
            border-bottom: 1px solid #000;
            width: 100%;
        }

        table.property-card-info-table,
        table.property-card-movements-table {
            width: 100%;
            border-collapse: collapse;
            color: #000;
        }

        table.property-card-info-table td,
        table.property-card-movements-table th,
        table.property-card-movements-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: middle;
            font-size: 10px;
        }

        table.property-card-info-table td {
            height: 38px;
            vertical-align: top;
        }

        table.property-card-info-table .info-main-left {
            width: 72%;
        }

        table.property-card-info-table .info-property-side {
            width: 28%;
            vertical-align: top;
        }

        table.property-card-info-table .info-property-line {
            display: block;
            margin-top: 4px;
            min-height: 14px;
            border-bottom: 1px solid #000;
        }

        table.property-card-info-table .info-value {
            font-weight: normal;
        }

        table.property-card-movements-table th {
            background: #fff;
            font-weight: bold;
            text-align: center;
        }

        table.property-card-movements-table thead tr:last-child th {
            border-bottom: 3px double #000;
        }

        table.property-card-movements-table .col-date {
            width: 10%;
        }

        table.property-card-movements-table .col-reference {
            width: 14%;
        }

        table.property-card-movements-table .col-receipt {
            width: 8%;
            text-align: center;
        }

        table.property-card-movements-table .col-issue-qty {
            width: 8%;
            text-align: center;
        }

        table.property-card-movements-table .col-office {
            width: 16%;
        }

        table.property-card-movements-table .col-balance {
            width: 8%;
            text-align: center;
        }

        table.property-card-movements-table .col-amount {
            width: 10%;
            text-align: center;
        }

        table.property-card-movements-table .col-remarks {
            width: 16%;
        }

        table.property-card-movements-table tbody td.text-center {
            text-align: center;
        }

        table.property-card-movements-table tbody tr {
            height: 22px;
        }

        .header-rule {
            height: 3px;
            background: #fcd116;
            margin-bottom: 4px;
        }

        .header-rule-blue {
            height: 1px;
            background: #0038a8;
            margin-bottom: 16px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.data-table th,
        table.data-table td {
            border: 1px solid #c8d6ef;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        table.data-table th {
            background: #eef2fa;
            color: #002a7a;
            font-weight: bold;
        }

        .empty {
            margin-top: 24px;
            color: #5b7fbf;
            font-style: italic;
            text-align: center;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #5b7fbf;
            text-align: center;
            border-top: 1px solid #c8d6ef;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    @php
        $logoLeftPath = public_path('images/logo1.png');
        $logoRightPath = public_path('images/logo2.png');
        $logoLeft = file_exists($logoLeftPath) ? base64_encode(file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? base64_encode(file_get_contents($logoRightPath)) : null;
    @endphp

    @if($type === 'stock-card')
        <div class="stock-card-header">
            <div class="stock-card-title">Stock Card</div>
            <div class="stock-card-regional-office">Department of Education Regional Office No. V</div>
            <div class="stock-card-agency">Agency</div>
        </div>
        <div class="stock-card-full-line"></div>
    @elseif($type === 'property-card')
        <div class="property-card-header">
            <div class="property-card-title">Property Card</div>
        </div>
        <table class="property-card-meta-table">
            <tr>
                <td style="width: 14%;" class="property-card-meta-label"><strong>Entity Name :</strong></td>
                <td style="width: 36%;"><div class="property-card-meta-line">&nbsp;</div></td>
                <td style="width: 14%;" class="property-card-meta-label"><strong>Fund Cluster:</strong></td>
                <td style="width: 36%;"><div class="property-card-meta-line">&nbsp;</div></td>
            </tr>
        </table>
    @else
        <table class="report-header-table">
            <tr>
                <td class="logo-left">
                    @if($logoLeft)
                        <img src="data:image/png;base64,{{ $logoLeft }}" alt="DepEd Logo" class="report-logo">
                    @endif
                </td>
                <td class="header-center">
                    <div class="org-line">Republic of the Philippines</div>
                    <div class="dept-line">Department of Education</div>
                    <div class="system-line">OBIMS · Supply Unit Inventory Management System</div>
                    <div class="report-title">{{ $title }}</div>
                    <div class="meta">Generated: {{ $generatedAt }}</div>
                </td>
                <td class="logo-right">
                    @if($logoRight)
                        <img src="data:image/png;base64,{{ $logoRight }}" alt="Division Logo" class="report-logo">
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if(!in_array($type, ['stock-card', 'property-card']))
        <div class="header-rule"></div>
        <div class="header-rule-blue"></div>
    @endif

    @if($rows->isEmpty() && !in_array($type, ['stock-card', 'property-card']))
        <p class="empty">No data yet for this report.</p>
    @elseif($type === 'stock-card')
        @if(!empty($item))
            <table class="stock-card-info-table">
                <tbody>
                    <tr>
                        <td class="info-item"><strong>Item:</strong> {{ $item->item_name }}</td>
                        <td class="info-description"><strong>Description:</strong> {{ $item->description ?: '—' }}</td>
                        <td class="info-stock">
                            <div class="info-stock-row"><strong>Stock No.:</strong> {{ $item->barcode }}</div>
                            <div class="info-stock-row"><strong>Re-order Point:</strong> {{ $item->minimum_stock }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        <table class="stock-card-movements-table" @if(!empty($item)) style="margin-top: -1px;" @endif>
            <thead>
                <tr>
                    <th rowspan="2" class="col-date">Date</th>
                    <th rowspan="2" class="col-reference">Reference</th>
                    <th colspan="1">Receipt</th>
                    <th colspan="2">Issuance</th>
                    <th colspan="1">Balance</th>
                    <th rowspan="2" class="col-days">No. of Days<br>to Consume</th>
                </tr>
                <tr>
                    <th class="col-receipt">Qty.</th>
                    <th class="col-issuance-qty">Qty.</th>
                    <th class="col-office">Office</th>
                    <th class="col-balance">Qty.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $movementType = $row->transaction_type->value ?? $row->transaction_type;
                        $receiptQty = '';
                        $issuanceQty = '';
                        $office = '';
                        $daysToConsume = '';

                        if (in_array($movementType, ['IN', 'RETURN'], true)) {
                            $receiptQty = $row->quantity;
                        } elseif ($movementType === 'OUT') {
                            $issuanceQty = $row->quantity;
                            $office = ($row->department_office ?? '—') !== '—' ? $row->department_office : '';
                        } elseif ($movementType === 'ADJUSTMENT') {
                            if ($row->new_stock > $row->previous_stock) {
                                $receiptQty = $row->quantity;
                            } elseif ($row->new_stock < $row->previous_stock) {
                                $issuanceQty = $row->quantity;
                            }
                        }

                        $movementDate = \Illuminate\Support\Carbon::parse($row->created_at)->format('n/j/y');
                    @endphp
                    <tr>
                        <td class="col-date">{{ $movementDate }}</td>
                        <td class="col-reference">{{ $row->reference_number ?? '' }}</td>
                        <td class="col-receipt text-center">{{ $receiptQty }}</td>
                        <td class="col-issuance-qty text-center">{{ $issuanceQty }}</td>
                        <td class="col-office">{{ $office }}</td>
                        <td class="col-balance text-center">{{ $row->new_stock }}</td>
                        <td class="col-days text-center">{{ $daysToConsume }}</td>
                    </tr>
                @endforeach
                @for($blank = max(0, 12 - $rows->count()); $blank > 0; $blank--)
                    <tr>
                        <td class="col-date">&nbsp;</td>
                        <td class="col-reference">&nbsp;</td>
                        <td class="col-receipt">&nbsp;</td>
                        <td class="col-issuance-qty">&nbsp;</td>
                        <td class="col-office">&nbsp;</td>
                        <td class="col-balance">&nbsp;</td>
                        <td class="col-days">&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>
    @elseif($type === 'property-card')
        @if(!empty($equipment))
            <table class="property-card-info-table" style="margin-top: 8px;">
                <tbody>
                    <tr>
                        <td class="info-main-left">
                            <strong>Property, Plant and Equipment :</strong>
                            <span class="info-value">{{ $equipment->name }}</span>
                        </td>
                        <td rowspan="2" class="info-property-side">
                            <strong>Property Number:</strong>
                            <span class="info-property-line">{{ $equipment->property_number ?: '' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="info-main-left">
                            <strong>Description :</strong>
                            <span class="info-value">{{ $equipment->description ?: '' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
        <table class="property-card-movements-table" @if(!empty($equipment)) style="margin-top: -1px;" @endif>
            <thead>
                <tr>
                    <th rowspan="2" class="col-date">Date</th>
                    <th rowspan="2" class="col-reference">Reference/<br>PAR No.</th>
                    <th colspan="1">Receipt</th>
                    <th colspan="2">Issue/Transfer/<br>Disposal</th>
                    <th colspan="1">Balance</th>
                    <th rowspan="2" class="col-amount">Amount</th>
                    <th rowspan="2" class="col-remarks">Remarks</th>
                </tr>
                <tr>
                    <th class="col-receipt">Qty.</th>
                    <th class="col-issue-qty">Qty.</th>
                    <th class="col-office">Office/Officer</th>
                    <th class="col-balance">Qty.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $movementDate = \Illuminate\Support\Carbon::parse(data_get($row, 'movement_date'))->format('n/j/y');
                    @endphp
                    <tr>
                        <td class="col-date">{{ $movementDate }}</td>
                        <td class="col-reference">{{ data_get($row, 'reference_number', '') }}</td>
                        <td class="col-receipt text-center">{{ data_get($row, 'receipt_qty', '') }}</td>
                        <td class="col-issue-qty text-center">{{ data_get($row, 'issue_qty', '') }}</td>
                        <td class="col-office">{{ data_get($row, 'office_officer', '') }}</td>
                        <td class="col-balance text-center">{{ data_get($row, 'balance_qty', '') }}</td>
                        <td class="col-amount text-center">{{ data_get($row, 'amount', '') }}</td>
                        <td class="col-remarks">{{ data_get($row, 'remarks', '') }}</td>
                    </tr>
                @endforeach
                @for($blank = max(0, 12 - $rows->count()); $blank > 0; $blank--)
                    <tr>
                        <td class="col-date">&nbsp;</td>
                        <td class="col-reference">&nbsp;</td>
                        <td class="col-receipt">&nbsp;</td>
                        <td class="col-issue-qty">&nbsp;</td>
                        <td class="col-office">&nbsp;</td>
                        <td class="col-balance">&nbsp;</td>
                        <td class="col-amount">&nbsp;</td>
                        <td class="col-remarks">&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>
    @elseif($type === 'inventory')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Minimum Stock</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->barcode }}</td>
                        <td>{{ $row->item_name }}</td>
                        <td>{{ $row->category->name ?? '' }}</td>
                        <td>{{ $row->current_stock }}</td>
                        <td>{{ $row->minimum_stock }}</td>
                        <td>{{ $row->location->name ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'equipment-inventory')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Property No.</th>
                    <th>Equipment</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Available Qty</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->barcode ?? '' }}</td>
                        <td>{{ $row->property_number ?? '' }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->category->name ?? '' }}</td>
                        <td>{{ $row->type }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->description ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'stock-movements')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Barcode</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->created_at }}</td>
                        <td>{{ $row->item->barcode ?? '' }}</td>
                        <td>{{ $row->item->item_name ?? '' }}</td>
                        <td>{{ $row->transaction_type->value ?? $row->transaction_type }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->new_stock }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'low-stock')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Item</th>
                    <th>Current Stock</th>
                    <th>Minimum Stock</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->barcode }}</td>
                        <td>{{ $row->item_name }}</td>
                        <td>{{ $row->current_stock }}</td>
                        <td>{{ $row->minimum_stock }}</td>
                        <td>{{ $row->location->name ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'issuance')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Issuance No.</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Item / Equipment</th>
                    <th>Barcode / Property No.</th>
                    <th>Qty</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @forelse($row->details as $detail)
                        <tr>
                            <td>{{ $row->issuance_number }}</td>
                            <td>{{ $row->request->department->name ?? '' }}</td>
                            <td>{{ $detail->equipment_id || $detail->equipment ? 'Equipment' : 'Item' }}</td>
                            <td>{{ $detail->equipment->name ?? $detail->item->item_name ?? '' }}</td>
                            <td>{{ $detail->equipment->property_number ?? $detail->barcode ?? $detail->item->barcode ?? '' }}</td>
                            <td>{{ $detail->quantity }}</td>
                            <td>{{ $row->issued_date }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td>{{ $row->issuance_number }}</td>
                            <td>{{ $row->request->department->name ?? '' }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>{{ $row->issued_date }}</td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'returns')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Returned By</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->date_returned }}</td>
                        <td>{{ $row->item->item_name ?? '' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->returner->name ?? '' }}</td>
                        <td>{{ $row->reason ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'physical-inventory')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Item</th>
                    <th>System Qty</th>
                    <th>Physical Qty</th>
                    <th>Variance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->session_id }}</td>
                        <td>{{ $row->item->item_name ?? '' }}</td>
                        <td>{{ $row->expected_quantity }}</td>
                        <td>{{ $row->physical_quantity }}</td>
                        <td>{{ $row->variance }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'monthly-consumption')
        <table class="data-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Total Issued</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->department }}</td>
                        <td>{{ $row->total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table class="data-table">
            <thead><tr><th>Report Data</th></tr></thead>
            <tbody>
                @foreach($rows as $row)
                    <tr><td>{{ json_encode($row) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">OBIMS · Department of Education · Supply Unit Inventory</div>
</body>
</html>
