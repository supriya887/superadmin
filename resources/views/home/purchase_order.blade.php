@extends('home.render')

@section('metaTitle')
    <title>Purchase Order | Tizzy Group</title>
@endsection

@section('Body')
<style>
    .purchase-order-invoice {
        max-width: 850px;
        margin: 30px auto;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 5px 18px rgba(46,54,80,0.13);
        font-family: 'Segoe UI', 'Arial', sans-serif;
        font-size: 15px;
        color: #333;
        overflow: hidden;
        padding: 36px 32px 24px 32px;
        position:relative;
    }
    .po-header {
        text-align: center;
        border-bottom: 1px solid #e7e7e7;
        margin-bottom: 18px;
        padding-bottom: 18px;
    }
    .po-header strong {
        font-size: 20px;
        letter-spacing: 1px;
    }
    .po-metadata {
        display:flex; justify-content: space-between; margin-bottom:16px; align-items:flex-start;
    }
    .po-meta-block { font-size: 13px; }
    .po-block + .po-block { margin-top: 16px; }
    .po-logo-block img {
        max-height:64px;
        margin-bottom:8px;
    }
    .po-client {
        background: #f7f9fb;
        border-radius: 10px;
        padding:14px 20px;
        margin-bottom:13px;
    }
    .po-table th, .po-table td {
        border: 1px solid #e2e2e2;
        padding: 8px 7px;
        text-align:left;
        font-size:14px;
    }
    .po-table { width: 100%; border-collapse:collapse; margin-bottom:18px;}
    .po-table th { background:#f3f6fa; font-weight:500; }
    .po-summary {
        float:right;
        width: 320px;
        margin-top: 0px;
        font-size:15px;
    }
    .po-summary table { width: 100%; }
    .po-terms { font-size:13px; margin-top:28px; color:#555;}
    .po-footer { text-align:center; font-size:11.5px; color:#bbbbbb; margin-top: 38px; }
    @media (max-width: 900px) {
        .purchase-order-invoice { padding:18px 7px 10px 7px; }
        .po-summary { width:100%; float:none; }
        .po-metadata { flex-direction:column; gap:16px;}
    }
</style>
<div class="purchase-order-invoice">
    <div class="po-header">
        <strong>Purchase Order</strong>
        <div style="font-size:13px; color:#666; margin-top:2px;">Tizzy Group</div>
    </div>
    <div class="po-metadata">
        <div class="po-meta-block">
            <div class="po-logo-block">
                <img src="{{ asset('assets/logo.png') }}" alt="Tizzy Logo">
                <div style="font-weight: 500;">Tizy Cloud Computing Private Ltd (P) 3/564</div>
                <div>Aluva Rd, Kochi, Kerala 683101, India</div>
                <div>Email: hello@tizzy.in | GSTIN: 32AAGCT3871K1Z7</div>
            </div>
        </div>
        <div class="po-meta-block">
            <div><strong>PO No:</strong> {{ $order->order_number ?? str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</div>
            <div><strong>PO Date:</strong> {{ $order->created_at->format('d M, Y') }}</div>
            <div><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($order->due_date ?? $order->created_at->addDays(15))->format('d M, Y') }}</div>
            <div><strong>Order Category:</strong> {{ ucfirst($order->order_category ?? 'new') }}</div>
        </div>
        <div class="po-meta-block">
            <div><strong>Bill To:</strong></div>
            <div class="po-client">
                <div><strong>Name:</strong> {{ $prefill['name'] ?? '-' }}</div>
                <div><strong>Email:</strong> {{ $prefill['email'] ?? '-' }}</div>
                <div><strong>Mobile:</strong> {{ $prefill['mobile'] ?? '-' }}</div>
                <div><strong>GSTIN:</strong> {{ $prefill['gstin'] ?? '-' }}</div>
                <div><strong>Company Name:</strong> {{ $prefill['company_name'] ?? '-' }}</div>
                <div><strong>Address:</strong> {{ $prefill['address'] ?? '-' }}</div>
            </div>
        </div>
    </div>
    <div class="po-block">
        <table class="po-table">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <th>Description</th>
                    <th style="width:90px;">HSN/SAC</th>
                    <th style="width:60px;">Qty</th>
                    <th style="width:60px;">Unit</th>
                    <th style="width:110px;">Unit Price</th>
                    <th style="width:110px;">Amount</th>
                </tr>
            </thead>
            <tbody>
<section class="select-plan-section position-relative py-5">
    <div class="container">
        <h2 class="your-cart-heading mb-4">Purchase Order</h2>
        <div class="card p-4 shadow-sm border-0">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input class="form-control" value="{{ $prefill['name'] ?? '' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input class="form-control" value="{{ $prefill['email'] ?? '' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mobile</label>
                    <input class="form-control" value="{{ $prefill['mobile'] ?? '' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">GSTIN</label>
                    <input class="form-control" value="{{ $prefill['gstin'] ?? '' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company Name</label>
                    <input class="form-control" value="{{ $prefill['company_name'] ?? '' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input class="form-control" value="{{ $prefill['address'] ?? '' }}" readonly>
                </div>
            </div>

            <hr class="my-4">
            <h5 class="mb-3">Plan Details</h5>
            <p class="mb-1"><strong>Plan:</strong> {{ $order->plan_name }}</p>
            <p class="mb-1"><strong>Domain:</strong> {{ $order->domain_name ?: 'N/A' }}</p>
            <p class="mb-1"><strong>Category:</strong> {{ ucfirst($order->order_category ?? 'new') }}</p>
            <p class="mb-1"><strong>Base Subtotal:</strong> ₹{{ number_format((float) ($proRata['base_subtotal'] ?? 0), 2) }}</p>
            <p class="mb-1"><strong>Pro-rata Credit:</strong> ₹{{ number_format((float) ($proRata['pro_rata_credit'] ?? 0), 2) }}</p>
            <p class="mb-1"><strong>GST:</strong> ₹{{ number_format((float) ($proRata['gst_amount'] ?? 0), 2) }}</p>
            <p class="mb-0"><strong>Total:</strong> ₹{{ number_format((float) ($proRata['total_amount'] ?? 0), 2) }}</p>

            <div class="mt-4 d-flex gap-2">
                <a href="{{ route('purchase-order.pdf', ['order' => \Illuminate\Support\Facades\Crypt::encryptString($order->id)]) }}" class="btn btn-primary">
                    Generate PO
                </a>
                <a href="{{ url('payment/' . \Illuminate\Support\Facades\Crypt::encryptString($order->id)) }}" class="btn btn-outline-primary">
                    Continue to Payment
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
