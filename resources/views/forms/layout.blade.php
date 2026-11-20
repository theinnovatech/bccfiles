<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $form['title'] ?? 'Form' }}</title>
    <style>
        @page { margin: @yield('margin', '26px 30px 34px 30px'); }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; margin: 0; }
        table { border-collapse: collapse; }
        .logo { width: @yield('logo', '58px'); height: auto; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .ruled td, .ruled th { border: 0.8px solid #000; }
        .grid { width: 100%; }
        .grid th { font-weight: bold; text-align: center; vertical-align: middle; padding: 2px 3px; }
        .grid td { padding: 2px 4px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .line { border-bottom: 0.8px solid #000; }
        .caption { font-size: 6.5px; text-align: center; }
        .meta {
            position: fixed; bottom: -22px; left: 0; right: 0;
            font-size: 6.5px; color: #6b7280; font-style: italic;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        @yield('styles')
    </style>
</head>
<body>
    <div class="meta">{{ $metaLine }}</div>
    @yield('content')
</body>
</html>
