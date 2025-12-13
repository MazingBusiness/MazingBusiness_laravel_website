<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Items Poster PDF</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #111;
        }

        .page {
            width: 100%;
            padding: 40px 25px 30px 25px;
            text-align: center;

            /* This is how DomPDF respects page breaks */
            page-break-after: always;
        }

        /* Prevent extra blank page at the end */
        .page:last-child {
            page-break-after: auto;
        }

        .item-image-box {
            width: 350px;
            height: 350px;
            margin: 0 auto;
            border: 1px solid #dddddd;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .item-image-box img {
            max-width: 100%;
            max-height: 100%;
        }
        .item-name {
            margin-top: 12px;
            font-size: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>

{{-- Optional debug: show how many items we actually have --}}
{{-- <div>Count: {{ count($items) }}</div> --}}

@foreach($items as $item)
    <div class="page">
        <div class="item-image-box">
            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
        </div>

        <div class="item-name">
            {{ $item['name'] }}
        </div>
    </div>
@endforeach

</body>
</html>