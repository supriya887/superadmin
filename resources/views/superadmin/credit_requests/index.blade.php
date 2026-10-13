@extends('superadmin.layouts.app')
@section('content')
{{-- Breadcrumb --}}
<div class="breadcrumb-header d-flex align-items-center">
    <div class="me-auto">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('superadmin.dashboard') }}">Super Admin</a>
                </li>
            </ol>
        </nav>
    </div>
</div>
{{-- Title --}}
<div class="col-12 mt-2">
    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <h4 class="mb-sm-0 font-main fw-bold">Credit Requests</h4>
    </div>
</div>
<div class="container-fluid mt-3">
    <div class="card shadow-sm border-0">
        {{-- Tabs --}}
        <div class="card-body pt-3 pb-0">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a href="{{ route('superadmin.credit_requests.index', ['status' => 'pending']) }}" class="nav-link {{ request('status', 'pending') === 'pending' ? 'active' : '' }}">Pending Requests</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('superadmin.credit_requests.index', ['status' => 'approved']) }}" class="nav-link {{ request('status') === 'approved' ? 'active' : '' }}">Approved Requests</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('superadmin.credit_requests.index', ['status' => 'all']) }}" class="nav-link {{ request('status') === 'all' ? 'active' : '' }}">All Requests</a>
                </li>
            </ul>
        </div>
        {{-- Table --}}
        <div class="card-body p-0">
            @if ($rows->isEmpty())
                <div class="p-4 text-center text-muted">
                    @if(request('status', 'pending') === 'pending')
                        No pending credit requests.
                    @elseif(request('status') === 'approved')
                        No approved credit requests.
                    @else
                        No credit requests found.
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Partner Name</th>
                                <th>Email</th>
                                <th>Credit allocation</th>
                                <th class="text-end">Requested Amount</th>
                                <th>Status</th>
                                @if(request('status', 'pending') === 'pending')
                                    <th class="text-end">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row->id }}</td>
                                    <td>{{ $row->created_at?->format('d M Y h:i A') }}</td>
                                    <td>{{ $row->partner?->name ?? '—' }}</td>
                                    <td>{{ $row->partner?->email ?? '—' }}</td>
                                    <td>₹{{ number_format((float) ($row->partner?->credit_limit ?? 0), 2) }}</td>
                                    <td class="text-end">₹{{ number_format((float) $row->requested_amount, 2) }}</td>
                                    <td>
                                        @if($row->status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @elseif($row->status === 'rejected')
                                            <span class="badge bg-danger">Rejected</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @endif
                                    </td>
                                    @if(request('status', 'pending') === 'pending')
                                        <td class="text-end">
                                            @if($row->status === 'pending')
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button"
                                                            class="btn btn-success btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#approveCreditRequestModal"
                                                            data-request-id="{{ $row->id }}"
                                                            data-partner-name="{{ e($row->partner?->name ?? 'Partner') }}"
                                                            data-requested-amount="{{ number_format((float) $row->requested_amount, 2, '.', '') }}"
                                                            data-current-allocation="{{ number_format((float) ($row->partner?->credit_limit ?? 0), 2, '.', '') }}"
                                                            data-approve-url="{{ route('superadmin.credit_requests.approve', $row->id) }}">
                                                        Approve
                                                    </button>
                                                    <form action="{{ route('superadmin.credit_requests.reject', $row->id) }}" method="POST" onsubmit="return confirm('Reject credit request #{{ $row->id }} ?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
      </div>
    </div>
</div>

<div class="modal fade" id="approveCreditRequestModal" tabindex="-1" aria-labelledby="approveCreditRequestModalLabel" aria-hidden="true"
     @if($errors->has('approved_amount') || $errors->has('admin_note')) data-bs-show-on-load="1" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="approveCreditRequestForm" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="approveCreditRequestModalLabel">Approve credit allocation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="approveCreditRequestMeta">
                        Enter the amount to add to this partner’s credit allocation.
                    </p>
                    <div class="mb-3">
                        <label for="approved_amount" class="form-label">Amount to add (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="approved_amount" id="approved_amount" required
                               class="form-control @error('approved_amount') is-invalid @enderror"
                               value="{{ old('approved_amount') }}"
                               placeholder="e.g. 50000">
                        <div class="form-text" id="approveCreditRequestHint"></div>
                        @error('approved_amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-0">
                        <label for="admin_note" class="form-label">Admin note <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="admin_note" id="admin_note" rows="3" maxlength="500"
                                  class="form-control @error('admin_note') is-invalid @enderror"
                                  placeholder="Reason / CIBIL review note">{{ old('admin_note') }}</textarea>
                        @error('admin_note')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve &amp; add allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }
        fn();
    }

    function formatMoney(n) {
        var x = Number(n || 0);
        return '₹' + x.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    onReady(function () {
        var modalEl = document.getElementById('approveCreditRequestModal');
        if (!modalEl) return;

        var form = document.getElementById('approveCreditRequestForm');
        var amountInput = document.getElementById('approved_amount');
        var meta = document.getElementById('approveCreditRequestMeta');
        var hint = document.getElementById('approveCreditRequestHint');

        modalEl.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn || !form) return;

            var url = btn.getAttribute('data-approve-url') || '#';
            var partnerName = btn.getAttribute('data-partner-name') || 'Partner';
            var requested = btn.getAttribute('data-requested-amount') || '';
            var current = btn.getAttribute('data-current-allocation') || '0';
            var requestId = btn.getAttribute('data-request-id') || '';

            form.setAttribute('action', url);
            if (amountInput) {
                amountInput.value = requested;
            }
            if (meta) {
                meta.innerHTML = 'Request <strong>#' + requestId + '</strong> · '
                    + partnerName + '<br>Current allocation: <strong>' + formatMoney(current) + '</strong>'
                    + ' · Requested: <strong>' + formatMoney(requested) + '</strong>';
            }
            if (hint) {
                hint.textContent = 'You can approve a different amount than requested. This value is added to credit allocation.';
            }
        });

        if (modalEl.getAttribute('data-bs-show-on-load') === '1' && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
})();
</script>
@endpush
