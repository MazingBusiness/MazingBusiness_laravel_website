<head>
  <title>Mazing CCAvenue Payment</title>
</head>

<body>
  @php
    $merchant_data = '';
    $working_key = env('CC_WORKINGKEY');
    $access_code = env('CC_ACCESSKEY');
    $address = json_decode($combined_order->shipping_address);
    $merchant_data .= 'tid=' . time() . '000&merchant_id=' . env('CC_MERCHANTID') . '&order_id=' . $combined_order->id . '&currency=INR&amount=' . $combined_order->grand_total . '&redirect_url=' . env('CC_CALLBACKURL') . '&cancel_url=' . env('CC_CANCELURL') . '&language=en&billing_name=' . $address->name . '&billing_tel=' . $address->phone . '&billing_email=' . $address->email . '&billing_address=' . $address->address . '&billing_city=' . $address->city . '&billing_state=' . $address->state . '&billing_zip=' . $address->postal_code . '&billing_country=India&merchant_param1=' . $combined_order->user->party_code;
    $encrypted_data = \App\Utility\CCavenueUtility::ccEncrypt($merchant_data, $working_key);
  @endphp
  <form method="post" name="redirect"
    action="https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction">
    {{-- https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction --}}
    <?php
    echo "<input type=hidden name=encRequest value=$encrypted_data>";
    echo "<input type=hidden name=access_code value=$access_code>";
    ?>
  </form>
  <script language='javascript'>
    document.redirect.submit();
  </script>
</body>

</html>
