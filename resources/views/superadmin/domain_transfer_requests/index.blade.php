@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Domain Transfer Requests</h3>
                    </div>
                    <div class="card-body">
                        @if (!$tableExists)
                            <div class="alert alert-warning mb-0">
                                <strong>Note:</strong> `domain_transfer_requests` table not found. Create/populate this
                                table to view live requests.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table
                                    class="table table-custom table-centered table-select table-hover w-100 mb-0 text-nowrap">
                                    <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                                        <tr>
                                            <th>Domain</th>
                                            <th>Transfer Code</th>
                                            <th>Requested By</th>
                                            <th>Status</th>
                                            <th class="text-center" style="width: 1%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($requests as $request)
                                            @php
                                                $status = strtolower((string) $request->status);
                                                $badgeClass = match ($status) {
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    default => 'bg-warning',
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $request->domain }}</td>
                                                <td>{{ $request->transfer_code }}</td>
                                                <td>{{ $request->requested_by_type }}</td>
                                                <td><span
                                                        class="badge {{ $badgeClass }}">{{ ucfirst($status ?: 'pending') }}</span>
                                                </td>
                                                <td class="text-center">
                                                    @if ($status === 'pending')
                                                        <div class="d-flex justify-content-center gap-1">
                                                            <form method="POST"
                                                                action="{{ route('superadmin.domain_transfer_requests.update_status', ['id' => $request->id]) }}">
                                                                @csrf
                                                                <input type="hidden" name="status" value="approved">
                                                                <button type="submit"
                                                                    class="btn btn-light btn-icon btn-sm rounded-circle"
                                                                    title="Approve">
                                                                    <i class="ti ti-check fs-lg text-success"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST"
                                                                action="{{ route('superadmin.domain_transfer_requests.update_status', ['id' => $request->id]) }}">
                                                                @csrf
                                                                <input type="hidden" name="status" value="rejected">
                                                                <button type="submit"
                                                                    class="btn btn-light btn-icon btn-sm rounded-circle"
                                                                    title="Reject">
                                                                    <i class="ti ti-x fs-lg text-danger"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">No action</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No transfer requests
                                                    found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
