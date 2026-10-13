@extends('superadmin.layouts.app')

@section('content')

<div class="main-container container-fluid mt-4">
    <style>
        /* Reuse existing styles as before */
        .mobile-plan-card{
            background-color:#fff;
            border:1px solid #D5DCE6;
            border-radius:10px;
            overflow:visible;
            box-shadow:0 2px 10px rgba(0,0,0,.04);
            position:relative;
            margin-bottom:20px;
            cursor: grab; /* Draggable everywhere */
        }
        .mobile-plan-badge{
            position:absolute;
            right: 44%;
            top:-9px;
            z-index:6;
            min-width:52px;
            height:24px;
            border-radius:24px;
            padding:0 8px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:11px;
            font-weight:600;
            color:#fff;
        }
        .mobile-plan-title-wrap{
            background:#E6F1FD;
            border-radius:10px;
            padding-top:16px;
            padding-bottom:10px;
            text-align:center;
            margin:15px 12px;
        }
        .mobile-plan-title{
            color:#2C3139;
            font-weight:700;
            font-size:15px;
            line-height:19px;
            margin-bottom:4px;
            text-transform:capitalize;
            padding:5px 20px;
        }
        .mobile-plan-subtitle{
            color:#4C4C5C;
            font-weight:400;
            font-size:14px;
            line-height:20px;
            margin:0;
        }
        .mobile-plan-badge2{
            background:#BCDAF9;
            border-radius:0 0 10px 10px;
            height:34px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 12px;
        }
        .mobile-plan-badge2-text{
            color:#4C4C5C;
            font-weight:700;
            font-size:14px;
            line-height:20px;
            text-align:center;
        }
        .mobile-plan-body{
            padding:14px 16px 12px 16px;
        }
        .mobile-plan-amount-original{
            color:#9BA6B7;
            font-size:20px;
            line-height:25px;
            text-decoration:line-through;
            font-weight:700;
            margin:0 0 2px 0;
        }
        .mobile-plan-amount{
            color:#0355AC;
            font-weight:700;
            font-size:40px;
            line-height:50px;
            margin:0 0 4px 0;
        }
        .mobile-plan-info2{
            color:#9BA6B7;
            font-size:12px;
            line-height:20px;
            margin:0;
            text-align:center;
            text-transform:capitalize;
        }
        .mobile-plan-features{
            margin-top:20px;
            padding-top:10px;
            padding-bottom:20px;
            padding-left:16px;
        }
        .mobile-plan-feature{
            display:flex;
            align-items:flex-start;
            margin-bottom:6px;
            gap:8px;
        }
        .mobile-plan-feature span{
            flex:1;
            color:#3F4A59;
            font-size:14px;
            line-height:20px;
            font-weight:400;
        }
        .mobile-plan-cta{
            width:100%;
            max-width:100%;
            border-radius:8px;
            background-color:#0355AC;
            height:38px;
            color:#fff;
            font-weight:600;
            display:flex;
            align-items:center;
            justify-content:center;
            text-decoration:none;
            border-radius: 19px;
        }
        .mobile-plan-footer{
            padding:0 16px 18px 16px;
            display:flex;
            justify-content:center;
        }
        .sortable-chosen { background: #f5faff !important; }
        .sortable-ghost { opacity: .5; }
        /* For time-updated info after sort */
        .time-updated-badge {
            display: inline-block;
            background: #e6fafd;
            color: #2c5282;
            border-radius: 12px;
            font-size:12px;
            font-weight:600;
            margin-left: 12px;
            padding: 2px 12px;
        }
        .microsoft-plan-type-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin-top: 6px;
        }
        .microsoft-plan-type-badges .badge {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
    </style>

    <div class="breadcrumb-header justify-content-between">
        <div>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.providers.index') }}">Providers</a></li>
                    @if($provider->has_variant == 'yes')
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/providers-variants/' . $provider_id) }}">{{ $provider->name }} Variants</a></li>
                    @endif
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            @if($provider->has_variant == 'no')
                <a href="{{ route('superadmin.providers.index') }}" class="btn btn-secondary me-2">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to Providers
                </a>
            @else
                <a href="{{ url('superadmin/providers-variants/' . $provider_id) }}" class="btn btn-secondary me-2">
                    <i class="mdi mdi-arrow-left me-1"></i> Back to Variants
                </a>
            @endif

            <a href="{{ url('superadmin/plans/add-plan/' . $provider_id . '/' . $variant_id) . '?source=provider' }}" class="btn btn-primary">
                Add Plan
            </a>        
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12 mb-3 d-flex align-items-center">
            <h5 class="text-muted mb-0">
                {{ $provider->name }} 
                @if(isset($variant) && $variant)
                    - <span class="text-primary">{{ $variant->name }}</span>
                @else
                    - <span class="text-primary">All Plans</span>
                @endif
            </h5>
            <span id="plans-time-updated-badge" class="time-updated-badge" style="display:none;">Order updated: <span id="plans-time-updated"></span></span>
        </div>
        @if(empty($plans) || $plans->isEmpty())
            <div class="col-12 text-center my-5">
                <div class="alert alert-info py-4">No plans found for this variant.</div>
            </div>
        @else
            <div class="row" id="plans-sortable">
            @foreach($plans as $plan)
                <div class="col-xl-4 col-md-6 mb-4 plan-card-item" data-id="{{ $plan->id }}">
                    @php
                        $statusStr = strtolower((string) ($plan->status ?? 'inactive'));
                        $isActive = ($plan->status == 1 || $statusStr === '1' || $statusStr === 'active' || $statusStr === 'enabled');
                        $badgeBg = $isActive ? '#0E61C2' : '#f3412c';
                        $limitedText = ($plan->limited_offer ?? '') == 'yes' ? 'Recommended' : '';
                        $originalPrice = (isset($plan->actual_price) && (float) $plan->actual_price > (float) $plan->price) ? (float) $plan->actual_price : null;
                        $infoText = (($plan->billing_cycle ?? '') === 'monthly') ? 'Per User / Per Month' : 'Per User / Per Year';
                    @endphp

                    <div class="mobile-plan-card">
                        <div class="mobile-plan-badge" style="background:{{ $badgeBg }};">
                            {{ $isActive ? 'Active' : 'Inactive' }}
                        </div>

                        <div class="mobile-plan-title-wrap">
                            <div class="mb-3"></div>
                            <h3 class="mobile-plan-title">{{ $plan->name }}</h3>
                            <p class="mobile-plan-subtitle">{{ $plan->storage }} Storage</p>
                            @if(isset($plan->category->name) && $plan->category->name)
                            <div class="mb-2">
                                <span class="badge bg-info text-uppercase">{{ $plan->category->name ?? '' }}</span>
                            </div>
                            @endif
                            {{-- Microsoft subscription/discount type badges removed from plan cards --}}
                        </div>

                        @if($limitedText)
                            <div class="mobile-plan-badge2">
                                <div class="mobile-plan-badge2-text">{{ $limitedText }}</div>
                            </div>
                        @endif

                        <div class="mobile-plan-body">
                            <div class="d-flex justify-content-center">
                                @if($originalPrice)
                                    <p class="mobile-plan-amount-original">₹{{ number_format($originalPrice, 2) }}</p>
                                @endif
                            </div>
                            <div class="d-flex justify-content-center">
                                <p class="mobile-plan-amount">₹{{ number_format((float) $plan->price, 2) }}</p>
                            </div>
                            <p class="mobile-plan-info2">{{ $infoText }}</p>
                            <p class="mobile-plan-info2">GST 18% Additional</p>
                            <p class="mobile-plan-info2">
                                <span style="color:#0355AC;font-weight:700;">{{ $plan->customer_limit }}</span> Customer Limit
                            </p>
                            <div class="mobile-plan-features">
                                @if(!empty($plan->features))
                                    @foreach(explode('|', $plan->features) as $feature)
                                        @php $feature = trim($feature); @endphp
                                        @if($feature !== '')
                                            <div class="mobile-plan-feature">
                                                <i class="ti ti-check" style="color:#02BC9C;font-size:16px;"></i>
                                                <span>{{ $feature }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                @else
                                    <div class="mobile-plan-feature">
                                        <i class="ti ti-info-square" style="color:#6c757d;font-size:16px;"></i>
                                        <span>No features listed.</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="mobile-plan-footer">
                            <a href="{{ url('superadmin/plans/edit-plan/' . $provider_id . '/' . $variant_id . '/' . $plan->id) . '?source=provider' }}"
                               class="mobile-plan-cta"
                               data-bs-toggle="tooltip"
                               title="Edit Plan">
                                Edit
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
    // Show a badge with updated time after drag sort;
    function showPlansTimeUpdated() {
        var now = new Date();
        var hh = String(now.getHours()).padStart(2, '0');
        var mm = String(now.getMinutes()).padStart(2, '0');
        var ss = String(now.getSeconds()).padStart(2, '0');
        var timeStr = hh + ':' + mm + ':' + ss;
        $('#plans-time-updated').text(timeStr);
        $('#plans-time-updated-badge').fadeIn().delay(3500).fadeOut();
    }

    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Handle Delete Plan
        $('.delete-plan').on('click', function () {
            var btn = $(this);
            var planId = btn.data('id');

            alertify.confirm('Confirm Deletion', 'Are you sure you want to delete this plan?', 
                function() { 
                    $.ajax({
                        url: '{{ url('superadmin/plans/delete-plan/'.$provider_id.'/'.$variant_id) }}', 
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

        // DRAG & DROP PLAN ORDER - drag from anywhere inside card (so handle removed)
        let plansSortable = document.getElementById('plans-sortable');
        if (plansSortable) {
            new Sortable(plansSortable, {
                animation: 200,
                handle: undefined, // allow drag/press anywhere in .plan-card-item
                draggable: '.plan-card-item',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function (evt) {
                    let planIds = [];
                    $('#plans-sortable .plan-card-item').each(function () {
                        planIds.push($(this).data('id'));
                    });
                    $.ajax({
                        url: '{{ url('superadmin/plans/update-order') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            plans: planIds
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                alertify.success('Plan order updated!');
                                showPlansTimeUpdated();
                                // No page reload. Just time badge.
                            } else {
                                alertify.error('Failed to update order.');
                            }
                        },
                        error: function () {
                            alertify.error('Error updating order.');
                        }
                    });
                }
            });
        }
    });
</script>
@endpush