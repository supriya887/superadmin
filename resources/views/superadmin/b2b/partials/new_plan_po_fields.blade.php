@php
    $po = $wizardPoPrefill ?? [];
    $poReadonly = !empty($poReadonly);
    $summary = $wizardOrderSummary ?? null;
    $raw = static fn (string $key): string => trim((string) ($po[$key] ?? ''));
    $val = static fn (string $key, string $default = '') => $raw($key) !== '' ? $raw($key) : $default;
@endphp

@if (!empty($summary))
    <div class="border rounded-3 p-3 mb-3 bg-light-subtle">
        <h6 class="mb-2 text-primary">Order details</h6>
        <table class="table table-bordered table-sm mb-0 border-0">
            <tbody>
                @if (!empty($summary['order_no']))
                    <tr>
                        <th class="text-muted w-25">Order No.</th>
                        <td class="fw-semibold">{{ $summary['order_no'] }}</td>
                    </tr>
                @endif
                <tr>
                    <th class="text-muted">Status</th>
                    <td class="fw-semibold text-capitalize">{{ $summary['status'] ?? 'pending' }}</td>
                </tr>
                <tr>
                    <th class="text-muted">Partner</th>
                    <td class="fw-semibold">{{ $summary['partner_name'] ?? '-' }}</td>
                </tr>
                <tr>
                    <th class="text-muted">Domain</th>
                    <td class="fw-semibold">{{ $summary['domain_name'] ?? '-' }}</td>
                </tr>
                @if (!empty($summary['plan_lines']))
                    <tr>
                        <th class="text-muted align-top">Plans</th>
                        <td>
                            <ul class="mb-0 ps-3">
                                @foreach ($summary['plan_lines'] as $line)
                                    <li>
                                        {{ $line['plan_name'] ?: 'Plan #' . ($line['order_id'] ?? '') }}
                                        — Qty {{ (int) ($line['quantity'] ?? 1) }}
                                        @if (!empty($line['unit_price']))
                                            (₹{{ number_format((float) $line['unit_price'], 2) }}/unit)
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
                @if (!empty($summary['total_amount']))
                    <tr>
                        <th class="text-muted">Amount</th>
                        <td class="fw-semibold">₹{{ number_format((float) $summary['total_amount'], 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
@endif

<h6 class="mb-2">Customer / PO details</h6>
@if (!$poReadonly)
    <p class="small text-muted mb-3">Open this tab to load customer details. Use Search on GSTIN, then click Submit to save PO.</p>
@endif

@if ($poReadonly)
    <input type="hidden" name="po_mode" value="generated">
    <div class="row g-2">
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Customer ID</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('customer_id', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Name</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('name', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Email</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('email', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Mobile</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('mobile', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">GSTIN</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('gstin', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">PAN</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('pan_no', '-')) }}" readonly>
        </div>
        <div class="col-12 mb-2">
            <label class="form-label small text-muted mb-1">Company</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('company', '-')) }}" readonly>
        </div>
        <div class="col-12 mb-2">
            <label class="form-label small text-muted mb-1">Address</label>
            <textarea class="form-control form-control-sm bg-light" rows="2" readonly>{{ $val('address') }}</textarea>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">City</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('city', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">State</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('state', '-')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Pincode</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('pincode', '-')) }}" readonly>
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label small text-muted mb-1">Country</label>
            <input class="form-control form-control-sm bg-light" value="{{ e($val('country', 'India')) }}" readonly>
        </div>
    </div>
@else
    <div class="col-12 mb-3">
        <label class="form-label small fw-semibold mb-1">PO Source</label>
        <div class="d-flex flex-wrap gap-3 mb-1">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="po_mode_choice" id="po_mode_generated" value="generated" checked>
                <label class="form-check-label" for="po_mode_generated">Generate PO</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="po_mode_choice" id="po_mode_uploaded" value="uploaded">
                <label class="form-check-label" for="po_mode_uploaded">Upload PO (CRM / SAP)</label>
            </div>
        </div>
        <small class="text-muted d-block mb-2">Upload your own PDF if you prefer CRM/SAP format over system PO + Aadhaar.</small>
        <input type="hidden" name="po_mode" id="po_mode" value="generated">
    </div>
    <div class="row g-2 d-none mb-3" id="po_upload_fields">
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">External PO Number</label>
            <input class="form-control form-control-sm" name="po_number" id="po_number_upload" placeholder="From CRM / SAP">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">PO Date</label>
            <input class="form-control form-control-sm" type="date" name="po_date" id="po_date_upload">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Upload PO PDF</label>
            <input class="form-control form-control-sm" type="file" name="po_document" id="po_document" accept="application/pdf,.pdf">
        </div>
    </div>
    <div class="row g-2" id="po_fields_wrap">
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Customer ID</label>
            <input class="form-control form-control-sm bg-light" id="po_customer_id" value="{{ e($val('customer_id')) }}" readonly>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Name</label>
            <input class="form-control form-control-sm" name="po_name" id="po_name" value="{{ e($val('name')) }}">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Email</label>
            <input class="form-control form-control-sm" name="po_email" id="po_email" type="email" value="{{ e($val('email')) }}">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Mobile</label>
            <input class="form-control form-control-sm" name="po_mobile" id="po_mobile" value="{{ e($val('mobile')) }}">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">GSTIN</label>
            <div class="input-group input-group-sm">
                <input class="form-control" name="po_gstin" id="po_gstin" value="{{ e($val('gstin')) }}" maxlength="15" placeholder="15-character GSTIN">
                <button type="button" class="btn btn-outline-secondary" id="po_gstin_search_btn">Search</button>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">PAN</label>
            <input class="form-control form-control-sm bg-light" name="po_pan_no" id="po_pan_no" value="{{ e($val('pan_no')) }}" readonly>
        </div>
        <div class="col-12 mb-2">
            <label class="form-label small text-muted mb-1">Company</label>
            <input class="form-control form-control-sm" name="po_company_name" id="po_company_name" value="{{ e($val('company')) }}">
        </div>
        <div class="col-12 mb-2">
            <label class="form-label small text-muted mb-1">Address</label>
            <textarea class="form-control form-control-sm" name="po_company_address" id="po_company_address" rows="2">{{ $val('address') }}</textarea>
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">City</label>
            <input class="form-control form-control-sm" name="po_city" id="po_city" value="{{ e($val('city')) }}">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">State</label>
            <input class="form-control form-control-sm" name="po_state" id="po_state" value="{{ e($val('state')) }}">
        </div>
        <div class="col-md-4 mb-2">
            <label class="form-label small text-muted mb-1">Pincode</label>
            <input class="form-control form-control-sm" name="po_pincode" id="po_pincode" value="{{ e($val('pincode')) }}">
        </div>
        <div class="col-md-6 mb-2">
            <label class="form-label small text-muted mb-1">Country</label>
            <input class="form-control form-control-sm" name="po_country" id="po_country" value="{{ e($val('country', 'India')) }}">
        </div>
    </div>
@endif
