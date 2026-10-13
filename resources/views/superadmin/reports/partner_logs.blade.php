@extends('superadmin.layouts.app')

@section('content')
    <div class="col-12 mt-2">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-main fw-bold">Partner Logs & Security Audits</h4>
        </div>
    </div>
    </div>

    <div class="container-fluid mt-2">
        <div class="card border-danger mb-4">
            <div class="card-header bg-danger text-white">Cyber Security Alert (Malicious Actions Detected)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped text-center align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Target Identity</th>
                                <th>IP Address</th>
                                <th>User Agent (Device)</th>
                                <th>Payload Context Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($securityThreats as $threat)
                                <tr class="table-warning">
                                    <td>{{ $threat->created_at }}</td>
                                    <td><code>{{ $threat->identifier }}</code></td>
                                    <td><span class="badge bg-danger">{{ $threat->ip_address }}</span></td>
                                    <td><small>{{ $threat->user_agent }}</small></td>
                                    <td class="text-danger font-monospace"><strong>{{ $threat->payload }}</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted">No suspicious activity logged.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $securityThreats->links() }}
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Partner Authentication Access History</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Partner</th>
                                <th>Action State</th>
                                <th>IP Details</th>
                                <th>System Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($authLogs as $log)
                                <tr>
                                    <td>{{ $log->created_at }}</td>
                                    <td>{{ $log->partner_name ?? 'N/A' }} ({{ $log->identifier }})</td>
                                    <td>
                                        <span class="badge bg-{{ $log->action == 'login_success' ? 'success' : ($log->action == 'force_logged_out' ? 'warning' : 'secondary') }}">
                                            {{ strtoupper($log->action) }}
                                        </span>
                                    </td>
                                    <td>{{ $log->ip_address }}</td>
                                    <td><small class="text-muted">{{ $log->payload }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted">No authentication logs found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $authLogs->links() }}
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-dark text-white">Immutable Raw OTP Issuance Log (Historical Audit Data)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Generated At</th>
                                <th>Partner Reference</th>
                                <th>Channel Type</th>
                                <th>Expiration Status</th>
                                <th>Is Verified?</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($otpHistory as $otp)
                                <tr>
                                    <td>{{ $otp->created_at }}</td>
                                    <td>{{ $otp->partner_name }} ({{ $otp->partner_email }})</td>
                                    <td><span class="badge bg-info">{{ $otp->login_field ?: $otp->type }}</span></td>
                                    <td>{{ $otp->expires_at }}</td>
                                    <td>
                                        @if ($otp->verified)
                                            <span class="text-success">Verified</span>
                                        @else
                                            <span class="text-danger">Expired / Unused</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted">No OTP history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $otpHistory->links() }}
            </div>
        </div>
    </div>
@endsection
