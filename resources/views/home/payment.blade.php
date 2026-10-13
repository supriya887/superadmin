
@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection
<!-- select plan section -->
@section('Body')
<section class="select-plan-section position-relative">
  <div class="container">
    <h2 class="your-cart-heading">Your Cart</h2>
    <div class="cart-progress">
      <div class="step-wrapper">
        <div class="step">Select Plan</div>
        <div class="step">Checkout</div>
        <div class="step active">Payment</div>
      </div>
      <div class="back-btn">← Back</div>
    </div>
    <div class="row mt-40 justify-content-center">
      <div class="col-lg-6 col-md-8 col-12">
        <div class="cart-plan-box mb-30">
          <h3 class="plan-box-title div-text mb-0">Payment Details</h3>
          <div class="domain-section">
            <div class="payment-box">
              <div class="payment-row">
                <div class="left-option">
                  <input type="radio" name="acctOption" value="have" checked>
                  <label>Credit Card / UPI</label>
                </div>
                <div class="payment-icons">
                  <button type="submit"><img src="{{ asset('public/home/images/icons/visa.png') }}" alt="visa paymeny"></button>
                  <button type="submit"><img src="{{ asset('public/home/images/icons/master-card.png') }}" alt="master-card paymeny"></button>
                  <button type="submit"><img src="{{ asset('public/home/images/icons/RuPay.png') }}" alt="RuPay paymeny"></button>
                  <button type="submit"><img src="{{ asset('public/home/images/icons/upi.png') }}" alt="upi paymeny"></button>
                  <button type="submit"><img src="{{ asset('public/home/images/icons/phone-pay.png') }}" alt="phone-pay paymeny"></button>
                  <button type="submit"><img src="{{ asset('public/home/images/icons/G-pay.png') }}" alt="G-pay paymeny"></button>
                </div>
              </div>
              <div class="payment-row">
                <div class="left-option">
                  <input type="radio" name="acctOption" value="register">
                  <label>Net Banking</label>
                </div>
                <div class="bank-icon">
                  <button type="submit"><img src="{{ asset('public/home/images/icons/bank.png') }}" alt="bank"></button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection