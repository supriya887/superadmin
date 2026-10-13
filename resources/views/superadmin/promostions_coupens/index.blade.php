@extends('superadmin.layouts.app')

@section('title', 'Promotions & Coupons')

@section('content')

    <div class="container-fluid px-0">

        <div class="row">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Promotions & Coupons</h4>
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Promotions & Coupons</li>
                </ol>
            </div>
        </div>

        {{-- DASHBOARD CARDS --}}
        <div class="row mb-4">
            @php
                $totalCoupons = $totalCoupons ?? 0;
                $activeCoupons = $activeCoupons ?? 0;
                $expiredCoupons = $expiredCoupons ?? 0;
                $upcomingCoupons = $upcomingCoupons ?? 0;
                $totalRedemptions = $totalRedemptions ?? 0;
                $coupons = $coupons ?? collect();
                $redemptionCounts = $redemptionCounts ?? [];
            @endphp

            {{-- Total Coupons --}}
            <div class="col">
                <div class="card mb-1">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title text-bg-primary rounded-circle fs-22">
                                    <i class="ti ti-ticket"></i>
                                </span>
                            </div>
                            <h3 class="mb-0">{{ number_format($totalCoupons ?? 0) }}</h3>
                        </div>
                        <p class="mb-0">
                            Total Coupons
                        </p>
                    </div>
                </div>
            </div>

            {{-- Active Coupons --}}
            <div class="col">
                <div class="card mb-1">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title text-bg-success rounded-circle fs-22">
                                    <i class="ti ti-circle-check"></i>
                                </span>
                            </div>
                            <h3 class="mb-0">{{ number_format($activeCoupons ?? 0) }}</h3>
                        </div>
                        <p class="mb-0">
                            Active Coupons
                        </p>
                    </div>
                </div>
            </div>

            {{-- Expired Coupons --}}
            <div class="col">
                <div class="card mb-1">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title text-bg-danger rounded-circle fs-22">
                                    <i class="ti ti-clock"></i>
                                </span>
                            </div>
                            <h3 class="mb-0">{{ number_format($expiredCoupons ?? 0) }}</h3>
                        </div>
                        <p class="mb-0">
                            Expired Coupons
                        </p>
                    </div>
                </div>
            </div>

            {{-- Upcoming Coupons --}}
            <div class="col">
                <div class="card mb-1">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title text-bg-warning rounded-circle fs-22">
                                    <i class="ti ti-calendar-clock"></i>
                                </span>
                            </div>
                            <h3 class="mb-0">{{ number_format($upcomingCoupons ?? 0) }}</h3>
                        </div>
                        <p class="mb-0">
                            Upcoming Coupons
                        </p>
                    </div>
                </div>
            </div>

            {{-- Total Redemptions --}}
            <div class="col">
                <div class="card mb-1">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title text-bg-info rounded-circle fs-22">
                                    <i class="ti ti-users"></i>
                                </span>
                            </div>
                            <h3 class="mb-0">{{ number_format($totalRedemptions ?? 0) }}</h3>
                        </div>
                        <p class="mb-0">
                            Total Redemptions
                        </p>
                    </div>
                </div>
            </div>

        </div>

        {{-- COUPONS LIST --}}
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Coupons List</h4>
                        <div>
                            <button type="button" class="btn btn-primary" id="addCouponBtn" data-bs-toggle="modal"
                                data-bs-target="#couponModal">
                                Add Coupon
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div id="couponsMessageBox"></div>

                        <div class="table-responsive">
                            <table id="orders-table" class="table table-custom table-centered table-select table-hover w-100 mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>COUPON CODE</th>
                                        <th>DISCOUNT</th>
                                        <th>START DATE</th>
                                        <th>EXPIRED DATE</th>
                                        <th>USED</th>
                                        <th class="text-center">STATUS</th>
                                        <th width="220" class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($coupons as $coupon)
                                        @php
                                            $status = $coupon->status == 1 ? 'Active' : 'Inactive';
                                            $statusBadge = $status === 'Active'
                                                ? '<span class="badge bg-success mt-1">Active</span>'
                                                : '<span class="badge bg-danger mt-1">Inactive</span>';
                                            $usedCount = (int) ($redemptionCounts[$coupon->code] ?? 0);
                                        @endphp
                                        <tr>
                                            <td>{{ $coupon->id }}</td>
                                            <td>{{ $coupon->code }}</td>
                                            <td>{{ $coupon->discount_percent }}%</td>
                                            <td>{{ $coupon->start_date ?? '-' }}</td>
                                            <td>{{ $coupon->end_date ?? '-' }}</td>
                                            <td class="text-center">{{ $usedCount }}</td>
                                            <td class="text-center">{!! $statusBadge !!}</td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="javascript:void(0);" class="btn btn-light btn-icon btn-sm rounded-circle editCouponBtn" data-id="{{ $coupon->id }}" title="Edit Coupon">
                                                        <i class="ti ti-edit fs-lg"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">No coupons found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
                <div class="modal fade" id="couponModal" tabindex="-1">
                    <!-- Updated modal-dialog classes for centering and top margin -->
                    <div class="modal-dialog modal-dialog-centered" style="margin-top:40px;">
                        <div class="modal-content">
                            <form id="couponForm">
                                @csrf
                                <input type="hidden" id="couponId" name="id">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="couponModalLabel">
                                        Add Coupon
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label>Coupon Code</label>
                                        <input type="text" class="form-control" name="code" id="code" >
                                        <span id="code_error" class="text-danger"></span>
                                    </div>
                                    <div class="mb-3">
                                        <label>Discount %</label>
                                        <input type="number" class="form-control" name="discount_percent" id="discount_percent"
                                            min="1" max="100" step="1" inputmode="numeric">
                                        <span id="discount_percent_error" class="text-danger"></span>
                                    </div>
                                    {{-- status --}}
                                    <div class="mb-3">
                                        <label>Status</label>
                                        <select class="form-control" name="promo_status" id="promo_status">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <span id="promo_status_error" class="text-danger"></span>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        Save Coupon
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
                    let couponModal = new bootstrap.Modal(document.getElementById('couponModal'));

                    // Utility to set modal position (centered & top margin)
                    function positionCouponModal() {
                        var dlg = $('#couponModal .modal-dialog');
                        dlg.addClass('modal-dialog-centered');
                        dlg.css('margin-top', '40px');
                    }

                    function clearCouponFormErrors() {
                        $('#code_error, #discount_percent_error, #promo_status_error').text('');
                    }

                    // Add Coupon - handle button click to reset and adjust modal
                    $('#addCouponBtn').on('click', function() {
                        $('#couponForm')[0].reset();
                        clearCouponFormErrors();
                        $('#couponId').val('');
                        $('#code').val('');
                        $('#discount_percent').val('');
                        $('#promo_status').val('1');
                        $('#couponModalLabel').text('Add Coupon');
                        positionCouponModal();
                    });

                    // Always adjust modal when it's shown (for both add and edit)
                    $('#couponModal').on('shown.bs.modal', function () {
                        positionCouponModal();
                    });

                    // Reset on hidden
                    $('#couponModal').on('hidden.bs.modal', function () {
                        $('#couponForm')[0].reset();
                        clearCouponFormErrors();
                        $('#couponId').val('');
                        $('#code').val('');
                        $('#discount_percent').val('');
                        $('#promo_status').val('1');
                        $('#couponModalLabel').text('Add Coupon');
                    });

                    $('#couponForm').submit(function(e) {
                        e.preventDefault();
                        clearCouponFormErrors();
                        $.ajax({
                            url: "{{ route('superadmin.promotions_coupons.save') }}",
                            type: "POST",
                            data: $(this).serialize(),
                            success: function(res) {
                                if (res.success) {
                                    couponModal.hide();
                                    $('#couponForm')[0].reset();
                                    alertify.success(res.message);
                                    location.reload();
                                }
                            },
                            error: function(xhr) {
                                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                    var errors = xhr.responseJSON.errors;
                                    $('#code_error').text(errors.code ? errors.code[0] : '');
                                    $('#discount_percent_error').text(errors.discount_percent ? errors.discount_percent[0] : '');
                                    $('#promo_status_error').text(errors.promo_status ? errors.promo_status[0] : '');
                                    alertify.error('Please fix the highlighted fields.');
                                    return;
                                }
                                alertify.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Something went wrong');
                            }
                        });
                    });

                    // Edit Coupon
                    $(document).on('click', '.editCouponBtn', function() {
                        let id = $(this).data('id');
                        $.ajax({
                            url: "{{ route('superadmin.promotions_coupons.edit', ['id' => ':id']) }}".replace(':id', id),
                            type: "GET",
                            success: function(res) {
                                let coupon = res.data;
                                clearCouponFormErrors();
                                $('#couponId').val(coupon.id);
                                $('#code').val(coupon.code);
                                $('#discount_percent').val(parseInt(coupon.discount_percent, 10) || '');
                                $('#promo_status').val(String(parseInt(coupon.status, 10) || 0)).trigger('change');
                                $('#couponModalLabel').text('Edit Coupon');
                                // Show modal with updated position
                                couponModal.show();
                                positionCouponModal();
                            }
                        });
                    });
                });
            </script>
@endpush
