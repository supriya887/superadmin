@extends('superadmin.layouts.app')

@php
    $detailStatus = strtolower(trim((string) ($order->resolved_status ?? $order->status ?? 'pending')));
    if ($detailStatus === '') {
        $detailStatus = 'pending';
    }
    $detailStatusOk = in_array($detailStatus, ['completed', 'paid', 'success', 'active'], true);
    $detailStatusRejected = in_array($detailStatus, ['rejected', 'cancelled', 'failed'], true);
    $detailStatusBadge = $detailStatusOk
        ? 'bg-success'
        : ($detailStatusRejected ? 'bg-danger' : 'bg-warning text-dark');
    $detailRejectionReason = '';
    if ($detailStatusRejected) {
        $detailRejectionReason = method_exists($order, 'displayCancellationReason')
            ? trim((string) $order->displayCancellationReason())
            : trim((string) preg_replace('/<!--\s*PAYMENT_RETRY:.*$/is', '', (string) ($order->order_note ?? '')));
    }
    $detailStatusLabel = $detailStatusOk
        ? 'Active'
        : ($detailStatusRejected
            ? 'Cancelled'
            : ($detailStatus === 'processing' ? 'Processing' : ucfirst($detailStatus)));

    $hasPoData = !empty($order->po_mode ?? null) || !empty($order->po_number ?? null) || !empty($order->po_document_url ?? null) || !empty($order->po_journey_status ?? null);
    $journey = strtolower((string) ($order->po_journey_status ?? ''));
    $isB2bOrder = strtolower((string) ($order->resolved_order_type ?? $order->order_type ?? 'b2c')) === 'b2b';
    $creditNote = $creditNote ?? null;
    $creditNoteSkipReason = $creditNoteSkipReason ?? null;
    $orderTypeLabel = strtoupper((string) ($order->resolved_order_type ?? 'b2c'));

    $orderActionUrl = \Illuminate\Support\Facades\Route::has('superadmin.orders.action')
        ? route('superadmin.orders.action', $order->id)
        : url('/superadmin/orders-action/' . $order->id);
    $canActivate = in_array($detailStatus, ['pending', 'processing'], true);
    $canCancel = $detailStatusOk && !empty($orderCanBeCancelled);

    $forcePlaced = $detailStatusOk;
    $poDone = $forcePlaced || in_array($journey, ['po_done', 'aadhar_done', 'otp_done', 'placed'], true);
    $placedDone = $forcePlaced || $journey === 'placed';
    $poSteps = [
        ['label' => 'PO', 'done' => $poDone],
        ['label' => 'Placed', 'done' => $placedDone],
    ];
    $modeLabel = strtolower((string) ($order->po_mode ?? ''));
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
                <h4 class="mb-1">Order Details</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.orders.index') }}">Orders</a></li>
                    <li class="breadcrumb-item active">#{{ $order->order_no }}</li>
                </ol>
            </div>
            <a href="{{ route('superadmin.orders.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="col-12">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="col-12">
            <div class="alert alert-danger">{{ session('error') }}</div>
        </div>
    @endif

    <div class="col-12 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h5 class="mb-0">Order #{{ $order->order_no }}</h5>
                            <span id="detail_status_badge" class="badge {{ $detailStatusBadge }}">{{ $detailStatusLabel }}</span>
                            <span class="badge bg-light text-dark border">{{ $orderTypeLabel }}</span>
                        </div>
                        <div class="small text-muted">
                            Created {{ !empty($order->created_at) ? \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y') : '—' }}
                            <span class="mx-1">·</span>
                            Updated {{ !empty($order->updated_at) ? \Illuminate\Support\Carbon::parse($order->updated_at)->format('d M Y') : '—' }}
                        </div>
                        @if ($detailRejectionReason !== '')
                            <div id="detail_rejection_display" class="alert alert-danger py-2 px-3 mt-3 mb-0 small">
                                <strong>Cancellation reason:</strong>
                                <span id="detail_rejection_text">{{ $detailRejectionReason }}</span>
                            </div>
                        @else
                            <div id="detail_rejection_display" class="alert alert-danger py-2 px-3 mt-3 mb-0 small d-none">
                                <strong>Cancellation reason:</strong>
                                <span id="detail_rejection_text"></span>
                            </div>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if ($canActivate)
                            <button type="button" class="btn btn-sm btn-success js-detail-order-action" data-action="active">
                                <i class="ti ti-circle-check me-1"></i> Set Active
                            </button>
                        @endif
                        @if ($canCancel)
                            <button type="button" class="btn btn-sm btn-danger js-detail-order-action" data-action="cancel">
                                <i class="ti ti-circle-x me-1"></i> Cancel Order
                            </button>
                        @endif
                        @if ($detailStatusOk)
                            <a href="{{ url('/superadmin/orders-invoice/' . $order->id) }}" target="_blank" class="btn btn-sm btn-primary">
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
                        <div class="od-label">Plan</div>
                        <div class="od-value">{{ $order->resolved_plan_name ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Domain</div>
                        <div class="od-value">{{ $order->domain_name ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Order</div>
                        <div class="od-value">#{{ $order->order_no }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Type</div>
                        <div class="od-value">{{ $orderTypeLabel }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Amount</div>
                        <div class="od-value">₹{{ number_format((float) ($order->resolved_amount ?? 0), 2) }}</div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="od-label">Credit Used</div>
                        <div class="od-value">₹{{ number_format((float) ($order->resolved_credit_used ?? 0), 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Customer</h6>
                <div class="fw-semibold fs-5 mb-1">{{ $order->customer_name ?? '—' }}</div>
                <div class="small text-muted mb-3">ID {{ str_pad((string) ($order->user_id ?? 0), 5, '0', STR_PAD_LEFT) }}</div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="od-label">Email</div>
                        <div class="od-value">{{ $order->customer_email ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">Mobile</div>
                        <div class="od-value">{{ $order->customer_mobile ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">Company</div>
                        <div class="od-value">{{ $order->customer_company_name ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">PAN</div>
                        <div class="od-value">{{ $order->customer_pan ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="od-label">GSTIN</div>
                        <div class="od-value">{{ $order->customer_gstin ?? '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="od-label">Billing address</div>
                        <div class="od-value">{{ $order->customer_billing_address ?? '—' }}</div>
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
                                <th>Subscription ID</th>
                                <th>Item</th>
                                <th>Description</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoiceItems as $item)
                                <tr>
                                    <td class="text-muted">{{ $item['subscription_id'] ?? '—' }}</td>
                                    <td>{{ $item['name'] ?? '—' }}</td>
                                    <td class="text-muted">{{ $item['description'] ?: '—' }}</td>
                                    <td class="text-end">{{ (int) ($item['quantity'] ?? 1) }}</td>
                                    <td class="text-end">₹{{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                                    <td class="text-end">₹{{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No line items</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if (!empty($creditNote))
        <div class="col-12 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">Credit Note</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @if (!empty($creditNote['credit_note_link']))
                                <a href="{{ $creditNote['credit_note_link'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                    View Credit Note
                                </a>
                            @endif
                            <form method="post" action="{{ route('superadmin.orders.regenerate_credit_note', ['id' => $order->id]) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Regenerate credit note PDF with correct license quantity?');">
                                    Regenerate PDF
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="od-label">CN No</div>
                            <div class="od-value">{{ $creditNote['credit_note_no'] ?? '—' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="od-label">Amount</div>
                            <div class="od-value">₹{{ number_format((float) ($creditNote['credit_note_amount'] ?? 0), 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="od-label">Status</div>
                            <div class="od-value">
                                @php
                                    $cnStatus = strtolower(trim((string) ($creditNote['credit_note_status'] ?? 'pending')));
                                    $cnStatusLabel = match ($cnStatus) {
                                        'credited', 'paid' => 'Credited',
                                        'issued' => 'Issued',
                                        'cancelled', 'canceled' => 'Issued',
                                        default => ucfirst($cnStatus),
                                    };
                                @endphp
                                {{ $cnStatusLabel }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($detailStatusRejected)
        <div class="col-12 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="mb-0">Credit Note</h6>
                        <form method="post" action="{{ route('superadmin.orders.generate_credit_note', ['id' => $order->id]) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Generate a credit note for this cancelled order?');">
                                Generate Credit Note
                            </button>
                        </form>
                    </div>
                    <div class="alert alert-warning py-2 px-3 mb-0 small">
                        {{ $creditNoteSkipReason ?: 'Credit note was not generated for this cancelled order.' }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isB2bOrder || $hasPoData)
        <div class="col-12 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">PO Journey</h6>
                        @if (!empty($order->po_document_url) && $modeLabel === 'uploaded')
                            <a href="{{ $order->po_document_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-file-text me-1"></i> View PO Document
                            </a>
                        @elseif ($hasPoData || !empty($order->po_number))
                            <a href="{{ route('superadmin.orders.po_pdf', ['id' => $order->id]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
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
                        <div class="col-6 col-md-3">
                            <div class="od-label">Status</div>
                            <div class="od-value">{{ $placedDone ? 'Placed' : ucwords(str_replace('_', ' ', (string) ($order->po_journey_status ?? 'po_pending'))) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <h6 class="mb-0">Status actions</h6>
                    <span class="badge {{ $detailStatusBadge }}">{{ $detailStatusLabel }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @if ($canActivate)
                        <button type="button" class="btn btn-sm btn-success js-detail-order-action" data-action="active">
                            <i class="ti ti-circle-check me-1"></i> Set Active
                        </button>
                    @endif
                    @if ($canCancel)
                        <button type="button" class="btn btn-sm btn-danger js-detail-order-action" data-action="cancel">
                            <i class="ti ti-circle-x me-1"></i> Cancel Order
                        </button>
                    @elseif ($detailStatusOk && empty($orderCanBeCancelled) && !empty($orderCancellationBlockReason))
                        <div class="alert alert-danger py-2 px-3 mb-0 small">{{ $orderCancellationBlockReason }}</div>
                    @elseif ($detailStatusRejected)
                        <div class="alert alert-secondary py-2 px-3 mb-0 small">Order is cancelled.</div>
                    @endif
                    @if ($detailStatusOk)
                        <a href="{{ url('/superadmin/orders-invoice/' . $order->id) }}" target="_blank" class="btn btn-sm btn-primary">
                            <i class="ti ti-file-invoice me-1"></i> View Invoice
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailActionConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailConfirmTitle">Confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="detailConfirmMessage" class="mb-0">Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                <button type="button" class="btn btn-primary" id="detailConfirmActionBtn">Yes</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function() {
        var actionUrl = @json($orderActionUrl);
        var pendingAction = null;
        var isProcessing = false;

        function notify(type, msg) {
            if (typeof alertify !== 'undefined') {
                if (type === 'success') alertify.success(msg);
                else if (type === 'error') alertify.error(msg);
                else alertify.message(msg);
                return;
            }
            if (typeof toastr !== 'undefined') {
                toastr[type] ? toastr[type](msg) : toastr.info(msg);
                return;
            }
            alert(msg);
        }

        $('.js-detail-order-action').on('click', function() {
            if (isProcessing) return;
            pendingAction = String($(this).data('action') || '').toLowerCase();
            if (pendingAction === 'active') {
                $('#detailConfirmTitle').text('Activate Order');
                $('#detailConfirmMessage').text('Are you sure you want to activate this order?');
                $('#detailConfirmActionBtn').removeClass('btn-danger').addClass('btn-success').text('Yes, Activate');
            } else if (pendingAction === 'cancel') {
                $('#detailConfirmTitle').text('Cancel Order');
                $('#detailConfirmMessage').text('Are you sure you want to cancel this order?');
                $('#detailConfirmActionBtn').removeClass('btn-success').addClass('btn-danger').text('Yes, Cancel');
            } else {
                return;
            }
            $('#detailActionConfirmModal').modal('show');
        });

        $('#detailConfirmActionBtn').on('click', function() {
            if (!pendingAction || isProcessing) return;
            isProcessing = true;
            var $btn = $(this).prop('disabled', true).text('Please wait...');
            $.ajax({
                url: actionUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    action: pendingAction
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).done(function(res) {
                $('#detailActionConfirmModal').modal('hide');
                if (res && res.success) {
                    notify('success', res.message || 'Updated successfully');
                    window.location.reload();
                    return;
                }
                notify('error', (res && res.message) ? res.message : 'Action failed');
            }).fail(function(xhr) {
                $('#detailActionConfirmModal').modal('hide');
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'Action failed';
                notify('error', msg);
            }).always(function() {
                isProcessing = false;
                pendingAction = null;
                $btn.prop('disabled', false);
            });
        });
    });
</script>
@endpush
