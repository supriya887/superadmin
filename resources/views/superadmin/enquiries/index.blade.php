@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <br>
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Partner Enquiries</h5>
                <a href="{{ url('superadmin/add-enquiry') }}" class="btn btn-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Add Enquiry
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Partner</th>
                                <th>Email</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result as $item)
                                <tr>
                                    <td>{{ $item->id }}</td>
                                    <td>{{ $item->partner->name ?? ('#' . $item->partner_id) }}</td>
                                    <td>{{ $item->partner->email ?? '-' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($item->message, 80) }}</td>
                                    <td>
                                        @php $st = strtolower(trim((string) $item->status)); @endphp
                                        @if($st === 'new')
                                            <span class="badge bg-warning text-dark">New</span>
                                        @elseif($st === 'in_progress')
                                            <span class="badge bg-info">In Progress</span>
                                        @elseif($st === 'resolved')
                                            <span class="badge bg-success">Resolved</span>
                                        @elseif($st === 'closed')
                                            <span class="badge bg-secondary">Closed</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ $item->status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->created_at ? $item->created_at->format('d M Y H:i') : '-' }}</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="{{ url('superadmin/add-enquiry/' . $item->id) }}" class="btn btn-sm btn-soft-primary" title="Edit">
                                                <i class="ti ti-pencil"></i>
                                            </a>
                                            <button id="{{ $item->id }}" class="btn btn-sm btn-soft-danger delete" title="Delete">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@csrf
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#datatable').DataTable({
                order: [[0, 'desc']]
            });

            $('.delete').on('click', function() {
                var id = $(this).attr('id');
                var token = $('input[name="_token"]').val();

                alertify.confirm("Are you sure you want to delete this enquiry?", function() {
                    $.ajax({
                        url: "{{ url('superadmin/delete-enquiry') }}",
                        type: "POST",
                        data: {
                            id: id,
                            _token: token
                        },
                        success: function() {
                            alertify.success("Enquiry Deleted Successfully.");
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    });
                });
            });
        });
    </script>
@endpush
