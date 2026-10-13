@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid mt-2">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Partner Login Logs</h3>
                    </div>
                    <div class="card-body">
                        <form id="partnerLogsSearchForm">
                            <div class="row">
                                <div class="col-lg-3 mt-2">
                                    <label for="search_identifier">Identifier</label>
                                    <input class="form-control" type="text" id="search_identifier" name="search_identifier" placeholder="Email or mobile">
                                </div>
                                <div class="col-lg-3 mt-2">
                                    <label for="search_partner">Partner</label>
                                    <input class="form-control" type="text" id="search_partner" name="search_partner" placeholder="Name, company or ID">
                                </div>
                                <div class="col-lg-3 mt-2">
                                    <label for="search_action">Action</label>
                                    <select class="form-control" id="search_action" name="search_action">
                                        <option value="">All</option>
                                        <option value="login_success">Login Success</option>
                                        <option value="login_failed">Login Failed</option>
                                        <option value="otp_sent">OTP Sent</option>
                                        <option value="suspicious_activity">Suspicious Activity</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 mt-2 d-flex align-items-end">
                                    <div class="w-100">
                                        <button class="btn btn-secondary w-100" type="submit">
                                            <i class="fa fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-12 mt-2 text-center">
                                    <button class="btn btn-light" type="button" id="resetPartnerLogsSearch">
                                        <i class="fa fa-undo"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom table-centered table-hover w-100 mb-0" id="partnerLoginLogsTable">
                                <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                                    <tr>
                                        <th>ID</th>
                                        <th>Partner</th>
                                        <th>Identifier</th>
                                        <th>Action</th>
                                        <th>IP Address</th>
                                        <th>User Agent</th>
                                        <th>Details</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var dataTable = $('#partnerLoginLogsTable').DataTable({
                processing: true,
                serverSide: true,
                order: [[0, 'desc']],
                ajax: {
                    url: "{{ route('superadmin.partner_logs.table') }}",
                    type: "POST",
                    data: function(d) {
                        d._token = $('meta[name="csrf-token"]').attr('content');
                        d.search_identifier = $('#search_identifier').val();
                        d.search_partner = $('#search_partner').val();
                        d.search_action = $('#search_action').val();
                    }
                },
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'partner', name: 'partner', orderable: false },
                    { data: 'identifier', name: 'identifier' },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                    { data: 'ip_address', name: 'ip_address' },
                    { data: 'user_agent', name: 'user_agent', orderable: false },
                    { data: 'payload', name: 'payload', orderable: false },
                    { data: 'created_at', name: 'created_at' },
                ]
            });

            $('#partnerLogsSearchForm').on('submit', function(e) {
                e.preventDefault();
                dataTable.ajax.reload();
            });

            $('#resetPartnerLogsSearch').on('click', function() {
                $('#search_identifier').val('');
                $('#search_partner').val('');
                $('#search_action').val('');
                dataTable.ajax.reload();
            });
        });
    </script>
@endpush
