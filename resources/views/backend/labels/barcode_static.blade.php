<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Barcode Label</title>
    <style>
        body {
            margin: 0;
            padding: 10px;
            font-family: Arial, sans-serif;
            font-size: 10px;
            background: white;
        }
        .label-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .label-box {
            width: 230px;
            height: 355px;
            border: 1px solid black;
            padding: 6px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .title {
            font-size: 10px;
            font-weight: bold;
        }
        .sub-title {
            font-size: 8px;
            font-weight: normal;
        }
        .center {
            text-align: center;
        }
        .bold {
            font-weight: bold;
        }
        .section {
            font-size: 9px;
            line-height: 1.3;
        }
        hr {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="label-row">
        @for ($i = 0; $i < 2; $i++)
        <div class="label-box">
            <table style="width: 100%;">
                <tr>
                    <td class="title">MRP: ₹2385 /pc<br><span class="sub-title">(Incl. of all taxes)</span></td>
                    <td class="title" style="text-align: right;">QTY: 1 N</td>
                </tr>
            </table>
            <hr>
            <div class="section">{{ 'XLNT ELECTRIC BLOWER - P-EB20 (MZ34337)' }}</div>
            <hr>
            <div class="center">
                <img src="https://barcode.tec-it.com/barcode.ashx?data=MZ3433700001&code=Code128&translate-esc=false&width=250&height=50" alt="Barcode" style="width: 180px; height: 45px;">
                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 4px;">MZ3433700001</div>
                <div style="margin-top: 6px;">
                    <img src="https://mazingbusiness.com/public/images/qr.png" width="40" height="40"><br>
                    <div class="section center">Download Our App</div>
                    <div class="bold center" style="font-size: 9px;">Visit Our Website</div>
                    <div class="section center">www.mazingbusiness.com</div>
                </div>
            </div>
            <hr>
            <div class="center section">
                <div class="bold">MARKETED BY:</div>
                ACE TOOLS PRIVATE LIMITED<br>
                Pal Colony, Village Rithala<br>
                NEW DELHI - 110085<br>
                Ph: 9730377752
            </div>
            <hr>
            <div class="center section">
                <div class="bold">IMPORTED BY:</div>
                M/S XYZ IMPORTERS<br>
                102, Nariman Point, Mumbai<br>
                022-23456789 | import@xyz.com
            </div>
        </div>
        @endfor
    </div>
</body>
</html>
