@extends('home.render')

@section('metaTitle')
    <title>Tizzy Cloud</title>
    <meta name="description" content="" />
    <meta name="keywords" content="" />
@endsection
@section('myCSS')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
@endsection
<!-- select plan section -->
@section('Body')
    <!-- select plan section -->
    <section class="select-plan-section position-relative">
        <div class="container">
            <h2 class="your-cart-heading">Your Cart</h2>
            <div class="cart-progress">
                <div class="step-wrapper">
                    <div class="step active">Select Plan</div>
                    <div class="step">Checkout</div>
                    <div class="step">Payment</div>
                </div>
                <div class="back-btn">← Back</div>
            </div>
            <div class="row mt-40">
                <div class="col-lg-6">
                    <div class="cart-plan-box">
                        <h3 class="plan-box-title div-text mb-0">{{ $plan->name }}</h3>
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
                                {{-- <span class="price-period">/mo</span> --}}
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
                                <span class="item-name">{{ $plan->name }}</span>
                                <span class="item-price">{{ $plan->storage }}</span>
                            </div>

                            <div class="summary-item">
                                <span class="item-description">1 Month Plan</span>
                                <span class="item-price-value">₹ {{ $plan->price }}</span>
                            </div>


                            <div class="subtotal-section">
                                <span class="subtotal-label">Subtotal</span>
                                <span class="subtotal-amount">₹499.00</span>
                            </div>

                            <p class="gst-info">GST calculated at checkout</p>
                            {{-- <div class="input-group coupon-code">
                                <input type="text" class="form-control" placeholder="Have a coupon code?"
                                    aria-label="Recipient's username" aria-describedby="button-addon2">
                                <button class="apply-btn" type="button">Apply</button>
                            </div> --}}
                            <button class="checkout-btn" onclick="continueCheckout()">
                                <span>Continue to Secure Checkout</span>
                            </button>

                            <div class="guarantee-badge">
                                <svg width="20" height="20" viewbox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 1L3 6V12C3 18.6 12 24 12 24S21 18.6 21 12V6L12 1Z" fill="#32a953"
                                        stroke="#32a953" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M10 14L8 12M10 14L16 8" stroke="white" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" />
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
{{-- myjs section --}}
@section('jsLinks')
    {{-- alertify js --}}
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
@section('myJS')
    <script>
        $(document).ready(function() {
            var planId = {{ $plan->id }};
            var $qtyInput = $('.qty-input');
            var $durationSelect = $('.plan-duration-select');

            function updateCalculation() {
                var quantity = parseInt($qtyInput.val());
                var durationText = $durationSelect.val();
                var duration = 1;

                // Map duration text to yearly fraction based on "price per year" logic
                if (durationText.includes('12')) duration = 1;
                else if (durationText.includes('6')) duration = 0.5;
                else if (durationText.includes('3')) duration = 0.25;
                else if (durationText.includes('1')) duration = 1 / 12;

                $.ajax({
                    url: '{{ url('api/calculate-order') }}',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        plan_id: planId,
                        quantity: quantity,
                        duration: duration
                    }),
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            $('.price-amount').text(data.total);
                            $('.subtotal-amount').text('₹' + data.total);
                            $('.item-price-value').text('₹' + data.total);
                            $('.item-description').text(durationText + ' Plan');
                            $('.summary-item-header .item-price').text(quantity + (quantity > 1 ?
                                ' Mailboxes' : ' Mailbox'));
                        }
                    },
                    error: function(error) {
                        console.error('Error:', error);
                    }
                });
            }

            $('.plus').on('click', function() {
                $qtyInput.val(parseInt($qtyInput.val()) + 1);
                updateCalculation();
            });

            $('.minus').on('click', function() {
                if (parseInt($qtyInput.val()) > 1) {
                    $qtyInput.val(parseInt($qtyInput.val()) - 1);
                    updateCalculation();
                }
            });

            $qtyInput.on('change', function() {
                if ($(this).val() < 1) $(this).val(1);
                updateCalculation();
            });

            $durationSelect.on('change', updateCalculation);

            // Continue to checkout
            window.continueCheckout = function() {
                var quantity = parseInt($qtyInput.val());
                var durationText = $durationSelect.val();
                var subtotal = $('.price-amount').text();
                var planName = '{{ $plan->name }}';
                var price = '{{ $plan->price }}';

                $.ajax({
                    url: '{{ url('api/save-order') }}',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        plan_id: planId,
                        plan_name: planName,
                        price: price,
                        quantity: quantity,
                        duration: durationText,
                        subtotal: subtotal
                    }),
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.success) {
                            window.location.href = '{{ url('checkout') }}/' + data.order_id;
                        } else if (data.error) {
                            alertify.error(data.message || 'Failed to save order. Please check your selection.');
                        } else {
                            alertify.error('An unexpected error occurred.');
                        }
                    },
                    error: function(error) {
                        console.error('Error saving order:', error);
                        alertify.error('An error occurred. Please try again.');
                    }
                });
            }

            // Initial call
            updateCalculation();
        });
    </script>
@endsection

