@extends('home.render')

@section('metaTitle')
  <title>Contact Us - Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection

@section('Body')
<!-- select plan section -->
<section class="select-plan-section position-relative">
  <div class="container">
    <h2 class="your-cart-heading">Your Cart</h2>
    <div class="row">
      <!-- Left Column - Plan Selection -->
      <div class="col-lg-6">
        <div class="cart-plan-box">
          <h3 class="plan-box-title">Business Starter</h3>
          <div class="plan-controls d-flex">
            <div class="d-flex gap-4 control-box">
              <div class="control-group">
                <label class="control-label">Plan Duration</label>
                <select class="plan-duration-select">
                  <option>12 Month</option>
                  <option>6 Month</option>
                  <option>3 Month</option>
                  <option>1 Month</option>
                </select>
              </div>
              
              <div class="control-group">
                <label class="control-label">Mailboxes</label>
                <div class="qty-wrapper ">
                <button class="qty-btn minus">−</button>
                <input type="number" class="qty-input" value="1" min="1">
                <button class="qty-btn plus">+</button>
              </div>
              </div>
            </div>
            <div class="plan-price-display">
            <span class="price-currency">₹</span>
            <span class="price-amount">792.00</span>
            <span class="price-period">/mo</span>
          </div>
          </div>

          
        </div>
      </div>

      <!-- Right Column - Order Summary -->
      <div class="col-lg-6">
        <div class="order-summary-box">
          <h3 class="summary-title">Order Summary</h3>
          <div class="plan-controls">
            <div class="summary-item-header">
            <span class="item-name">Business Starter - 1 Mailbox</span>
            <span class="item-price">1 Mailbox</span>
          </div>
          
          <div class="summary-item">
            <span class="item-description">1 Month Plan</span>
            <span class="item-price-value">₹ 499.00</span>
          </div>


          <div class="subtotal-section">
            <span class="subtotal-label">Subtotal</span>
            <span class="subtotal-amount">₹499.00</span>
          </div>

          <p class="gst-info">GST calculated at checkout</p>
          <div class="input-group coupon-code">
            <input type="text" class="form-control" placeholder="Have a coupon code?" aria-label="Recipient's username" aria-describedby="button-addon2">
            <button class="apply-btn" type="button">Apply</button>
          </div>
          <button class="checkout-btn" onclick="continueCheckout()">
            <span>Continue to Secure Checkout</span>
          </button>

          <div class="guarantee-badge">
            <svg width="20" height="20" viewbox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 1L3 6V12C3 18.6 12 24 12 24S21 18.6 21 12V6L12 1Z" fill="#32a953" stroke="#32a953" stroke-width="2" stroke-linejoin="round"/>
              <path d="M10 14L8 12M10 14L16 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>30 days money back guarantee</span>
          </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- select plan section -->
@endsection