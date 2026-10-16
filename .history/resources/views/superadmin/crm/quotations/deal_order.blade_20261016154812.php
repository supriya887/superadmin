@extends('superadmin.layouts.app')

@php
    $converted = !empty($converted);
    $status = strtolower(trim((string) ($order->status ?? 'pending')));
    $statusOk = $converted && in_array($status, ['completed', 'paid', 'success', 'active'], true);
    $statusBadge = $converted
        ? ($statusOk ? 'bg-success' : ($status === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark'))
        : 'bg-warning text-dark';
    $statusLabel = $converted
        ? ($statusOk ? 'Active' : ucfirst($status ?: 'Pending'))
        : 'Not converted';
    $lead = optional($quotation)->lead;
    $mobile = $lead->mobile ?? null;
    $gstin = optional($quotation)->gstin;
    $hasPoData = !empty($order->po_mode ?? null) || !empty($order->po_number ?? null) || !empty($order->po_document_url ?? null);
    $modeLabel = strtolower((string) ($order->po_mode ?? ''));
    $poDone = $hasPoData;
    $placedDone = $statusOk;
    $poSteps = [
        ['label' => 'PO', 'done' => $poDone],
        ['label' => 'Placed', 'done' => $placedDone],
    ];
    $address = collect([
        optional($quotation)->mailing_address,
        optional($quotation)->city,
        optional($quotation)->state,
        optional($quotation)->pincode,
        optional($quotation)->country,
    ])->filter()->implode(', ');
@endphp

@push('styles')
<style>
    .od-label { font-size: .75rem; color: #6c757d; margin-bottom: .15rem; }
    .od-value { font-weight: 500; word-break: break-word; }
    .od-step-dot {
        width: 26px; height: 26px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: .75rem; font-weight: 700;
        background: #ced4da; color: #6c757d;
    }
    .od-step-dot.done { background: #1e58b4; color: #fff; }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h4 class="mb-1">{{ $converted ? 'Order Details' : 'Order preview' }}</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('crm.deals') }}">CRM Deals</a></li>
                    <li class="breadcrumb-item active">#{{ $order->order_no }}</li>
                </ol>
            </div>
            <a href="{{ route('crm.deals') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="col-12">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif
    @if ($errors->any())
        <div class="col-12">
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="col-12 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h5 class="mb-0">Order #{{ $order->order_no }}</h5>
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                            <span class="badge bg-light text-dark border">CRM</span>
                        </div>
                        <div class="small text-muted">
                            Created {{ !empty($order->created_at) ? \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y') : '—' }}
                            <span class="mx-1">·</span>
                            Updated {{ !empty($order->updated_at) ? \Illuminate\Support\Carbon::parse($order->updated_at)->format('d M Y') : '—' }}
                            @if($quotation)
                                <span class="mx-1">·</span>
                                Quote {{ $quotation->quotation_no }}
                            @endif
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if(!$converted && $quotation)
                            <form method="POST" action="{{ route('crm.deals.convert', $quotation) }}" class="d-flex align-items-center gap-2"
                                  onsubmit="return confirm('Convert this deal to a CRM order?');">
                                @csrf
                                <label class="small text-muted mb-0" for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ \Illuminate\Support\Carbon::parse(($quotation->plans[0]['billing_start_date'] ?? null) ?: now())->format('Y-m-d') }}" required style="width:150px;">
                                <label class="small text-muted mb-0" for="billing_cycle">Billing Cycle</label>
                                <select name="billing_cycle" id="billing_cycle" class="form-select form-select-sm" style="width:140px;">
                                    @foreach (['Monthly', 'Quarterly', 'Half Yearly', 'Yearly', '3 Yearly'] as $cycle)
                                        <option value="{{ $cycle }}" {{ ($order->period ?: 'Yearly') === $cycle ? 'selected' : '' }}>{{ $cycle }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="ti ti-transform me-1"></i> Convert to order
                                </button>
                            </form>
                        @endif
                        @if($quotation)
                            <a href="{{ route('crm.quotations.show', $quotation) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-file-text me-1"></i> View Quotation
                            </a>
                            <a href="{{ route('crm.quotations.invoice', $quotation) }}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="ti ti-file-invoice me-1"></i> View Invoice
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Order Type</div>
                        <div class="od-value">{{ $quotation->dealTypeLabel() }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Plan</div>
                        <div class="od-value">{{ $order->plan_name ?: '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Domain</div>
                        <div class="od-value">{{ $order->domain_name ?: '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Order</div>
                        <div class="od-value">#{{ $order->order_no }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Type</div>
                        <div class="od-value">CRM</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Billing Cycle</div>
                        <div class="od-value">{{ $order->period ?: 'Yearly' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Start Date</div>
                        <div class="od-value">{{ !empty($order->start_date) ? \Illuminate\Support\Carbon::parse($order->start_date)->format('d-m-Y') : '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Next Renewal</div>
                        <div class="od-value">{{ !empty($order->renewal_date) ? \Illuminate\Support\Carbon::parse($order->renewal_date)->format('d-m-Y') : '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Amount</div>
                        <div class="od-value">₹{{ number_format((float) ($order->total_amount ?? 0), 2) }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Employee</div>
                        <div class="od-value">{{ $order->employee ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Customer</h6>
                <div class="fw-semibold fs-5 mb-1">{{ $order->customer_name ?: '—' }}</div>
                <div class="small text-muted mb-3">{{ $order->company_name ?: '—' }}</div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="od-label">Email</div>
                        <div class="od-value">{{ $order->email ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">Mobile</div>
                        <div class="od-value">{{ $mobile ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">Company</div>
                        <div class="od-value">{{ $order->company_name ?: '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">GSTIN</div>
                        <div class="od-value">{{ $gstin ?: '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="od-label">Billing address</div>
                        <div class="od-value">{{ $address !== '' ? $address : '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Description</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>{{ $item['name'] }}</td>
                                    <td class="text-muted">{{ $item['description'] ?: '—' }}</td>
                                    <td class="text-end">{{ (int) $item['quantity'] }}</td>
                                    <td class="text-end">₹{{ number_format((float) $item['unit_price'], 2) }}</td>
                                    <td class="text-end">₹{{ number_format((float) $item['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No line items</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <div class="text-end">
                        <div class="od-label">Grand total</div>
                        <div class="fs-5 fw-semibold">₹{{ number_format((float) ($order->total_amount ?? 0), 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h6 class="mb-0">PO Journey</h6>
                    @if($hasPoData)
                        <a href="{{ route('crm.deals.po', $quotation) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-file-text me-1"></i> View PO Document
                        </a>
                    @endif
                </div>

                <div class="d-flex justify-content-center mb-4">
                    <div class="position-relative w-100" style="max-width:320px;">
                        <div class="position-absolute start-0 end-0" style="top:12px;height:2px;background:#dee2e6;"></div>
                        <div class="d-flex justify-content-between position-relative">
                            @foreach ($poSteps as $step)
                                <div class="text-center" style="width:90px;">
                                    <span class="od-step-dot {{ $step['done'] ? 'done' : '' }}">{{ $step['done'] ? '✓' : '' }}</span>
                                    <div class="small mt-2 {{ $step['done'] ? 'fw-semibold' : 'text-muted' }}">{{ $step['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($hasPoData)
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="od-label">PO Mode</div>
                            <div class="od-value">
                                @if ($modeLabel === 'uploaded')
                                    <span class="badge bg-info text-dark">Uploaded</span>
                                @elseif ($modeLabel === 'generated')
                                    <span class="badge bg-secondary">Generated</span>
                                @else
                                    {{ ucfirst((string) ($order->po_mode ?? '—')) }}
                                @endif
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="od-label">PO Number</div>
                            <div class="od-value">{{ $order->po_number ?? '—' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="od-label">PO Date</div>
                            <div class="od-value">{{ !empty($order->po_date) ? \Illuminate\Support\Carbon::parse($order->po_date)->format('d M Y') : '—' }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="od-label">PO Amount</div>
                            <div class="od-value">₹{{ number_format((float) ($order->po_amount ?? 0), 2) }}</div>
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('crm.deals.po.save', $quotation) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">PO Source</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="po_mode" id="po_mode_generated" value="generated" checked>
                                    <label class="form-check-label" for="po_mode_generated">Generate PO</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="po_mode" id="po_mode_uploaded" value="uploaded">
                                    <label class="form-check-label" for="po_mode_uploaded">Upload PO (CRM / SAP)</label>
                                </div>
                            </div>
                            <small class="text-muted">Companies using their own CRM/SAP PO can upload a PDF instead of our format.</small>
                        </div>
                        <div class="row g-2 d-none" id="po_upload_fields">
                            <div class="col-md-4">
                                <label class="form-label">External PO Number</label>
                                <input type="text" name="po_number" class="form-control form-control-sm" placeholder="From CRM / SAP">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">PO Date</label>
                                <input type="date" name="po_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Upload PO PDF</label>
                                <input type="file" name="po_document" class="form-control form-control-sm" accept="application/pdf,.pdf">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-sm btn-primary" id="po_submit_btn">
                                <i class="ti ti-file-plus me-1"></i> Generate PO
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var generated = document.getElementById('po_mode_generated');
        var uploaded = document.getElementById('po_mode_uploaded');
        var fields = document.getElementById('po_upload_fields');
        var btn = document.getElementById('po_submit_btn');
        if (!generated || !uploaded || !fields || !btn) return;
        function sync() {
            var isUpload = uploaded.checked;
            fields.classList.toggle('d-none', !isUpload);
            btn.innerHTML = isUpload
                ? '<i class="ti ti-upload me-1"></i> Upload PO'
                : '<i class="ti ti-file-plus me-1"></i> Generate PO';
        }
        generated.addEventListener('change', sync);
        uploaded.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
