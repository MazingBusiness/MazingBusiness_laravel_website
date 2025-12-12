@extends('frontend.layouts.app')

@section('content')
<section class="text-center py-6">
	<div class="container">
		<div class="row">
			<div class="col-lg-6 mx-auto">
				@if($paymentStatus == 'succeeded')
					<img src="{{ static_asset('assets/img/payment-success.png') }}" class="img-fluid" style="width:30%">
					<h1 class="fw-700" style="color:#037c03">₹{{ number_format($paidAmount,2) }}</h1>
					<h2 class="fw-700" style="color:#3b9a4a">PAYMENT WAS SUCCESSFUL!</h2>
					<h3 class="fw-700">The payment has been done successfylly. <br> Thanks for being with us.</h2>
					<p class="fs-16 opacity-60">Your Payment Id : {{ $paumentId }}</p>
				@else
					<img src="{{ static_asset('assets/img/payment_failed.png') }}" class="img-fluid" style="width:30%">
					<h1 class="fw-700" style="color:#992121">₹{{ number_format($paidAmount,2) }}</h1>
					<h2 class="fw-700" style="color:#ef0c0c">PAYMENT WAS FAILED!</h2>
					<h3 class="fw-700">The payment has been failed.</h2>
					<p class="fs-16 opacity-60">Your Payment Id : {{ $paumentId }}</p>
				@endif
			</div>
		</div>
	</div>
</section>
@endsection
