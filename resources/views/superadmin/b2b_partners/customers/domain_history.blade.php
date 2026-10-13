@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title mb-0">Domain History : <span class="text-primary">{{ $domain }}</span></h3>
                    <small class="text-muted">Partner : {{ $partner->company_name ?: $partner->name }}</small>
                </div>
                <span class="badge bg-light text-dark border">{{ count($history) }} records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Order No</th>
                                <th>Plan</th>
                                <th>Category</th>
                                <th class="text-center">Licenses</th>
                                <th class="text-end">Price</th>
                                <th>Period</th>
                                <th>Enrollment</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $row)
                                <tr>
                                    <td>{{ $row['order_no'] ?? '-' }}</td>
                                    <td>{{ $row['plan_name'] ?? '-' }}</td>
                                    <td>{{ ucfirst((string) ($row['order_category'] ?? '-')) }}</td>
                                    <td class="text-center">{{ $row['license'] ?? 0 }}</td>
                                    <td class="text-end">₹{{ number_format((float) ($row['price'] ?? 0), 2) }}</td>
                                    <td>{{ $row['period'] ?? '-' }}</td>
                                    <td>{{ $row['enrollment_type'] ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $row['status_label'] ?? ($row['status'] ?? '-') }}</span>
                                        @if (!empty($row['status_detail']))
                                            <div class="small text-muted">{{ $row['status_detail'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-light btn-icon btn-sm rounded-circle" title="View Order" target="_blank"
                                            href="{{ route('superadmin.orders.details', (int) ($row['order_id'] ?? 0)) }}"><i class="ti ti-eye fs-lg"></i></a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
