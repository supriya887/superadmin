@extends('superadmin.index')

@section('content')
<div class="main-container container-fluid">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.index') }}">Providers</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.variants', $variant->provider_id) }}">{{ $provider->name }} Variants</a></li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <a href="{{ route('superadmin.providers.variants', $variant->provider_id) }}" class="btn btn-secondary me-2">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Variants
            </a>
            <a href="{{ route('superadmin.providers.variants.plans.create', $variant->id) }}" class="btn btn-primary">
                Add Plan
            </a>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">{{ $pageTitle }} for Variant: {{ $variant->name }}</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Price</th>
                            <th>Billing Cycle</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(empty($plans) || $plans->isEmpty())
                        <tr>
                            <td colspan="5" class="text-center">No plans found for this variant.</td>
                        </tr>
                        @else
                        @foreach($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>${{ number_format($plan->price, 2) }}</td>
                            <td>{{ ucfirst($plan->billing_cycle) }}</td>
                            <td>
                                @if($plan->status == 'active')
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('superadmin.providers.variants.plans.edit', $plan->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Edit Plan">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-danger delete-plan ms-1" data-id="{{ $plan->id }}" data-bs-toggle="tooltip" title="Delete Plan">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                        @endif
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
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Handle Delete Plan
        $('.delete-plan').on('click', function () {
            var planId = $(this).data('id');

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this plan?', 
                function() { 
                    $.ajax({
                        url: '{{ route('superadmin.providers.variants.plans.delete') }}', 
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            id: planId
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                alertify.success('Plan deleted successfully.');
                                location.reload();
                            } else {
                                alertify.error('Error deleting plan.');
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
