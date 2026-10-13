@extends('superadmin.layouts.app')

@push('styles')
<style>
    /* Ensure Bootstrap modal is always above the backdrop in this layout. */
    #approvePartnerModal,
    #rejectPartnerModal {
        z-index: 1060;
    }

    .modal-backdrop {
        z-index: 1050;
    }
</style>
@endpush

@section('content')
<div class="container-fluid mt-2">
    @php
        $gstin = $partner->gstin ?? null;
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h3 class="mb-0">Partner request</h3>
            <div class="text-muted small">ID #{{ $partner->id }} · Registered {{ $partner->created_at?->format('d-m-Y H:i') }}</div>
        </div>
        <a href="{{ route('superadmin.partner_requests.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header"><strong>Profile</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted small">Partner name</div><div class="fw-medium">{{ $partner->first_name ?? $partner->name }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Email</div><div class="fw-medium">{{ $partner->email }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Mobile</div><div class="fw-medium">{{ $partner->mobile }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Company</div><div class="fw-medium">{{ $partner->company_name ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Legal name</div><div class="fw-medium">{{ $partner->legal_name ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Trade name</div><div class="fw-medium">{{ $partner->trade_name ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Address</div><div class="fw-medium">{{ $partner->company_address ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">GST address</div><div class="fw-medium">{{ $partner->address ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">GSTIN</div><div class="fw-medium">{{ $gstin ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">PAN</div><div class="fw-medium">{{ $partner->pan_no ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Email verified</div><div class="fw-medium">{{ (int) ($partner->email_verified ?? 0) === 1 ? 'Yes' : 'No' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Country</div><div class="fw-medium">{{ $partner->country ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">State</div><div class="fw-medium">{{ $partner->state ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">City</div><div class="fw-medium">{{ $partner->city ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Postcode</div><div class="fw-medium">{{ $partner->postcode ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Pincode</div><div class="fw-medium">{{ $partner->pincode ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">GST status</div><div class="fw-medium">{{ $partner->gst_status ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Constitution</div><div class="fw-medium">{{ $partner->constitution ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Registration date</div><div class="fw-medium">{{ $partner->registration_date ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Last updated (GST)</div><div class="fw-medium">{{ $partner->last_updated ?? '—' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Partner status</div><div class="fw-medium">{{ $partner->status ?? '—' }}</div></div>
                @php
                    $discountTypeLabel = \App\Support\PartnerDiscountType::label($partner->partner_discount_type ?? '');
                @endphp
                <div class="col-md-4">
                    <div class="text-muted small">Discount type</div>
                    <div class="fw-medium">{{ $discountTypeLabel ?? '—' }}</div>
                </div>
                <div class="col-md-12"><div class="text-muted small">Nature of business</div><div class="fw-medium">{{ $partner->nature_of_business ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    @php
        $partnerTable = (new \App\Models\Partner())->getTable();
        $partnerConnection = (new \App\Models\Partner())->getConnectionName();
        $partnerSchema = \Illuminate\Support\Facades\Schema::connection($partnerConnection);
        $hasCreditLimit = $partnerSchema->hasTable($partnerTable)
            && $partnerSchema->hasColumn($partnerTable, 'credit_limit');
        $postPaidHeadroom = $hasCreditLimit ? max(0.0, (float) ($partner->credit_limit ?? 0) - (float) $partner->used_credit) : 0.0;
    @endphp

    @if($hasCreditLimit && !empty($partnerApproved))
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong>Credit allocation</strong>
                <span class="text-muted small">One purchase quota. Paying invoices does not restore available balance.</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="text-muted small">Credit allocation</div>
                        <div class="fw-semibold">₹{{ number_format((float) ($partner->credit_limit ?? 0), 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Consumed</div>
                        <div class="fw-semibold">₹{{ number_format((float) $partner->used_credit, 2) }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Available balance</div>
                        <div class="fw-semibold text-success">₹{{ number_format($postPaidHeadroom, 2) }}</div>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small mb-2">Allocation change history</h6>
                <p class="text-muted small">Admin increases / changes to credit allocation.</p>
                <div class="table-responsive border rounded mb-4">
                    <table class="table table-sm table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Change</th>
                                <th>Admin</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($manualCreditHistory ?? [] as $row)
                                <tr>
                                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y, H:i') }}</td>
                                    <td class="text-nowrap {{ !empty($row->is_decrease) ? 'text-danger' : 'text-success' }}">
                                        {{ !empty($row->is_decrease) ? '−' : '+' }}₹{{ number_format((float) $row->amount, 2) }}
                                    </td>
                                    <td class="text-nowrap">{{ $row->admin_name ?? '—' }}</td>
                                    <td>{{ $row->description }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No allocation changes yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    @endif

    @if(!empty($partnerPending))
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header"><strong>Decision</strong></div>
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approvePartnerModal">Approve</button>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectPartnerModal">Reject</button>
            </div>
        </div>
    @elseif(!empty($partnerRejected))
        <div class="alert alert-secondary mb-3">This partner application was <strong>rejected</strong>.</div>
    @endif

    @if(!empty($partnerPending))
        <div class="modal fade" id="approvePartnerModal" tabindex="-1" aria-labelledby="approvePartnerModalLabel" aria-hidden="true"
             @if($errors->has('approval_credit_limit') || $errors->has('approval_wallet_credit') || $errors->has('partner_discount_type')) data-bs-show-on-load="1" @endif>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('superadmin.partner_requests.approve', $partner->id) }}" id="approvePartnerForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="approvePartnerModalLabel">Approve partner</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @php
                                $selectedDiscountType = strtolower(trim((string) old('partner_discount_type', $partner->partner_discount_type ?? '')));
                            @endphp
                            <div class="mb-3">
                                <label for="partner_discount_type" class="form-label">Discount type <span class="text-danger">*</span></label>
                                <select name="partner_discount_type" id="partner_discount_type"
                                        class="form-select @error('partner_discount_type') is-invalid @enderror" required>
                                    <option value="" disabled {{ $selectedDiscountType === '' || $selectedDiscountType === null ? 'selected' : '' }}>Select discount type</option>
                                    @foreach(\App\Support\PartnerDiscountType::options() as $value => $label)
                                        <option value="{{ $value }}" {{ $selectedDiscountType === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('partner_discount_type')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <p class="text-muted small">Optional. Sets the partner’s <strong>credit allocation</strong> (purchase quota). Leave blank or <strong>0</strong> to approve without initial allocation. Paying invoices later does not restore available balance.</p>
                            <div class="mb-0">
                                <label for="approval_wallet_credit" class="form-label">Credit allocation (₹) <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="number" step="0.01" min="0" name="approval_wallet_credit" id="approval_wallet_credit"
                                       class="form-control @error('approval_wallet_credit') is-invalid @enderror"
                                       value="{{ old('approval_wallet_credit', old('approval_credit_limit', '')) }}"
                                       placeholder="e.g. 100000">
                                <input type="hidden" name="approval_credit_limit" id="approval_credit_limit"
                                       value="{{ old('approval_credit_limit', old('approval_wallet_credit', '0')) }}">
                                @error('approval_wallet_credit')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('approval_credit_limit')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Save &amp; approve</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="rejectPartnerModal" tabindex="-1" aria-labelledby="rejectPartnerModalLabel" aria-hidden="true" @if($errors->has('rejection_reason')) data-bs-show-on-load="1" @endif>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('superadmin.partner_requests.reject', $partner->id) }}" id="rejectPartnerForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectPartnerModalLabel">Reject partner</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="rejection_reason" class="form-label">Rejection reason</label>
                            <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="4" placeholder="Explain why this application is being rejected (optional)">{{ old('rejection_reason') }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Submit rejection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
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

        function showModalIfRequested(id) {
            var modalEl = document.getElementById(id);
            if (!modalEl || modalEl.getAttribute('data-bs-show-on-load') !== '1') {
                return;
            }
            if (!window.bootstrap || !window.bootstrap.Modal) {
                return;
            }
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        onReady(function () {
        @if(session('info'))
            if (typeof alertify !== 'undefined') {
                alertify.message(@json(session('info')));
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: @json(session('info')), toast: true, position: 'top-end', showConfirmButton: false, timer: 5000 });
            }
        @endif
        @if(session('warning'))
            if (typeof alertify !== 'undefined') {
                alertify.notify(@json(session('warning')), 'warning', 8);
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: @json(session('warning')), toast: true, position: 'top-end', showConfirmButton: false, timer: 7000 });
            }
        @endif
        @if($errors->has('credit_limit'))
            if (typeof alertify !== 'undefined') {
                alertify.error(@json($errors->first('credit_limit')));
            }
        @endif
        @if($errors->has('manual_credit_amount') || $errors->has('manual_credit_note'))
            if (typeof alertify !== 'undefined') {
                @if($errors->has('manual_credit_amount'))
                    alertify.error(@json($errors->first('manual_credit_amount')));
                @endif
                @if($errors->has('manual_credit_note'))
                    alertify.error(@json($errors->first('manual_credit_note')));
                @endif
            }
        @endif
        @if($errors->has('approval_credit_limit') || $errors->has('approval_wallet_credit') || $errors->has('partner_discount_type'))
            if (typeof alertify !== 'undefined') {
                @if($errors->has('approval_credit_limit'))
                    alertify.error(@json($errors->first('approval_credit_limit')));
                @endif
                @if($errors->has('approval_wallet_credit'))
                    alertify.error(@json($errors->first('approval_wallet_credit')));
                @endif
                @if($errors->has('partner_discount_type'))
                    alertify.error(@json($errors->first('partner_discount_type')));
                @endif
            }
        @endif

        // Some layouts load bootstrap after stacked scripts; retry briefly.
        var tries = 0;
        var maxTries = 20;
        var retryMs = 100;
        (function tryShowQueuedModals() {
            showModalIfRequested('rejectPartnerModal');
            showModalIfRequested('approvePartnerModal');
            if (window.bootstrap && window.bootstrap.Modal) {
                return;
            }
            tries += 1;
            if (tries < maxTries) {
                window.setTimeout(tryShowQueuedModals, retryMs);
            }
        })();

        var rejectForm = document.getElementById('rejectPartnerForm');
        if (rejectForm && typeof Swal !== 'undefined') {
            rejectForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var reason = (rejectForm.querySelector('[name="rejection_reason"]') || {}).value || '';
                Swal.fire({
                    title: 'Send rejection?',
                    html: reason.trim()
                        ? 'The partner will be rejected and a rejection email will be sent with your reason.'
                        : 'The partner will be rejected and a rejection email will be sent (no reason text).',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, reject',
                    cancelButtonText: 'Back'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        HTMLFormElement.prototype.submit.call(rejectForm);
                    }
                });
            });
        }

        var approveForm = document.getElementById('approvePartnerForm');
        if (approveForm) {
            var walletCreditInput = document.getElementById('approval_wallet_credit');
            var creditLimitInput = document.getElementById('approval_credit_limit');
            var syncApprovalAmounts = function () {
                if (walletCreditInput && creditLimitInput) {
                    creditLimitInput.value = walletCreditInput.value;
                }
            };

            if (walletCreditInput) {
                walletCreditInput.addEventListener('input', syncApprovalAmounts);
                walletCreditInput.addEventListener('change', syncApprovalAmounts);
            }

            approveForm.addEventListener('submit', syncApprovalAmounts);
            syncApprovalAmounts();
        }
        });
    })();
</script>
@endpush
