@extends('superadmin.layouts.app')

@section('content')
<div class="row">
    <div class="col-12 mt-2">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Wallet Statement - {{ $partner->name }}</h4>
            <a href="{{ route('superadmin.b2b_partners.edit', $partner->id) }}" class="btn btn-sm btn-secondary">Back to Partner</a>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-2">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('superadmin.b2b_partners.wallet_statement', $partner->id) }}" class="btn btn-light">Reset</a>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <span class="badge bg-success">Current Balance: ₹{{ number_format($currentBalance, 2) }}</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction Type</th>
                                <th>Description</th>
                                <th>Order No</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Closing Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statementRows as $row)
                                <tr>
                                    <td>{{ !empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d M Y H:i') : '-' }}</td>
                                    <td class="text-capitalize">{{ $row['type'] }}</td>
                                    <td>{{ $row['description'] !== '' ? $row['description'] : '-' }}</td>
                                    <td>{{ $row['order_no'] ?: '-' }}</td>
                                    <td class="text-end">₹{{ number_format((float) $row['amount'], 2) }}</td>
                                    <td class="text-end">₹{{ number_format((float) $row['closing_balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No wallet transactions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
