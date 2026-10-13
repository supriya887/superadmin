@extends('home.render')

@section('metaTitle')
    <title>Payment Successful | Tizzy Cloud</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .success-icon-wrapper {
            font-size: 5rem;
            color: #1665b6;
            background: #eef5ff;
            width: 120px;
            height: 120px;
            line-height: 120px;
            border-radius: 50%;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-heading {
            color: #43475b;
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 1rem;
        }
        .success-description {
            color: #64748b;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }
        .order-details-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            text-align: left;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .order-details-card h4 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 1rem;
        }
        .detail-label {
            color: #94a3b8;
            font-weight: 500;
        }
        .detail-value {
            color: #334155;
            font-weight: 600;
        }
        .btn-dashboard {
            background: #1665b6;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-dashboard:hover {
            background: #12549a;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(22, 101, 182, 0.3);
            color: white;
        }
        .auth-wrapper {
            background: #f8fafc;
            min-height: 80vh;
            display: flex;
            align-items: center;
        }
        .auth-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
@endsection

@section('Body')
    <div class="auth-wrapper py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-6">
                    <div class="auth-card p-5 text-center">
                        <div class="success-icon-wrapper mb-4">
                            <i class="fa fa-circle-check"></i>
                        </div>
                        
                        <h1 class="success-heading">Payment Successful!</h1>
                        <p class="success-description">
                            Thank you for your purchase. We've received your payment and your order is now being processed.
                        </p>
                        
                        <div class="order-details-card">
                            <h4>Order Information</h4>
                            <div class="detail-row">
                                <span class="detail-label">Order Reference</span>
                                <span class="detail-value">#{{ $order->id }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Plan Name</span>
                                <span class="detail-value">{{ $order->plan_name }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Amount</span>
                                <span class="detail-value">₹ {{ number_format($order->total_amount, 2) }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Transaction ID</span>
                                <span class="detail-value">{{ $order->razorpay_payment_id }}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Payment Status</span>
                                <span class="detail-value text-success">
                                    <i class="fa fa-check-circle me-1"></i> Completed
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="{{ route('dashboard') }}" class="btn-dashboard">
                                Go to My Dashboard
                            </a>
                        </div>
                        
                        <div class="mt-4">
                            <p class="text-muted small">
                                A confirmation email has been sent to {{ auth()->user()->email ?? 'your registered email' }}.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
