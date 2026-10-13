@extends('superadmin.layouts.crm')

@section('content')
<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                <span>/</span>
                Manage
                <span>/</span>
                <a href="{{ route('crm.providers') }}">Services &amp; Providers</a>
                <span>/</span>
                provider
            </p>
        </div>
        <h2>{{ $provider->name }}</h2>
    </div>
    <div class="back">
        <a href="{{ route('crm.providers') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

<section class="plan-section">
    <div class="plan-heading">
        <h2>{{ $provider->name }}</h2>
        {{-- <h3>{{ $provider->description ?: 'Plans' }}</h3> --}}
    </div>

    @if(($filterMenu ?? collect())->count() > 1)
        <div class="partner-tabs-wrap">
            <div class="partner-tabs">
                @foreach($filterMenu as $item)
                    <a href="{{ $item['url'] }}" class="partner-tab {{ !empty($item['active']) ? 'active' : '' }}">
                        {{ $item['name'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @forelse($visibleCategories as $categoryName => $categoryPlans)

        <div class="plan-grid">
            @foreach($categoryPlans as $plan)
                @php
                    $price = $plan->getDiscountedPrice();
                    $features = $plan->features_array;
                    if (empty($features) && !empty($plan->description)) {
                        $features = preg_split('/\r\n|\n|\|/', (string) $plan->description);
                    }
                    $features = array_values(array_filter(array_map('trim', $features ?: [])));
                @endphp
                <div class="plan-card">
                    <div class="plan-card-header">
                        <h3>{{ $plan->name ?: $plan->title }}</h3>
                    </div>
                    <div class="plan-card-body">
                        <div class="plan-price">
                            <strong>₹ {{ number_format((float) $price, 0) }}</strong>
                            <span>Per User / Per Year</span>
                            <small>GST 18% Additional</small>
                        </div>
                        @if(count($features))
                            <ul class="plan-features">
                                @foreach($features as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <p>No plans found for this provider.</p>
    @endforelse
</section>
@endsection
