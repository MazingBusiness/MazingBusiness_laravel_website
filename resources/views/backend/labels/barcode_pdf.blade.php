<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@for ($i = 0; $i < $copies; $i++)
    @include('backend.labels.barcode_label', $data)
    @if ($i < $copies - 1)
        <div class="page-break"></div>
    @endif
@endfor
</body>
</html>
