
@extends('home.render')

@section('cssLinks')
    {{-- google font  --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root {
            --primary-blue: #1665b6;
            --light-blue: #f0f7ff;
            --border-color: #e1e8f0;
            --text-dark: #2c3e50;
        }

        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }

        .payment-method__wrapper {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .payment-method__wrapper.active {
            border-color: var(--primary-blue);
            box-shadow: 0 4px 20px rgba(22, 101, 182, 0.08);
        }

        .payment-method__header {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
        }

        .payment-method__header .h-t-body-1-bold {
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        .payment-method__method-logo {
            height: 24px;
            width: auto;
            object-fit: contain;
        }

        .payment-method__body {
            display: none;
            padding: 0 20px 20px;
            border-top: 1px solid #f1f5f9;
        }

        .payment-method__wrapper.active .payment-method__body {
            display: block;
        }

        .form-control-custom {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
            margin-bottom: 16px;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary-blue);
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 0.85rem;
        }

        .pay-now-btn {
            width: 100%;
            padding: 14px;
            background: var(--primary-blue);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .pay-now-btn:hover { background: #12549a; }

        .upi-option-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-bottom: 16px;
        }

        .upi-option-btn {
            border: 1.5px solid var(--border-color);
            border-radius: 10px;
            background: #fff;
            color: var(--text-dark);
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.3;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upi-option-btn:hover {
            border-color: var(--primary-blue);
            background: #f8fbff;
        }

        .upi-option-btn.active {
            border-color: var(--primary-blue);
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .upi-option-card {
            border: 1px solid #dbe6f3;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 6px 18px rgba(22, 101, 182, 0.08);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .upi-option-card:hover {
            box-shadow: 0 10px 24px rgba(22, 101, 182, 0.14);
        }

        .upi-accordion-header {
            width: 100%;
            border: none;
            background: transparent;
            padding: 14px;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }

        .upi-accordion-right {
            color: #64748b;
            transition: transform 0.2s ease;
        }

        .upi-option-card.open .upi-accordion-right {
            transform: rotate(180deg);
            color: var(--primary-blue);
        }

        .upi-accordion-body {
            display: none;
            padding: 0 14px 14px;
            border-top: 1px solid #eaf1f9;
        }

        .upi-option-card.open .upi-accordion-body {
            display: block;
        }

        .upi-option-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .upi-option-subtitle {
            font-size: 0.8rem;
            color: #64748b;
            margin: 0;
        }

        .upi-generate-btn {
            width: 100%;
            border: 1.5px solid var(--primary-blue);
            color: var(--primary-blue);
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .upi-generate-btn:hover {
            background: #1665b6;
            color: #fff;
        }

        .upi-qr-box {
            border: 1px solid #d5e5f7;
            border-radius: 14px;
            background: linear-gradient(180deg, #fafdff 0%, #f2f8ff 100%);
            padding: 18px;
            margin-top: 16px;
            text-align: center;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.8);
        }

        .upi-qr-box img {
            max-width: 220px;
            width: 100%;
            height: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            padding: 8px;
        }

        .upi-qr-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1f3f61;
            margin-bottom: 2px;
        }

        .upi-qr-subtitle {
            font-size: 0.8rem;
            color: #6b7f96;
            margin-bottom: 10px;
        }

        .upi-qr-frame {
    width: 260px; /* slightly bigger */
    margin: 0 auto;
    border-radius: 14px;
    border: 1px solid #dce7f5;
    padding: 12px; /* more breathing space */
    background: #fff;
    box-shadow: 0 6px 18px rgba(20, 58, 97, 0.08);
}

        .upi-qr-image {
            width: 100%;
            max-width: 214px;
            height: auto;
            border: 0;
            border-radius: 6px;
            padding: 0;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .upi-qr-apps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: #6d8198;
            font-size: 0.76rem;
            font-weight: 500;
            margin-top: 10px;
        }

        .upi-app-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .upi-app-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid #c6dcf3;
            border-radius: 10px;
            min-height: 44px;
            padding: 10px 8px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #184b7d;
            background: #fff;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .upi-app-link:hover {
            border-color: #8bb6e1;
            background: #eef6ff;
            color: #113b62;
        }

        .upi-app-badge {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #ecf4ff;
            color: #1d5f9f;
            font-size: 0.75rem;
        }

        .upi-app-logo {
            width: 24px;
            height: 24px;
            object-fit: contain;
            display: inline-block;
        }

        .upi-app-fallback {
            min-width: 28px;
            height: 28px;
            padding: 0 8px;
            border-radius: 999px;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: #fff;
        }

        .upi-app-fallback.gpay {
            background: linear-gradient(90deg, #4285f4, #34a853);
        }

        .upi-app-fallback.phonepe {
            background: #5f259f;
        }

        .upi-app-fallback.paytm {
            background: linear-gradient(90deg, #00baf2, #002970);
        }

        .upi-app-fallback.bhim {
            background: linear-gradient(90deg, #ff7a00, #00a651);
        }

        .upi-app-note {
            font-size: 0.76rem;
            color: #627b95;
            margin-top: 10px;
            margin-bottom: 0;
        }

        @media (max-width: 767.98px) {
            .upi-qr-frame {
                width: 100%;
            }

            .upi-qr-image {
                max-width: 100%;
            }
        }

        .upi-qr-note {
            font-size: 0.88rem;
            color: #47617d;
            margin-bottom: 12px;
            font-weight: 500;
        }

        .upi-qr-loader {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 170px;
            color: #2f5f91;
        }

        .upi-qr-spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #dbeafe;
            border-top-color: var(--primary-blue);
            border-radius: 50%;
            animation: upiSpin 0.8s linear infinite;
        }

        .upi-qr-loader-text {
            font-size: 0.88rem;
            font-weight: 600;
            color: #47617d;
            margin: 0;
        }

        @keyframes upiSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .payment-error {
            color: #ef4444;
            font-size: 0.9rem;
            display: none;
        }

        .summary-total {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-blue);
            border-top: 2px dashed var(--border-color);
            padding-top: 16px;
            margin-top: 16px;
        }
        .upi-app-logo {
    width: 28px;
    height: 28px;
    object-fit: contain;
    display: inline-block;
}
    </style>
@endsection

@section('metaTitle')
    <title>Complete Your Payment - Tizzy Cloud</title>
@endsection

@section('Body')
<section class="select-plan-section position-relative py-5">
    <div class="container">
        <h2 class="your-cart-heading mb-4">Complete Payment</h2>
        
        <div class="cart-progress mb-5">
            <div class="step-wrapper">
                <div class="step">Select Plan</div>
                <div class="step">Checkout</div>
                <div class="step active">Payment</div>
            </div>
        </div>

        @if(!empty($paymentExpired))
            <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert">
                <h3 class="h5 mb-2">Payment Link Expired</h3>
                <p class="mb-2">{{ $expiredMessage ?? 'This payment link is no longer active.' }}</p>
                <p class="mb-1"><strong>Order:</strong> #{{ $order->order_no ?? $order->id }}</p>
                @if(!empty($paymentRetry['expires_at']))
                    <p class="small text-muted mb-0">Link was valid until {{ $paymentRetry['expires_at'] }}</p>
                @endif
                <p class="small text-muted mt-3 mb-0">Please contact support or your partner to request a new payment link.</p>
            </div>
        @elseif(!empty($paymentRetry['allowed']))
            <div class="alert alert-info border-0 shadow-sm mb-4 py-3" role="status">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span id="payment-retry-note">
                        Complete payment within
                        @if(!empty($paymentRetry['remaining_hrs']))
                            <strong>{{ number_format((float) $paymentRetry['remaining_hrs'], 1) }}</strong> hour(s).
                        @else
                            24 hours.
                        @endif
                    </span>
                    @if(!empty($paymentRetry['expires_at']))
                        <span class="small text-muted">Valid until {{ $paymentRetry['expires_at'] }}</span>
                    @endif
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-7">
                @if(empty($paymentExpired))
                <div class="select-payment__method w-100">
                    <div class="h-mt-24">
                        <div class="select-payment__execution-time h-t-body-1 h-mb-16">Select Payment Method:</div>
                        <div class="select-payment__methods">
                            
                            <!-- UPI -->
                            <div class="payment-method__wrapper w-100 active">
                                <div class="payment-method__header" onclick="togglePaymentSection(this)">
                                    <div class="h-t-body-1-bold h-m-0">Pay via UPI</div>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ asset('public/home/images/icons/upi.png') }}" alt="UPI" class="payment-method__method-logo me-3">
                                        <i class="fa fa-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="payment-method__body">
                                    <div id="upi-form" class="payment-form" style="display: block;">
                                        <div class="upi-option-grid">
                                            
                                          
                                            <div class="upi-option-card d-md-none">
                                                <button type="button" class="upi-accordion-header" onclick="toggleUpiAccordion(this)">
                                                    <div>
                                                        <div class="upi-option-title"><i class="fa fa-mobile text-primary"></i> Pay via UPI Apps</div>
                                                    </div>
                                                    <i class="fa fa-chevron-down upi-accordion-right"></i>
                                                </button>
                                                <div class="upi-accordion-body">
                                                    <div class="upi-app-grid mt-2">
                                                        <a class="upi-app-link" href="#" onclick="event.preventDefault(); startUpiVariantPayment('intent_redirect', 'gpay');">
                                                            <img class="upi-app-logo"
                                                            src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/googlepay.svg"
                                                            alt="Google Pay">                                                            <span class="upi-app-fallback gpay" aria-hidden="true">GPay</span>
                                                            <span>Google Pay</span>
                                                        </a>
                                                        <a class="upi-app-link" href="#" onclick="event.preventDefault(); startUpiVariantPayment('intent_redirect', 'phonepe');">
                                                            <img class="upi-app-logo"
                                                            src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/phonepe.svg"
                                                            alt="PhonePe">                                                            <span class="upi-app-fallback phonepe" aria-hidden="true">PhonePe</span>
                                                            <span>PhonePe</span>
                                                        </a>
                                                        <a class="upi-app-link" href="#" onclick="event.preventDefault(); startUpiVariantPayment('intent_redirect', 'paytm');">
                                                            <img class="upi-app-logo"
                                                            src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/paytm.svg"
                                                            alt="Paytm">                                                            <span class="upi-app-fallback paytm" aria-hidden="true">Paytm</span>
                                                            <span>Paytm</span>
                                                        </a>
                                                    </div>
                                                    <p class="upi-app-note">App will open directly after tap.</p>
                                                </div>
                                            </div>
                                            <div class="upi-option-card">
                                                <button type="button" class="upi-accordion-header" onclick="toggleUpiAccordion(this)">
                                                    <div>
                                                        <div class="upi-option-title"><i class="fa fa-qrcode text-primary"></i> Generate QR</div>
                                                    </div>
                                                    <i class="fa fa-chevron-down upi-accordion-right"></i>
                                                </button>
                                                <div class="upi-accordion-body">
                                                    <button id="generate-qr-btn" class="upi-generate-btn mt-2" onclick="generateQr()">Generate QR</button>
                                                    <div id="qr-container" class="upi-qr-box">
                                                        <p class="upi-qr-note mb-0">Your secure UPI QR will appear here after you continue.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="upi-option-card">
                                                <button type="button" class="upi-accordion-header" onclick="toggleUpiAccordion(this)">
                                                    <div>
                                                        <div class="upi-option-title"><i class="fa fa-check-circle text-primary"></i> Verify UPI ID</div>
                                                        <div class="upi-option-subtitle">Confirm your UPI ID and continue to payment QR.</div>
                                                    </div>
                                                    <i class="fa fa-chevron-down upi-accordion-right"></i>
                                                </button>
                                                <div class="upi-accordion-body">
                                                    <div class="form-group mb-0 mt-2">
                                                    <label class="form-label">UPI ID (VPA)</label>
                                                    <input type="text" id="upi_id" class="form-control-custom mb-2" placeholder="username@bankname">
                                                    <button class="pay-now-btn" onclick="startUpiVariantPayment('verify_collect')">Pay via UPI</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="upi-error" class="payment-error text-center mt-3"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Credit / Debit Card -->
                            <div class="payment-method__wrapper w-100">
                                <div class="payment-method__header" onclick="togglePaymentSection(this)">
                                    <div class="h-t-body-1-bold h-m-0">Pay via Card</div>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ asset('public/home/images/icons/VMU.png') }}" alt="Card" class="payment-method__method-logo me-3">
                                        <i class="fa fa-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="payment-method__body">
                                    <div id="card-form" class="payment-form" style="display: block;">
                                        <div class="form-group">
                                            <label class="form-label">Card Number</label>
                                            <input type="text" id="card_number" class="form-control-custom" placeholder="0000 0000 0000 0000" maxlength="19">
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label class="form-label">Expiry</label>
                                                <input type="text" id="card_expiry" class="form-control-custom" placeholder="MM/YY" maxlength="7">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">CVV</label>
                                                <input type="password" id="card_cvv" class="form-control-custom" placeholder="123" maxlength="4">
                                            </div>
                                        </div>
                                        <button class="pay-now-btn" onclick="processCustomPayment('card')">Pay Now</button>
                                        <div id="card-error" class="payment-error text-center mt-3"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Netbanking -->
                            <div class="payment-method__wrapper w-100">
                                <div class="payment-method__header" onclick="togglePaymentSection(this)">
                                    <div class="h-t-body-1-bold h-m-0">Netbanking</div>
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-university me-3"></i>
                                        <i class="fa fa-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="payment-method__body">
                                    <div id="netbanking-form" class="payment-form" style="display: block;">
                                        <button class="pay-now-btn" onclick="processCustomPayment('netbanking')">Select Bank & Pay</button>
                                        <div id="netbanking-error" class="payment-error text-center mt-3"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Wallet -->
                            <div class="payment-method__wrapper w-100">
                                <div class="payment-method__header" onclick="togglePaymentSection(this)">
                                    <div class="h-t-body-1-bold h-m-0">Wallet</div>
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-google-wallet me-3"></i>
                                        <i class="fa fa-chevron-down"></i>
                                    </div>
                                </div>
                                <div class="payment-method__body">
                                    <div id="wallet-form" class="payment-form" style="display: block;">
                                        <button class="pay-now-btn" onclick="processCustomPayment('wallet')">Select Wallet & Pay</button>
                                        <div id="wallet-error" class="payment-error text-center mt-3"></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="order-summary-card">
                    <div class="plan-badge">{{ $order->duration }} Plan</div>
                    <h3 class="summary-title">Order Summary</h3>
                    
                    <div class="summary-row">
                        <span class="summary-label">Plan</span>
                        <span class="summary-value">{{ $order->plan_name }}</span>
                    </div>
                    
                    @if($order->domain_name)
                    <div class="summary-row">
                        <span class="summary-label">Domain</span>
                        <span class="summary-value">{{ $order->domain_name }}</span>
                    </div>
                    @endif

                    <div class="summary-row">
                        <span class="summary-label">Quantity</span>
                        <span class="summary-value">{{ $order->quantity }} Mailbox(es)</span>
                    </div>

                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">₹ {{ number_format($order->subtotal, 2) }}</span>
                    </div>

                    @if($order->discount_amount > 0)
                    <div class="summary-row text-success">
                        <span class="summary-label text-success">Discount</span>
                        <span class="summary-value">₹ -{{ number_format($order->discount_amount, 2) }}</span>
                    </div>
                    @endif

                    <div class="summary-row">
                        <span class="summary-label">GST (18%)</span>
                        <span class="summary-value">₹ {{ number_format($order->tax_amount, 2) }}</span>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Total Amount</span>
                        <span>₹ {{ number_format($order->total_amount, 2) }}</span>
                    </div>

                    <div class="mt-4 text-center">
                        <p class="text-muted small"><i class="fa fa-lock me-2"></i>Secure SSL Encrypted Payment</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    const createOrderApiUrl = "{{ url('api/payment-create-order') }}";
    const paymentFailureApiUrl = "{{ url('api/payment-failure') }}";
    const checkPaymentBaseUrl = "{{ url('check-payment') }}";
    const orderDbId = {{ (int) ($order->id ?? 0) }};
    let paymentLinkExpired = {{ !empty($paymentExpired) ? 'true' : 'false' }};
    const upiVerifyRequestUrl = "{{ url('upi-verify-request') }}";
    const upiIntentBaseUrl = "{{ url('upi-intent') }}";

    async function parseJsonSafely(res) {
        const rawText = await res.text();
        try {
            return JSON.parse(rawText);
        } catch (e) {
            const preview = rawText ? rawText.substring(0, 120) : 'Empty response';
            throw new Error(`Server returned non-JSON response: ${preview}`);
        }
    }

    function handlePaymentLinkExpired(message) {
        paymentLinkExpired = true;
        showError('upi', message || 'This payment link has expired.');
        showError('card', message || 'This payment link has expired.');
        showError('netbanking', message || 'This payment link has expired.');
        showError('wallet', message || 'This payment link has expired.');
        setTimeout(() => window.location.reload(), 1500);
    }

    function recordPaymentFailure(errorMessage) {
        if (paymentLinkExpired || !orderDbId) {
            return Promise.resolve();
        }

        return fetch(paymentFailureApiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderDbId,
                error: errorMessage || 'Payment failed'
            })
        })
        .then(async (res) => {
            const data = await parseJsonSafely(res);
            if (data.expired) {
                handlePaymentLinkExpired(data.message || 'This payment link has expired.');
            } else if (data.remaining_hrs || data.expires_at) {
                const note = document.getElementById('payment-retry-note');
                if (note && typeof data.remaining_hrs === 'number') {
                    note.innerHTML = 'Complete payment within <strong>' + data.remaining_hrs.toFixed(1) + '</strong> hour(s).';
                }
            }
            return data;
        })
        .catch(() => null);
    }

    function startUpiVariantPayment(variant, appName = null) {
        if (variant === 'intent_redirect' && !isMobileDevice()) {
            showError('upi', 'Open on mobile to use UPI app redirect.');
            return;
        }
        if (variant === 'verify_collect') {
            verifyUpiCollectRequest();
            return;
        }
        if (variant === 'intent_redirect') {
            openUpiIntent(appName);
            return;
        }
        if (variant === 'qr_scan') {
            generateQr();
        }
    }
    function toggleUpiAccordion(button) {
        const card = button.closest('.upi-option-card');
        card.classList.toggle('open');
    }

    function verifyUpiAndGenerateQr() {
        startUpiVariantPayment('verify_collect');
    }

    function verifyUpiCollectRequest() {
        const vpa = document.getElementById('upi_id')?.value?.trim() || '';
        if (!vpa) {
            showError('upi', 'Please enter your UPI ID');
            return;
        }

        fetch(upiVerifyRequestUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                order_id: "{{ $order->id }}",
                upi_id: vpa
            })
        })
        .then(async (res) => {
            const data = await parseJsonSafely(res);
            if (!res.ok || data.error) {
                throw new Error(data.error || 'Unable to send collect request.');
            }
            return data;
        })
        .then((data) => {
            const qrContainer = document.getElementById('qr-container');
            const requestId = data?.data?.id ? `<div class="small text-muted mt-2">Request ID: ${data.data.id}</div>` : '';
            qrContainer.innerHTML = `
                <p class="upi-qr-title mb-1">Payment Request Sent</p>
                <p class="upi-qr-subtitle mb-1">Request sent to <strong>${vpa}</strong>. Open your UPI app and approve it.</p>
                ${requestId}
            `;
        })
        .catch((err) => {
            showError('upi', err.message || 'Unable to verify UPI ID.');
        });
    }

    function openUpiIntent(appName = 'upi') {
        const orderId = "{{ $order->id }}";
        fetch(`${upiIntentBaseUrl}/${orderId}`)
            .then(async (res) => {
                const data = await parseJsonSafely(res);
                if (!res.ok || !data.upi_link) {
                    throw new Error(data.message || 'Unable to create UPI intent link.');
                }
                return data;
            })
            .then((data) => {
                const upiLink = data.upi_link;
                const query = upiLink.includes('?') ? upiLink.split('?')[1] : '';
                let appLink = upiLink;

                if (query) {
                    if (appName === 'gpay') appLink = `tez://upi/pay?${query}`;
                    if (appName === 'phonepe') {
                        // PhonePe expects /upi/pay route for direct payment screen.
                        const isAndroid = /Android/i.test(navigator.userAgent);
                        appLink = isAndroid
                            ? `phonepe://upi/pay?${query}`
                            : `phonepe://upi/pay?${query}`;
                    }
                    if (appName === 'paytm') appLink = `paytmmp://pay?${query}`;
                }

                window.location.href = appLink;
            })
            .catch((err) => {
                showError('upi', err.message || 'Unable to open UPI app.');
            });
    }


    function generateQr() {
        const errorDiv = document.getElementById('upi-error');
        const qrContainer = document.getElementById('qr-container');
        const generateQrBtn = document.getElementById('generate-qr-btn');

        showError('upi', '');
        if (generateQrBtn) {
            generateQrBtn.disabled = true;
            generateQrBtn.textContent = 'Generating QR...';
        }

        qrContainer.innerHTML = `
            <div class="upi-qr-loader">
                <div class="upi-qr-spinner"></div>
                <p class="upi-qr-loader-text">Generating your secure UPI QR. Please wait...</p>
            </div>
        `;

        fetch("{{ route('generate.qr') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                order_id: "{{ $order->id }}"
            })
        })
        .then(async (res) => {
            const data = await parseJsonSafely(res);
            if (res.status === 410 || data.expired) {
                handlePaymentLinkExpired(data.error || data.message || 'This payment link has expired.');
                return null;
            }
            if (!res.ok || data.error) {
                throw new Error(data.error || 'Unable to generate QR. Try again.');
            }
            return data;
        })
        .then((data) => {
            if (!data) {
                return;
            }
            qrContainer.innerHTML = `
                <p class="upi-qr-title mb-0">Scan QR to Pay</p>
                <p class="upi-qr-subtitle">Complete payment in your UPI app</p>
                <div class="upi-qr-frame">
                    <img src="${data.qr_image}" class="upi-qr-image" alt="UPI QR Code" />
                </div>
                <div class="upi-qr-apps">
                    <i class="fa fa-mobile"></i>
                    Works with all UPI apps
                </div>
            `;

            checkPaymentStatus(data.qr_id, data.order_id || "{{ $order->id }}");
        })
        .catch((err) => {
            showError('upi', err.message || 'Unable to generate QR. Try again.');
            qrContainer.innerHTML = `<p class="upi-qr-note mb-0">Your secure UPI QR will appear here after you continue.</p>`;
        })
        .finally(() => {
            if (generateQrBtn) {
                generateQrBtn.disabled = false;
                generateQrBtn.textContent = 'Generate QR';
            }
        });
    }
function isMobileDevice() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

function checkPaymentStatus(qr_id, orderId) {
    const startedAt = Date.now();
    const maxPollingMs = 5 * 60 * 1000;

    const interval = setInterval(() => {
        const url = `${checkPaymentBaseUrl}/${encodeURIComponent(qr_id)}?order_id=${encodeURIComponent(orderId)}`;
        fetch(url)
            .then(res => parseJsonSafely(res))
            .then(data => {
                if (data.status === 'expired') {
                    clearInterval(interval);
                    handlePaymentLinkExpired(data.message || 'This payment link has expired.');
                    return;
                }
                if (data.status === 'paid') {
                    clearInterval(interval);
                    window.location.href = data.redirect_url;
                    return;
                }

                if (Date.now() - startedAt > maxPollingMs) {
                    clearInterval(interval);
                    showError('upi', 'Payment is still pending. If already paid, please refresh in a moment.');
                }
            })
            .catch(() => {
                // Keep polling even if one request fails temporarily
            });
    }, 5000);
}
    function togglePaymentSection(header) {
        const wrapper = header.closest('.payment-method__wrapper');
        const isActive = wrapper.classList.contains('active');
        
        // Close all sections
        document.querySelectorAll('.payment-method__wrapper').forEach(w => {
            w.classList.remove('active');
        });
        
        // Toggle current section
        if (!isActive) {
            wrapper.classList.add('active');
        }
    }

    function processCustomPayment(method, upiVariant = null, upiApp = null) {
        if (paymentLinkExpired) {
            return;
        }

        if (method === 'upi') {
            startUpiVariantPayment(upiVariant || 'verify_collect', upiApp);
            return;
        }

        const orderId = "{{ $order->id }}";
        const email = "{{ auth()->user()->email ?? '' }}";
        const mobile = "{{ auth()->user()->mobile ?? '' }}";
        const name = "{{ auth()->user()->name ?? 'Customer' }}";

        fetch(createOrderApiUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                order_id: orderId,
                payment_method: method
            })
        })
        .then(async (res) => {
            const data = await parseJsonSafely(res);
            if (res.status === 410 || data.expired) {
                handlePaymentLinkExpired(data.message || 'This payment link has expired.');
                return null;
            }
            if (!res.ok || !data.success) {
                throw new Error(data.message || data.error || "Unable to create payment order.");
            }
            return data;
        })
        .then((orderData) => {
            if (!orderData) {
                return;
            }
            let paymentData = {
                "key": orderData.key,
                "amount": orderData.amount,
                "currency": "INR",
                "order_id": orderData.order_id,
                "name": "Tizzy Cloud",
                "description": "Payment for {{ $order->plan_name }}",
                "prefill": {
                    "name": name,
                    "email": email,
                    "contact": mobile
                },
                "theme": { "color": "#1665b6" },
                "method": {
                    "upi": method === 'upi',
                    "card": method === 'card',
                    "netbanking": method === 'netbanking',
                    "wallet": method === 'wallet'
                }
            };

            paymentData.handler = function(response) {
                $.ajax({
                    url: "{{ route('verify-payment') }}",
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        payment_method: method,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature
                    },
                    success: function(res) {
                        if (res.status) {
                            window.location.href = res.redirect_url;
                        } else {
                            recordPaymentFailure(res.message || 'Payment verification failed').finally(() => {
                                showError(method, "Payment verification failed: " + res.message);
                            });
                        }
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON?.message || 'An error occurred during payment verification.';
                        recordPaymentFailure(msg).finally(() => {
                            showError(method, msg);
                        });
                    }
                });
            };

            const rzp = new Razorpay(paymentData);
            rzp.on('payment.failed', function (response) {
                const msg = (response.error && response.error.description) ? response.error.description : 'Payment failed';
                recordPaymentFailure(msg).finally(() => {
                    showError(method, msg);
                });
            });
            rzp.open();
        })
        .catch((err) => {
            showError(method, err.message || "Unable to start payment.");
        });
    }

    function showError(method, message) {
        const errorDiv = document.getElementById(method + '-error');
        if (!message) {
            errorDiv.textContent = '';
            errorDiv.style.display = 'none';
            return;
        }
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }

    // Input Formatting
    document.getElementById('card_number')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = value;
    });

    document.getElementById('card_expiry')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 2) {
            value = value.substring(0, 2) + ' / ' + value.substring(2, 4);
        }
        e.target.value = value;
    });
</script>
@endsection
