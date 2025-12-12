<h5 class="fw-600 mb-3 fs-17 pb-2">Custom Amount</h5>
<table class="w-100 mt-n2">
    <tbody>
    <tr><td colspan="2" class="text-center"><strong>Payment Summary :</strong></td></tr>
    <tr><td colspan="2" class="text-center"><strong>Payable Amount:</strong> ₹ {{$amount}}</td></tr>
    </tbody>
</table>
<p>Pay your amount by scanning the QR CODE</p>
<img src="{{$qrCodeUrl}}" alt="UPI QR Code" class="img-fluid mt-n2">
<p class="text-muted" style="font-size: 14px;">(* This QR Code is valid for next 24 hrs.)</p>
<p>OR</p>
<p>Pay your amount by entering your @upi ID</p>
<div class="d-flex justify-content-center align-items-center">
    <form method="POST" action="">
    <div class="form-group mb-0">
        @csrf
        <input type="text" class="form-control mb-2" placeholder="@upiId" name="upi_id_custom" id="upi_id_custom" required="" style="width:239px;">
        <input type="hidden" class="form-control" name="bill_number_custom" id="bill_number_custom" required="" value="{{ encrypt($billNumber) }}">
        <input type="hidden" class="form-control" name="amount_custom" id="amount_custom" required="" value="{{ encrypt($amount) }}">
        <input type="hidden" class="form-control" name="payment_for_custom" id="payment_for_custom" required="" value="{{$payment_for}}">
    </div>
    <!-- <button type="button" class="btn btn-primary mt-2">Verify and Pay</button> -->
    <button type="button" class="btn btn-primary mt-2" id="btnVerifyAndPayForCustomAmount" style="margin-bottom:10px;" onclick="payCustomAmount()">Verify and Pay</button> <!-- Added mt-2 for margin-top -->
    </form>
</div>