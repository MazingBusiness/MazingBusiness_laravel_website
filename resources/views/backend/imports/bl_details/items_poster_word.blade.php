<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Items Poster Document</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111;
        }

        .page {
            width: 100%;
            padding: 40px 25px 30px 25px;
            text-align: center;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Simple centering box, no flex (Word-friendly) */
        .item-image-box {
            width: 0;               /* we'll center via text-align on parent */
            margin: 0 auto;
        }

        .item-name {
            margin-top: 8px;
            font-size: 13px;
            font-weight: bold;
        }
    </style>
</head>
<body>

@foreach($items as $item)
    <div class="page">

        <div class="item-image-box">
            {{-- explicit width/height makes it a fixed square in Word --}}
            <img src="{{ $item['image_url'] }}"
                 alt="{{ $item['name'] }}"
                 style="width:500px;height:500px;object-fit:contain;display:block;margin:0 auto;"
                 width="500"
                 height="500">
        </div>

        <div class="item-name">
            {{ $item['name'] }}
        </div>

    </div>
@endforeach

</body>
</html>