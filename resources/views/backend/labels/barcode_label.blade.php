@extends('backend.layouts.app')

@section('content')
<style>
    .label-box {
        width: 310px;
        border: 1px solid black;
        padding: 8px 10px;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
        font-size: 10px;
        background: white;
    }
    .text-center {
        text-align: center;
    }
    .bold {
        font-weight: bold;
    }
    .top-title {
        font-size: 13px;
    }
    .sub-title {
        font-size: 9px;
    }
    .info {
        margin-top: 5px;
        line-height: 1.4;
    }
    .barcode {
        text-align: center;
        margin: 10px 0;
    }
    .barcode img {
        height: 65px;
    }
    .barcode-number {
        font-size: 14px;
        letter-spacing: 2px;
        margin: 4px 0;
    }
    .section {
        margin-top: 4px;
        line-height: 1.4;
    }
    hr {
        border: none;
        border-top: 1px solid black;
        margin: 5px 0;
    }
    .float-right {
        float: right;
    }
      hr {
        border: none;
        border-top: 2.2px solid #111;  /* slightly darker than default */
        margin:5px 0;
    }
</style>

<div class="label-box">
    <div class="text-center bold top-title">MRP : ₹{{ $mrp }}</div>
    <div class="text-center sub-title">(Incl. of all taxes)</div>
    <hr>
   <div class="info">
        <strong>Part No.:</strong> {{ $part_no }} </span><br>
        <strong>Name</strong> <br>
        {{ $product_name }}<br>
       
   </div>

    <hr>

    <div class="barcode">
        <div class="barcode-number"><strong>NET QTY:</strong> {{ $qty }} N</div>
        <img src="https://barcode.tec-it.com/barcode.ashx?data={{ $barcode }}&code=EAN13&translate-esc=false&width=300&height=90" alt="Barcode" />
        <div class="barcode-number">{{ $barcode }}</div>
    </div>

    <hr>

    <div class="section text-center">
        <div class="bold">IMPORTED & MARKETED BY:</div>
        {{ $marketed_by }}<br>
        {{ $market_address_line1 }}<br>
        {{ $market_address_line2 }}<br>
        {{ $market_address_line3 }}
    </div>

    <hr>

    
</div>
@endsection


