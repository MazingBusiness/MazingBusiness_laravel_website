@extends('backend.layouts.app')

@section('content')

<style>
    .table th, .table td { vertical-align: middle; font-size: 14px; }

    /* === Minimal custom modal === */
    .mp-modal-overlay{
        position: fixed; inset: 0; background: rgba(0,0,0,.45);
        display:none; z-index: 1050;
        align-items: center; justify-content: center; padding: 16px;
    }
    .mp-modal-card{
        width: 100%; max-width: 560px; background: #fff; border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,.2); overflow: hidden; animation: mp-pop .16s ease-out;
    }
    @keyframes mp-pop { from { transform: scale(.98); opacity: .6; } to { transform: scale(1); opacity: 1; } }
    .mp-modal-header{
        display:flex; align-items:center; justify-content:space-between;
        padding: 12px 16px; border-bottom: 1px solid #eef1f4; background:#f8fafc;
    }
    .mp-modal-header h5{ margin:0; font-weight:700; color:#c0392b; }
    .mp-close-btn{
        background: transparent; border: 0; font-size: 22px; line-height: 1; cursor: pointer; color:#666;
    }
    .mp-modal-body{ padding: 16px; color:#333; }
    .mp-list{
        padding:10px 12px; background:#fff7e6; border:1px dashed #f0ad4e; border-radius:8px; margin:8px 0 0;
        font-family: monospace; white-space: pre-wrap; word-break: break-word;
    }
    .mp-note{ margin: 12px 0 0; color:#555; }
    .mp-modal-actions{
        display:flex; gap:10px; justify-content:flex-end; padding: 12px 16px; border-top: 1px solid #eef1f4; background:#f8fafc;
    }
</style>

<div class="card">
    <div class="card-body">
         {{-- Error Message Display --}}
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        {{-- Success Message Display --}}
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('invoice.saveFromChallans') }}" id="invoiceForm">
            @csrf
            <input type="hidden" name="force_create" id="forceCreate" value="0">
            <input type="hidden" name="challan_ids" value="{{ implode(',', $challanIds) }}">
            @php
                $early_payment_check = 1;
            @endphp

            <h5 class="mb-3">Combined Product Listing from Selected Challans</h5>
            <table class="table table-bordered text-center" style="border-collapse: collapse;">
                <thead style="background-color: #007baf; color: #fff;">
                    <tr>
                        <th>Challan No</th>
                        <th>Part No</th>
                        <th>Item Name</th>
                        <th>HSN No</th>
                        <th>GST</th>
                        <th>Billed Qty</th>
                        <th>Rate</th>
                        <th>Billed Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($challans as $challan)
                        @foreach ($challan->challan_details as $detail)
                            @php
                                $product   = optional($detail->product_data);
                                $pnRaw     = (string) ($product->part_no ?? '');
                                $pnKey     = strtoupper(trim($pnRaw)); // normalize
                                $rate      = (float) ($detail->rate ?? 0);
                                $fallback  = (float) ($product->purchase_price ?? $product->unit_price ?? 0);
                                $buyPrice  = $allProductRates[$pnKey] ?? $fallback; // ← from products table
                            @endphp
                        
                            @if($product)
                                <tr class="product-row"
                                    data-partno="{{ $pnRaw }}"
                                    data-rate="{{ $rate }}"
                                    data-purchase-price="{{ $buyPrice }}">
                                    <td>{{ $challan->challan_no }}</td>
                                    <td>{{ $product->part_no ?? 'N/A' }}</td>
                                    <td>{{ $product->name ?? 'N/A' }}</td>
                                    <td>{{ $product->hsncode ?? 'N/A' }}</td>
                                    <td>{{ $product->tax ?? 0 }}%</td>
                                    <td>{{ $detail->quantity }}</td>
                                    <td>{{ single_price($detail->rate) }}</td>
                                    <td>{{ single_price($detail->final_amount) }}</td>
                                </tr>
                            @endif
                        @endforeach
                        @if($challan->early_payment_check == 0)
                            @php
                                $early_payment_check = 0;
                            @endphp
                        @endif
                    @endforeach
                </tbody>
            </table>
            <input type="hidden" name="early_payment_check" value="{{ $early_payment_check }}">
            <div class="text-right mt-4">
                <button type="submit" class="btn btn-success" id="submitBtn">Save to Invoice</button>
            </div>
        </form>


        {{-- ↓↓↓ Add this right after </form> but still inside .card-body --}}
        <div id="underpricedModal" class="mp-modal-overlay" style="display:none;">
          <div class="mp-modal-card" role="dialog" aria-modal="true" aria-labelledby="mpModalTitle">
            <div class="mp-modal-header">
              <h5 id="mpModalTitle">Warning</h5>
              <button type="button" class="mp-close-btn" aria-label="Close">&times;</button>
            </div>

            <div class="mp-modal-body">
              <p><strong>Some products are being billed below purchase price:</strong></p>
              <div id="underPriceList" class="mp-list"></div>
              <p class="mp-note">Do you want to continue?</p>
            </div>

            <div class="mp-modal-actions">
              <button type="button" class="btn btn-light" id="modalCancelBtn">Cancel</button>
              <button type="button" class="btn btn-danger" id="modalContinueBtn">Continue Anyway</button>
            </div>
          </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .table th, .table td {
        vertical-align: middle;
        font-size: 14px;
    }
</style>
@endpush

@section('script')
<script>
$(function () {
    const $form = $('#invoiceForm');
    const $submit = $('#submitBtn');
    const $force = $('#forceCreate');

    const $modal = $('#underpricedModal');
    const $list  = $('#underPriceList');

    function openModal(partNos){
        $list.text(partNos.join(', '));
        $modal.fadeIn(120).css('display','flex'); // center with flex
        $('body').css('overflow','hidden');
    }
    function closeModal(){
        $modal.fadeOut(120);
        $('body').css('overflow','');
    }

    // Close handlers
    $modal.on('click', function(e){
        if (e.target === this) closeModal(); // click on overlay
    });
    $modal.find('.mp-close-btn, #modalCancelBtn').on('click', function(){
        closeModal();
    });

    // Continue submit
    $('#modalContinueBtn').on('click', function(){
        $force.val('1');
        $submit.prop('disabled', true).text('Processing...');
        closeModal();
        $form.trigger('submit');
    });

    // Intercept main submit button
    $submit.on('click', function (e) {
        e.preventDefault();

        let underPriced = [];
        $('.product-row').each(function () {
            let rate = parseFloat(String($(this).attr('data-rate')) || '0');
            let purchasePrice = parseFloat(String($(this).attr('data-purchase-price')) || '0');
            if (rate < purchasePrice) {
                underPriced.push($(this).attr('data-partno'));
            }
        });

        if (underPriced.length > 0) {
            openModal(underPriced);
        } else {
            $submit.prop('disabled', true).text('Processing...');
            $form.trigger('submit');
        }
    });
});
</script>
@endsection




