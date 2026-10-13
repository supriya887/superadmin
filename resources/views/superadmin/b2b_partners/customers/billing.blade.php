@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="card mb-2">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">Billing History - {{ $customer->name }}</h3>
                        <a href="{{ route('superadmin.b2b_partners.customers.list', ['partner_id' => $encryptedPartnerId]) }}"
                            class="btn btn-sm rounded-pill px-4" style="background:#1e58b4;color:#fff;">
                            Back to Customers
                        </a>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-custom table-centered table-select table-hover w-100 mb-0">
                                <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer Name</th>
                                        <th>Plan Name</th>
                                        <th>Order Type</th>
                                        <th>Amount</th>
                                        <th>Credit Used</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($orders as $order)
                                        @php
                                            $status = strtolower((string) ($order->status ?? 'pending'));
                                            $statusBadge =
                                                '<span class="badge bg-warning text-capitalize">' .
                                                e($status) .
                                                '</span>';
                                            if (in_array($status, ['completed', 'paid', 'success', 'active'], true)) {
                                                $statusBadge =
                                                    '<span class="badge bg-success text-capitalize">Active</span>';
                                            } elseif (in_array($status, ['failed', 'cancelled', 'rejected'], true)) {
                                                $statusBadge =
                                                    '<span class="badge bg-danger text-capitalize">' .
                                                    e($status) .
                                                    '</span>';
                                            }
                                        @endphp

                                        <tr>
                                            <td>#{{ $order->order_no }}</td>
                                            <td>{{ $customer->name ?? '-' }}</td>
                                            <td>{{ $order->plan_name ?? '-' }}</td>
                                            <td>{{ strtoupper((string) ($order->order_type ?? 'b2b')) }}</td>
                                            <td>₹{{ number_format((float) ($order->amount ?? 0), 2) }}</td>
                                            <td>₹{{ number_format((float) ($order->credit_used ?? 0), 2) }}</td>
                                            <td>{{ !empty($order->created_at) ? \Illuminate\Support\Carbon::parse($order->created_at)->format('d M Y') : '-' }}
                                            </td>
                                            <td>
                                                {!! $statusBadge !!}
                                            </td>
                                            <td class="text-end">
                                                <a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Invoice"
                                                    href="{{ route('superadmin.orders.invoice', $order->id) }}">
                                                    <i class="ti ti-file-invoice fs-lg"></i>
                                                </a>
                                                <a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Details"
                                                    href="{{ route('superadmin.orders.details', $order->id) }}">
                                                    <i class="ti ti-eye fs-lg"></i>
                                                </a>
                                                <a class="btn btn-light btn-icon btn-sm rounded-circle"
                                                    title="Download Invoice"
                                                    href="{{ route('superadmin.orders.invoice.download', $order->id) }}">
                                                    <i class="ti ti-download fs-lg"></i>
                                                </a>

                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">No billing history found
                                                for this customer.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
