<h5 class="fw-600 mb-3 fs-17 pb-2">Custom Amount</h5>
<table class="w-100">
<tbody>
    <tr><td class="text-center"><strong>Due Amount:</strong> ₹ {{$dueAmount}}</td></tr>
    <tr><td class="text-center"><strong>Overdue Amount:</strong> ₹ {{$overdueAmount}}</td></tr>
</tbody>
</table>
<p>Enter your amount and pay</p>
<div class="d-flex justify-content-center align-items-center">
<form method="POST" action="">
    @csrf
    <input type="text" class="form-control mb-2" placeholder="Enter your amount" name="amount_custom" id="amount_custom" required="" style="width:239px;">
    <input type="hidden" class="form-control mb-2" name="billNumberCustom" id="billNumberCustom" value="{{ encrypt($billnumber) }}">
    <input type="hidden" class="form-control mb-2" name="payment_for_custom" id="payment_for_custom" value="{{ $payment_for }}">
    <button type="button" class="btn btn-primary mt-2" id="btnCustomPay" onclick="generateCustoPay()">Pay</button>
</form>
</div>