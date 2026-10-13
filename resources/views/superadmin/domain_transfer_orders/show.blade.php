@extends('superadmin.layouts.app')

@section('content')
    <div class="row">
        <div class="col-12 mt-2">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Domain Transfer Order Details</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('superadmin.domain_transfer_orders.index') }}">Domain Transfer Orders</a>
                    </li>
                    <li class="breadcrumb-item active">Order #{{ $request->order_no ?? $request->id ?? '-' }}</li>
                </ol>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    {{-- Status and Timestamps --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        @php
                            $detailStatus = strtolower(trim((string) ($request->resolved_status ?? $request->status ?? 'pending')));
                            if ($detailStatus === '') {
                                $detailStatus = 'pending';
                            }
                            $detailStatusOk = in_array($detailStatus, ['completed', 'paid', 'success', 'active', 'approved'], true);
                        @endphp
                        <div>
                            <span class="text-muted me-2">Status:</span>
                            <span class="badge {{ $detailStatusOk ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($detailStatus) }}</span>
                        </div>
                        <div class="small text-muted">
                            <span class="me-3">
                                <strong>Created On :</strong>
                                {{ !empty($request->created_at) ? \Illuminate\Support\Carbon::parse($request->created_at)->format('d M Y') : '-' }}
                            </span>
                            <span>
                                <strong>Last Updated :</strong>
                                {{ !empty($request->updated_at) ? \Illuminate\Support\Carbon::parse($request->updated_at)->format('d M Y') : '-' }}
                            </span>
                        </div>
                    </div>

                    {{-- Customer Information --}}
                    <div class="row g-3 align-items-start">
                        <div class="col-lg-4 border-end">
                            <div class="fw-bold fs-5">{{ optional($request->customer)->name ?? '-' }}</div>
                            <div class="text-muted small mb-2">
                                Customer Id : {{ str_pad((string) ($request->customer_id ?? 0), 5, '0', STR_PAD_LEFT) }}
                            </div>
                            <div class="small text-muted">Email Id</div>
                            <div class="mb-2">{{ optional($request->customer)->email ?? '-' }}</div>
                            <div class="small text-muted">Mobile No.</div>
                            <div>{{ optional($request->customer)->mobile ?? '-' }}</div>
                        </div>
                        <div class="col-lg-4 border-end">
                            <div class="small text-muted">Company Name</div>
                            <div class="mb-2">{{ optional($request->customer)->company_name ?? '-' }}</div>
                            <div class="small text-muted">Address</div>
                            <div>{{ optional($request->customer)->billing_address ?? '-' }}</div>
                        </div>
                        <div class="col-lg-4">
                            <div class="small text-muted">Contact No.</div>
                            <div class="mb-2">{{ optional($request->customer)->mobile ?? '-' }}</div>
                            <div class="small text-muted">PAN No.</div>
                            <div class="mb-2">{{ optional($request->customer)->pan ?? '-' }}</div>
                            <div class="small text-muted">GSTIN</div>
                            <div>{{ optional($request->customer)->gstin ?? '-' }}</div>
                        </div>
                    </div>

                    <hr>

                    {{-- Order Summary Table --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0 w-100">
                            <tbody>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:25%;">Plan</th>
                                    <th>Domain name</th>
                                    <th>Order</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Credit Used</th>
                                </tr>
                                @php
                                    $domains = [];
                                    if (!empty($request->domain_name)) {
                                        $domains = is_array($request->domain_name)
                                            ? $request->domain_name
                                            : preg_split('/\s*,\s*/', (string) $request->domain_name);
                                    }
                                    $domains = array_values(array_filter($domains, fn($d) => trim($d) !== ''));
                                    $domainCount = count($domains);
                                @endphp
                                <tr>
                                    <td>1</td>
                                    <td>{{ optional($request->plan)->name ?? '-' }}</td>
                                    <td>
                                        @if($domainCount > 0)
                                            <ol class="mb-0 ps-3">
                                                @foreach($domains as $domIdx => $domain)
                                                    <li>{{ $domain }}</li>
                                                @endforeach
                                            </ol>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>#{{ $request->order_no ?? $request->id ?? '-' }}</td>
                                    <td>{{ strtoupper((string) ($request->order_type ?? 'b2b')) }}</td>
                                    <td>₹{{ number_format((float) ($request->total_amount ?? 0), 2) }}</td>
                                    <td>₹{{ number_format((float) ($request->credit_used ?? 0), 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- PO Journey Section (Dynamic) --}}
                    @php
                        $hasPoData = !empty($request->po_mode ?? null) || !empty($request->po_number ?? null) || !empty($request->po_document_url ?? null) || !empty($request->po_journey_status ?? null);
                        $journey = strtolower((string) ($request->po_journey_status ?? ''));
                        $isB2bOrder = strtolower((string) ($request->order_type ?? 'b2b')) === 'b2b';
                    @endphp
                    @if($isB2bOrder || $hasPoData)
                        <div class="card border mt-3">
                            <div class="card-body py-3">
                                <h6 class="mb-3">PO Journey</h6>
                                @php
                                    $aadhaarVerified = (int) ($request->aadhar_verified ?? 0) === 1;
                                    $statusCompleted = in_array($detailStatus, ['completed', 'paid', 'success', 'active', 'approved'], true);
                                    $forcePlaced = $aadhaarVerified || $statusCompleted;
                                    $poDone = $forcePlaced || in_array($journey, ['po_done', 'aadhar_done', 'otp_done', 'placed'], true);
                                    $aadhaarDone = $forcePlaced || in_array($journey, ['aadhar_done', 'otp_done', 'placed'], true);
                                    $otpDone = $forcePlaced || in_array($journey, ['otp_done', 'placed'], true);
                                    $placedDone = $forcePlaced || $journey === 'placed';
                                    $steps = [
                                        ['label' => 'PO', 'done' => $poDone],
                                        ['label' => 'Aadhar', 'done' => $aadhaarDone],
                                        ['label' => 'OTP', 'done' => $otpDone],
                                        ['label' => 'Placed', 'done' => $placedDone],
                                    ];
                                @endphp
                                <div class="mb-3">
                                    <div class="position-relative px-1">
                                        <div class="position-absolute start-0 end-0" style="top:12px;height:2px;background:#d7deea;"></div>
                                        <div class="d-flex justify-content-between position-relative">
                                            @foreach($steps as $step)
                                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                                                      style="width:24px;height:24px;font-size:13px;font-weight:700;
                                                        background:{{ $step['done'] ? '#0b5cad' : '#c9d3e5' }};
                                                        color:{{ $step['done'] ? '#fff' : '#6b7280' }};">
                                                    {{ $step['done'] ? '✓' : '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 px-1">
                                        @foreach($steps as $step)
                                            <span style="font-size:12px;font-weight:600;color:{{ $step['done'] ? '#334155' : '#94a3b8' }};">
                                                {{ $step['label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-md-3"><strong>PO Mode:</strong> {{ ucfirst((string) ($request->po_mode ?? '-')) }}</div>
                                    <div class="col-md-3"><strong>PO Number:</strong> {{ $request->po_number ?? '-' }}</div>
                                    <div class="col-md-3"><strong>PO Date:</strong> {{ !empty($request->po_date) ? \Illuminate\Support\Carbon::parse($request->po_date)->format('d M Y') : '-' }}</div>
                                    <div class="col-md-3"><strong>PO Amount:</strong> ₹{{ number_format((float) ($request->po_amount ?? 0), 2) }}</div>
                                    <div class="col-md-6"><strong>Journey Status:</strong> {{ $placedDone ? 'Placed' : ucwords(str_replace('_', ' ', (string) ($request->po_journey_status ?? 'po_pending'))) }}</div>
                                    <div class="col-md-6"><strong>PO Document:</strong>
                                        @if(!empty($request->po_document_url))
                                            <a href="{{ $request->po_document_url }}" target="_blank" rel="noopener"
                                               class="btn btn-sm rounded-pill px-3 ms-2"
                                               style="background:#1e58b4;color:#fff;">
                                                Show PO Document
                                            </a>
                                        @else
                                            <span class="ms-2">-</span>
                                        @endif
                                    </div>
                                    <div class="col-md-6"><strong>Aadhaar Verified:</strong>
                                        @if((int) ($request->aadhar_verified ?? 0) === 1)
                                            <span class="badge bg-success">Yes</span>
                                            @if(!empty($request->aadhar_verified_at))
                                                <span class="ms-2">{{ \Illuminate\Support\Carbon::parse($request->aadhar_verified_at)->format('d M Y, h:i A') }}</span>
                                            @endif
                                        @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @endif
                                    </div>
                                    <div class="col-md-6"><strong>Aadhaar Ref:</strong> {{ $request->aadhaar_ref_no ?? '-' }}</div>
                                    <div class="col-md-6"><strong>Aadhaar No:</strong> {{ $request->aadhaar_masked ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Order Items Table (Dynamic) --}}
                    @php
                        // Build order items dynamically from available data
                        $invoiceItems = [];
                        $planName = optional($request->plan)->name ?? ($request->plan_name ?? '-');
                        $amount = (float) ($request->total_amount ?? 0);
                        $creditUsed = (float) ($request->credit_used ?? 0);
                        $netAmount = $amount - $creditUsed;
                        
                        // Main item from plan
                        if ($planName !== '-') {
                            $invoiceItems[] = [
                                'name' => $planName,
                                'description' => 'Domain Transfer Service - ' . ($request->domain_name ?? 'N/A'),
                                'quantity' => 1,
                                'unit_price' => $amount,
                                'total' => $amount,
                            ];
                        }
                        
                        // Add credit discount as a separate line if credit used
                        if ($creditUsed > 0) {
                            $invoiceItems[] = [
                                'name' => 'Credit Applied',
                                'description' => 'Wallet credit adjustment',
                                'quantity' => 1,
                                'unit_price' => -$creditUsed,
                                'total' => -$creditUsed,
                            ];
                        }
                    @endphp

                    <div class="card mt-3 mb-4">
                        <div class="card-body">
                            <h6 class="mb-3">Order Items</h6>
                            <div class="table-responsive">
                                <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                                    <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                                        <tr>
                                            <th>Item</th>
                                            <th>Description</th>
                                            <th class="text-end">Quantity</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($invoiceItems as $item)
                                            <tr>
                                                <td>{{ $item['name'] ?? '-' }}</td>
                                                <td>{{ $item['description'] ?: '-' }}</td>
                                                <td class="text-end">{{ (int) ($item['quantity'] ?? 1) }}</td>
                                                <td class="text-end">₹{{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                                                <td class="text-end">₹{{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No items found</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot class="bg-light bg-opacity-25">
                                        <tr>
                                            <th colspan="4" class="text-end">Subtotal:</th>
                                            <th class="text-end">₹{{ number_format($amount, 2) }}</th>
                                        </tr>
                                        @if($creditUsed > 0)
                                        <tr>
                                            <th colspan="4" class="text-end">Credit Used:</th>
                                            <th class="text-end">- ₹{{ number_format($creditUsed, 2) }}</th>
                                        </tr>
                                        <tr>
                                            <th colspan="4" class="text-end fw-bold">Net Payable:</th>
                                            <th class="text-end fw-bold">₹{{ number_format(max(0, $netAmount), 2) }}</th>
                                        </tr>
                                        @endif
                                    </tfoot>
                                </table>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="d-flex flex-wrap gap-2 mt-4">
                                @php
                                    // Generate action URL dynamically based on route existence
                                    $orderActionUrl = \Illuminate\Support\Facades\Route::has('superadmin.domain_transfer_orders.action')
                                        ? route('superadmin.domain_transfer_orders.action', $request->id)
                                        : url('/superadmin/domain-transfer-orders-action/' . $request->id);
                                @endphp

                                <form id="orderActionForm" action="{{ $orderActionUrl }}" method="post" class="d-none">
                                    @csrf
                                    <input type="hidden" name="action" id="orderActionInput" value="">
                                </form>

                                {{-- Show Cancel button only for pending orders --}}
                                @if(strtolower($request->status ?? 'pending') === 'pending')
                                    <button type="button" data-action="cancel" class="btn btn-sm rounded-pill px-3 js-order-action"
                                        style="background:#1e58b4;color:#fff;">Cancel Order</button>
                                @endif
                                {{-- View Invoice Button --}}
                                <a href="{{ url('/superadmin/domain-transfer-orders-invoice/' . $request->id) }}" target="_blank"
                                    class="btn btn-sm rounded-pill px-3" style="background:#1e58b4;color:#fff;">
                                    View Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            // Handle order actions (cancel, etc.)
            $('.js-order-action').on('click', function() {
                const action = $(this).data('action');
                if (action === 'cancel' && !confirm('Are you sure you want to cancel this domain transfer order?')) return;
                if (action === 'fraud' && !confirm('Are you sure you want to mark this order as fraud?')) return;
                $('#orderActionInput').val(action);
                $('#orderActionForm').trigger('submit');
            });
        });
    </script>
@endpush