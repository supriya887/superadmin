@extends('superadmin.layouts.app')

@push('styles')
<style>
    .credit-kpi-cta-card {
        background: linear-gradient(145deg, #4479cf 0%, #2f63b8 52%, #2558ad 100%);
        border-radius: 14px;
        box-shadow: 0 0.35rem 1rem rgba(37, 88, 173, 0.28);
    }
    .credit-kpi-cta-card .cta-pattern {
        position: absolute;
        inset: 0;
        opacity: 0.14;
        background-image:
            radial-gradient(circle at 18% 28%, #fff 0%, transparent 42%),
            radial-gradient(circle at 88% 72%, #fff 0%, transparent 38%),
            radial-gradient(circle at 70% 15%, rgba(255,255,255,0.5) 0%, transparent 25%);
        pointer-events: none;
        border-radius: inherit;
    }

    #partnerOrdersHistoryTable {
        table-layout: fixed;
        width: 100% !important;
    }

    #partnerOrdersHistoryTable th.domain-col,
    #partnerOrdersHistoryTable td.domain-cell {
        max-width: 12rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #partnerOrdersHistoryTable .domain-text {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
   
</style>
@endpush

@section('content')
<div class="container-fluid mt-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">{{ $title }}</h3>
            <div class="text-muted small">Partner #{{ $partner->id }} · {{ $partner->email }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ url('superadmin/b2b-partners-list') }}" class="btn btn-outline-secondary btn-sm">Back to partners</a>
            <a href="{{ url('superadmin/b2b-partner-edit/' . $partner->id) }}" class="btn btn-outline-primary btn-sm">Edit partner</a>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCreditModal">Increase allocation</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editLimitModal">Set allocation</button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editDiscountModal">Edit discount</button>
        </div>
    </div>

    @php
        $currentDiscountType = strtolower(trim((string) ($partner->partner_discount_type ?? '')));
        $currentDiscountLabel = \App\Support\PartnerDiscountType::label($currentDiscountType);
    @endphp
    <div class="alert alert-light border shadow-sm mb-3 py-2" role="status">
        Discount type: <strong>{{ $currentDiscountLabel ?? '—' }}</strong>
    </div>

    @if(!empty($creditLimitReached))
        <div class="alert alert-warning border-0 shadow-sm mb-3" role="alert">
            <strong>Credit allocation exhausted.</strong> This partner cannot purchase until allocation is increased via <strong>Increase allocation</strong> or <strong>Set allocation</strong>.
            (Consumed: ₹{{ number_format((float) ($kpis['credit_used'] ?? 0), 2) }} / Allocation: ₹{{ number_format((float) ($kpis['credit_limit'] ?? 0), 2) }})
        </div>
    @endif

    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-3 row-cols-xxl-5 g-3 mb-4">
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Credit allocation</div>
                    <div class="fs-4 fw-semibold">₹{{ number_format((float) ($kpis['credit_limit'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Available balance</div>
                    <div class="fs-4 fw-semibold text-success">₹{{ number_format((float) ($kpis['available_post_paid'] ?? 0), 2) }}</div>
                    <div class="text-muted small mt-1">Allocation − consumed (pay invoice does not restore)</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Consumed</div>
                    <div class="fs-4 fw-semibold">₹{{ number_format((float) ($kpis['credit_used'] ?? 0), 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total B2B orders</div>
                    <div class="fs-4 fw-semibold">{{ (int) ($kpis['total_orders'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Completed orders</div>
                    <div class="fs-4 fw-semibold text-success">{{ (int) ($kpis['successful_orders'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        
        
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>Credit usage trends</strong>
            <div class="btn-group btn-group-sm" role="group" aria-label="Trend period">
                <button type="button" class="btn btn-outline-primary active" data-trend="week">Weekly</button>
                <button type="button" class="btn btn-outline-primary" data-trend="month">Monthly</button>
                <button type="button" class="btn btn-outline-primary" data-trend="year">Yearly</button>
            </div>
        </div>
        <div class="card-body">
            <div style="height: 280px; position: relative;">
                <canvas id="creditTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><strong>Allocation change history</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Sr. No.</th>
                            <th>Date</th>
                            <th>Added By</th>
                            <th>Description</th>
                            <th class="text-end">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($creditAddHistory ?? [] as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y, H:i') }}</td>
                                <td>{{ $row->admin_name ?? '—' }}</td>
                                <td>{{ $row->description ?? '—' }}</td>
                                <td class="text-end text-nowrap {{ !empty($row->is_decrease) ? 'text-danger' : 'text-success' }}">
                                    {{ !empty($row->is_decrease) ? '−' : '+' }}₹{{ number_format((float) ($row->amount ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No allocation changes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>Wallet statement (ledger)</strong>
            <span class="text-muted small">
                Current: ₹{{ number_format((float) ($walletCurrentBalance ?? 0), 2) }}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Order No</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($walletStatementRows ?? [] as $row)
                            <tr>
                                <td class="text-nowrap">
                                    {{ !empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d M Y H:i') : '-' }}
                                </td>
                                <td class="text-capitalize">{{ $row['type'] ?? '-' }}</td>
                                <td>{{ $row['description'] ?? '-' }}</td>
                                <td>{{ $row['order_no'] ?? '-' }}</td>
                                <td class="text-end">
                                    ₹{{ number_format((float) ($row['amount'] ?? 0), 2) }}
                                </td>
                                <td class="text-end">
                                    ₹{{ number_format((float) ($row['closing_balance'] ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No wallet transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header"><strong>Order history</strong></div>
        <div class="card-body border-bottom">
            <div class="row g-2 align-items-end" id="partnerOrderFilters">
                <div class="col-md-2">
                    <label for="filter_order_id" class="form-label mb-1">Order ID</label>
                    <input type="text" id="filter_order_id" class="form-control form-control-sm" placeholder="Order id/no">
                </div>
                <div class="col-md-2">
                    <label for="filter_customer_name" class="form-label mb-1">Customer</label>
                    <input type="text" id="filter_customer_name" class="form-control form-control-sm" placeholder="Name/company">
                </div>
                <div class="col-md-2">
                    <label for="filter_domain_name" class="form-label mb-1">Domain</label>
                    <input type="text" id="filter_domain_name" class="form-control form-control-sm" placeholder="example.com">
                </div>
                <div class="col-md-2">
                    <label for="filter_order_status" class="form-label mb-1">Status</label>
                    <select id="filter_order_status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="paid">Paid</option>
                        <option value="active">Active</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_order_type" class="form-label mb-1">Order Type</label>
                    <select id="filter_order_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="b2b">B2B</option>
                        <option value="b2c">B2C</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_sort_by" class="form-label mb-1">Sort by</label>
                    <select id="filter_sort_by" class="form-select form-select-sm">
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="amount_desc">Amount: High to Low</option>
                        <option value="amount_asc">Amount: Low to High</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary w-100" id="applyOrderFilters">Apply</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="resetOrderFilters">Reset</button>
                </div>
                <div class="col-md-2">
                    <label for="filter_from_date" class="form-label mb-1">From</label>
                    <input type="date" id="filter_from_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label for="filter_to_date" class="form-label mb-1">To</label>
                    <input type="date" id="filter_to_date" class="form-control form-control-sm">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle w-100" id="partnerOrdersHistoryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th class="domain-col">Domain</th>
                            <th>Plan</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Credit Used</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCreditModal" tabindex="-1" aria-labelledby="addCreditModalLabel" aria-hidden="true"
         @if($errors->has('topup_amount') || $errors->has('transaction_note')) data-bs-show-on-load="1" @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('superadmin.admin.partners.credit_history.apply', ['id' => $partner->id]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCreditModalLabel">Increase credit allocation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Current allocation:
                            <strong>₹{{ number_format((float) ($kpis['credit_limit'] ?? 0), 2) }}</strong>
                            · Available:
                            <strong>₹{{ number_format((float) ($kpis['available_post_paid'] ?? 0), 2) }}</strong>
                        </p>
                        <p class="text-muted small mb-3">Adds to the allocation total. Paying invoices does not restore available balance.</p>
                        <div class="mb-3">
                            <label for="topup_amount" class="form-label">Amount to add (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="topup_amount" id="topup_amount" required
                                   class="form-control @error('topup_amount') is-invalid @enderror"
                                   value="{{ old('topup_amount') }}"
                                   placeholder="e.g. 50000">
                            @error('topup_amount')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-0">
                            <label for="transaction_note" class="form-label">Note <span class="text-danger">*</span></label>
                            <textarea name="transaction_note" id="transaction_note" rows="3" maxlength="2000" required
                                      class="form-control @error('transaction_note') is-invalid @enderror"
                                      placeholder="Reason for increasing allocation">{{ old('transaction_note') }}</textarea>
                            @error('transaction_note')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editLimitModal" tabindex="-1" aria-labelledby="editLimitModalLabel" aria-hidden="true"
         @if($errors->has('credit_limit') || $errors->has('limit_note')) data-bs-show-on-load="1" @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('superadmin.admin.partners.credit_history.update_limit', ['id' => $partner->id]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="editLimitModalLabel">Set credit allocation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Current allocation: <strong>₹{{ number_format((float) ($kpis['credit_limit'] ?? 0), 2) }}</strong>
                            · Consumed: <strong>₹{{ number_format((float) ($kpis['credit_used'] ?? 0), 2) }}</strong>
                        </p>
                        <div class="mb-3">
                            <label for="edit_credit_limit" class="form-label">New credit allocation (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="credit_limit" id="edit_credit_limit" required
                                   class="form-control @error('credit_limit') is-invalid @enderror"
                                   value="{{ old('credit_limit', $kpis['credit_limit'] ?? 0) }}">
                            <div class="form-text" id="edit_limit_min_hint"></div>
                            @error('credit_limit')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-0">
                            <label for="limit_note" class="form-label">Note <span class="text-danger">*</span></label>
                            <textarea name="limit_note" id="limit_note" rows="3" maxlength="2000" required
                                      class="form-control @error('limit_note') is-invalid @enderror"
                                      placeholder="Reason for allocation change">{{ old('limit_note') }}</textarea>
                            @error('limit_note')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update allocation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editDiscountModal" tabindex="-1" aria-labelledby="editDiscountModalLabel" aria-hidden="true"
         @if($errors->has('partner_discount_type')) data-bs-show-on-load="1" @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="{{ route('superadmin.admin.partners.credit_history.update_discount', ['id' => $partner->id]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDiscountModalLabel">Edit discount type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @php
                            $editDiscountType = strtolower(trim((string) old('partner_discount_type', $partner->partner_discount_type ?? '')));
                            $editDiscountLabel = \App\Support\PartnerDiscountType::label($editDiscountType);
                        @endphp
                        @if($editDiscountLabel)
                            <p class="text-muted small mb-3">
                                Current selected:
                                <strong>{{ $editDiscountLabel }}</strong>
                            </p>
                        @endif
                        <div class="mb-0">
                            <label for="partner_discount_type" class="form-label">Discount type <span class="text-danger">*</span></label>
                            <select name="partner_discount_type" id="partner_discount_type"
                                    class="form-select @error('partner_discount_type') is-invalid @enderror" required>
                                <option value="" disabled {{ $editDiscountType === '' ? 'selected' : '' }}>Select discount type</option>
                                @foreach(\App\Support\PartnerDiscountType::options() as $value => $label)
                                    <option value="{{ $value }}" {{ $editDiscountType === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('partner_discount_type')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save discount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var partnerOrdersListTableUrl = @json(route('superadmin.orders.list_table'));
        var trendData = @json($trends);
        var ctx = document.getElementById('creditTrendChart');
        if (!ctx || typeof Chart === 'undefined') {
            return;
        }
        var gran = 'week';
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendData[gran].labels,
                datasets: [{
                    label: 'Credit usage (₹)',
                    data: trendData[gran].values,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.12)',
                    tension: 0.25,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: true }
                }
            }
        });

        document.querySelectorAll('[data-trend]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                gran = this.getAttribute('data-trend');
                document.querySelectorAll('[data-trend]').forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-trend') === gran);
                });
                var d = trendData[gran];
                chart.data.labels = d.labels;
                chart.data.datasets[0].data = d.values;
                chart.update();
            });
        });

        var addModal = document.getElementById('addCreditModal');
        if (addModal && addModal.getAttribute('data-bs-show-on-load') === '1' && window.bootstrap && window.bootstrap.Modal) {
            new bootstrap.Modal(addModal).show();
        }

        var editLimitModal = document.getElementById('editLimitModal');
        if (editLimitModal && editLimitModal.getAttribute('data-bs-show-on-load') === '1' && window.bootstrap && window.bootstrap.Modal) {
            new bootstrap.Modal(editLimitModal).show();
        }

        var editDiscountModal = document.getElementById('editDiscountModal');
        if (editDiscountModal && editDiscountModal.getAttribute('data-bs-show-on-load') === '1' && window.bootstrap && window.bootstrap.Modal) {
            new bootstrap.Modal(editDiscountModal).show();
        }

        (function () {
            var creditUsed = @json((float) ($kpis['credit_used'] ?? 0));
            var limitInput = document.getElementById('edit_credit_limit');
            var limitHint = document.getElementById('edit_limit_min_hint');

            if (!limitInput) {
                return;
            }

            function formatMoney(n) {
                return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function refreshLimitHint() {
                if (!limitHint) {
                    return;
                }
                limitHint.textContent = creditUsed > 0
                    ? 'Minimum allocation: ' + formatMoney(creditUsed) + ' (already consumed).'
                    : '';
            }

            limitInput.addEventListener('input', refreshLimitHint);
            limitInput.addEventListener('change', refreshLimitHint);
            refreshLimitHint();
        })();

        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            function escHtml(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }

            function renderDomainCell(data, type) {
                var domain = String(data == null ? '' : data).trim();
                if (domain === '' || domain === '-') {
                    return type === 'display' ? '-' : '';
                }
                if (type === 'display') {
                    return '<span class="domain-text" title="' + escHtml(domain) + '">' + escHtml(domain) + '</span>';
                }
                return domain;
            }

            // Keep defaults clean so browser autofill doesn't silently hide rows.
            $('#filter_order_id').val('');
            $('#filter_customer_name').val('');
            $('#filter_domain_name').val('');
            $('#filter_order_status').val('');
            $('#filter_order_type').val('');
            $('#filter_from_date').val('');
            $('#filter_to_date').val('');
            $('#filter_sort_by').val('newest');

            var ordersTable = $('#partnerOrdersHistoryTable').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                ordering: true,
                autoWidth: false,
                ajax: {
                    url: partnerOrdersListTableUrl,
                    type: 'POST',
                    data: function (d) {
                        d._token = $('meta[name="csrf-token"]').attr('content');
                        d.order_type = $('#filter_order_type').val() || '';
                        d.partner_id = @json((int) $partner->id);
                        d.order_id = $.trim($('#filter_order_id').val() || '');
                        d.customer_name = $.trim($('#filter_customer_name').val() || '');
                        d.domain_name = $.trim($('#filter_domain_name').val() || '');
                        d.filter_status = $('#filter_order_status').val() || '';
                        d.from_date = $('#filter_from_date').val() || '';
                        d.to_date = $('#filter_to_date').val() || '';
                        d.sort_by = $('#filter_sort_by').val() || 'newest';
                    }
                },
                order: [[7, 'desc']],
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'customer_name', name: 'customer_name' },
                    {
                        data: 'domain_name',
                        name: 'domain_name',
                        className: 'domain-cell',
                        render: renderDomainCell
                    },
                    { data: 'plan_name', name: 'plan_name' },
                    { data: 'amount', name: 'amount', className: 'text-end text-nowrap' },
                    { data: 'credit_used', name: 'credit_used', className: 'text-end text-nowrap' },
                    { data: 'status', name: 'status' },
                    { data: 'created_at', name: 'created_at', className: 'text-nowrap' }
                ],
                columnDefs: [
                    { targets: '_all', defaultContent: '-' }
                ]
            });

            $('#applyOrderFilters').on('click', function () {
                ordersTable.page('first').ajax.reload(null, true);
            });

            $('#resetOrderFilters').on('click', function () {
                $('#filter_order_id').val('');
                $('#filter_customer_name').val('');
                $('#filter_domain_name').val('');
                $('#filter_order_status').val('');
                $('#filter_order_type').val('');
                $('#filter_from_date').val('');
                $('#filter_to_date').val('');
                $('#filter_sort_by').val('newest');
                ordersTable.page('first').ajax.reload(null, true);
            });

            $('#partnerOrderFilters input').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    ordersTable.page('first').ajax.reload(null, true);
                }
            });

            $('#partnerOrderFilters select').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    ordersTable.page('first').ajax.reload(null, true);
                }
            });
        }
    })();
</script>
@endpush
