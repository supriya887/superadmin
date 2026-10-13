@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <br>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto mb-2">
            <a href="{{ route('superadmin.categories.form') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Add Category
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-custom table-centered table-select table-hover w-100 mb-0" id="categoriesTable">
                    <thead class="bg-light align-middle bg-opacity-25 thead-sm">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 1%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                        <tr id="row-{{ $category->id }}">
                            <td>{{ $category->id }}</td>
                            <td>{{ $category->name }}</td>
                            <td>
                                @if($category->status == 'active')
                                    <span class="badge bg-success mt-1">Active</span>
                                @else
                                    <span class="badge bg-danger mt-1">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('superadmin.categories.form', $category->id) }}" class="btn btn-light btn-icon btn-sm rounded-circle" data-bs-toggle="tooltip" title="Edit Category">
                                        <i class="ti ti-edit fs-lg"></i>
                                    </a>
                                </div>
                                {{-- <button class="btn btn-sm btn-danger delete-category ms-1" data-id="{{ $category->id }}" data-bs-toggle="tooltip" title="Delete Category">
                                    <i class="fas fa-trash"></i>
                                </button> --}}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script>
    $(document).ready(function() {
        // Handle Delete Category
        $('.delete-category').on('click', function () {
            var categoryId = $(this).data('id');

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this category?', 
                function() { 
                    $.ajax({
                        url: '{{ route('superadmin.categories.delete') }}', 
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            id: categoryId
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                alertify.success(response.success);
                                $('#row-' + categoryId).fadeOut();
                            } else {
                                alertify.error('Error deleting category.');
                            }
                        },
                        error: function (xhr) {
                            alertify.error('An error occurred.');
                        }
                    });
                },
                function() { }
            ).set('labels', {ok:'Yes', cancel:'No'});
        });
    });
</script>
@endpush
