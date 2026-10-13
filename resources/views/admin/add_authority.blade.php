@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('authority-table') }}">Role</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Role Permissions</h5>
            </div>
            <div class="card-body">
                <form id="form">
                    @csrf
                    <div class="row mb-4">
                        <div class="col-lg-4">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input class="form-control text-uppercase bg-light" type="text" name="role_name" id="role_name" @if(isset($update->role_name)) readonly @endif  value="{{ $update->role_name ?? '' }}">
                            <div class="text-danger fs-xs" id="error-role_name"></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Module / Sub-Module</th>
                                    <th style="width: 150px;">Select All</th>
                                    <th>Permissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php 
                                    $i = 1;
                                    $modules = DB::connection('pgsql_admin')->table('admin_user_module_mst')->orderBy('id')->get();
                                @endphp

                                @foreach ($modules as $Mrow)
                                    <tr class="table-info bg-opacity-10">
                                        <td colspan="4" class="fw-bold text-primary">{{ $Mrow->module_name }}</td>
                                    </tr>
                                    @php
                                        $submodules = DB::connection('pgsql_admin')->table('admin_user_module')->where('mst_id', $Mrow->id)->orderBy('id')->get();
                                    @endphp
                                    @foreach ($submodules as $row)
                                        <tr>
                                            <td>{{ $i++ }}</td>
                                            <td class="fw-medium">{{ $row->modules }}</td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input allcheck" type="checkbox" id="all_{{ $row->id }}">
                                                    <label class="form-check-label" for="all_{{ $row->id }}">All</label>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-3">
                                                    @php 
                                                        $perms = explode(',', $row->permissions);
                                                        $savedPerms = !empty($id) ? user_role_loop($id, $row->id) : [];
                                                    @endphp
                                                    @foreach ($perms as $p)
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="{{ $row->id . '[]' }}" 
                                                                   id="{{ $row->id . '_' . strtolower($p) }}" value="{{ $p }}"
                                                                   @if(!empty($savedPerms) && in_array($p, $savedPerms)) checked @endif>
                                                            <label class="form-check-label" for="{{ $row->id . '_' . strtolower($p) }}">{{ $p }}</label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-4">
                        <input type="hidden" name="id" value="{{ $update->id ?? '' }}">
                        <a href="{{ url('authority-table') }}" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;">Back</a>
                        <button type="submit" class="btn btn-sm rounded-pill px-3 py-1" style="background:#1e58b4;color:#fff;" id="submit">
                            <span class="spinner-border spinner-border-sm me-1" id="spinner" style="display:none;"></span>
                            Save Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $(".allcheck").on("change", function() {
                var row = $(this).closest('tr');
                row.find('td:nth-child(4) input[type="checkbox"]').prop('checked', $(this).is(':checked'));
            });

            $('#form').on('submit', function(e) {
                e.preventDefault();
                $('#submit').attr('disabled', true);
                $('#spinner').show();

                $.ajax({
                    url: "{{ url('superadmin/authority-validation') }}",
                    type: "POST",
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        $('#submit').attr('disabled', false);
                        $('#spinner').hide();
                        if (data.error) {
                            alertify.error("Please fix errors.");
                        } else {
                            alertify.success(data.success);
                            setTimeout(function() {
                                window.location.href = "{{ url('superadmin/authority-table') }}";
                            }, 1000);
                        }
                    }
                });
            });
        });
    </script>
@endpush
