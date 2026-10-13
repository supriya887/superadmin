@extends('superadmin.layouts.app')

@push('styles')
<style>
    /* Ensure Bootstrap modal is always above the backdrop in this layout. */
    #approveRequestModal,
    #rejectRequestModal {
        z-index: 1060;
    }

    .modal-backdrop {
        z-index: 1050;
    }
</style>
@endpush

@section('content')
<div class="container-fluid mt-2">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h3 class="mb-0">Purchase Request Details</h3>
            <div class="text-muted small">ID #{{ $purchaseRequest->id }} · Created {{ $purchaseRequest->created_at?->format('d-m-Y H:i') }}</div>
        </div>
        <a href="{{ route('superadmin.purchase_requests.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
    </div>

    <div class="row">
        <!-- Main Details Column -->
        <div class="col-lg-12">
            <!-- Request details -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-light"><strong>Request Information</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Request Type</div>
                            <div class="fw-semibold text-capitalize">{{ $purchaseRequest->request_type ?? 'Purchase' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Licenses / Quantity</div>
                            <div class="fw-semibold">{{ $purchaseRequest->licenses ?? '—' }}</div>
                        </div>
                        @if($purchaseRequest->order && $purchaseRequest->order->plan)
                            <div class="col-md-6">
                                <div class="text-muted small">Current Plan</div>
                                <div class="fw-semibold text-secondary">{{ $purchaseRequest->order->plan->name }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Requested Plan</div>
                                <div class="fw-semibold text-primary">{{ $purchaseRequest->plan->name ?? '—' }}</div>
                            </div>
                        @else
                            <div class="col-md-6">
                                <div class="text-muted small">Requested Plan</div>
                                <div class="fw-semibold text-primary">{{ $purchaseRequest->plan->name ?? '—' }}</div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <div class="text-muted small">Status</div>
                            <div>
                                @php
                                    $statusVal = strtolower(trim((string) $purchaseRequest->status));
                                    $label = 'Unknown';
                                    $cls = 'bg-secondary';
                                    if ($statusVal === 'pending') {
                                        $label = 'Pending';
                                        $cls = 'bg-warning text-dark';
                                    } elseif ($statusVal === 'approved') {
                                        $label = 'Approved';
                                        $cls = 'bg-success text-white';
                                    } elseif ($statusVal === 'rejected') {
                                        $label = 'Rejected';
                                        $cls = 'bg-danger text-white';
                                    }
                                @endphp
                                <span class="badge {{ $cls }}">{{ $label }}</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="text-muted small">Remarks (from Partner)</div>
                            <div class="fw-medium text-dark bg-light p-2 rounded" style="white-space: pre-wrap;">{{ $purchaseRequest->remarks ?? '—' }}</div>
                        </div>
                        @if($purchaseRequest->admin_notes)
                            <div class="col-md-12">
                                <div class="text-muted small">Admin Notes</div>
                                <div class="fw-medium text-dark bg-light p-2 rounded" style="white-space: pre-wrap;">{{ $purchaseRequest->admin_notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <br>
            <!-- Partner details -->
            <div class="card shadow-sm border-0 mb-3 mt-2">
                <div class="card-header bg-light"><strong>Partner Profile</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Partner Name</div>
                            <div class="fw-medium">{{ $purchaseRequest->partner->name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Company Name</div>
                            <div class="fw-medium">{{ $purchaseRequest->partner->company_name ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Email Address</div>
                            <div class="fw-medium">{{ $purchaseRequest->partner->email ?? '—' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Mobile Number</div>
                            <div class="fw-medium">{{ $purchaseRequest->partner->mobile ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <br>
            <!-- Customer details -->
            @if($purchaseRequest->customer)
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-light"><strong>Customer Profile</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Customer Name</div>
                                <div class="fw-medium">{{ $purchaseRequest->customer->name ?? '—' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Email Address</div>
                                <div class="fw-medium">{{ $purchaseRequest->customer->email ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            <br>
            <div class="card mt-2">
                <div class="card-body">
                    @if($purchaseRequest->status === 'pending')
                        <div class="d-flex gap-2">
                            <div>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveRequestModal">
                                    <i class="bi bi-check-circle me-1"></i> Accept &amp; Approve
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectRequestModal">
                                    <i class="bi bi-x-circle me-1"></i> Reject Request
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-3">
                            <p class="text-muted mb-0">This request has already been processed.</p>
                            <div class="mt-2 fw-semibold text-capitalize text-{{ $purchaseRequest->status === 'approved' ? 'success' : 'danger' }}">
                                Status: {{ $purchaseRequest->status }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>     
        </div>
            <br>
        <!-- Sidebar Actions Column -->
        
    </div>
</div>

@if($purchaseRequest->status === 'pending')
    <!-- Approve Modal -->
    <div class="modal fade" id="approveRequestModal" tabindex="-1" aria-labelledby="approveRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('superadmin.purchase_requests.approve', $purchaseRequest->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveRequestModalLabel">Approve Purchase Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Approving this request will proceed with ordering/upgrading process. You can optionally add notes for the partner.</p>
                        <div class="mb-3">
                            <label for="approve_admin_notes" class="form-label font-semibold text-sm">Admin Notes (Optional)</label>
                            <textarea id="approve_admin_notes" name="admin_notes" class="form-control" rows="4" placeholder="Enter notes to share with the partner..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectRequestModal" tabindex="-1" aria-labelledby="rejectRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('superadmin.purchase_requests.reject', $purchaseRequest->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectRequestModalLabel">Reject Purchase Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Please explain why this purchase request is being rejected. This note will be recorded.</p>
                        <div class="mb-3">
                            <label for="reject_admin_notes" class="form-label font-semibold text-sm">Rejection Reason / Admin Notes (Optional)</label>
                            <textarea id="reject_admin_notes" name="admin_notes" class="form-control" rows="4" placeholder="Explain the reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@endsection
