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
                Services &amp; Providers
            </p>
        </div>
        <h2>Provider</h2>
    </div>
</div>

<section class="provider-section">
    <div class="provider-grid">
        @forelse($providers as $provider)
            @php
                $isActive = in_array($provider->status, [1, '1', 'active', 'Active'], true);
            @endphp
            <div class="provider-card">
                @if($isActive)
                    <span class="provider-status">Active</span>
                @endif
                <div class="provider-logo">
                    @if($provider->logo_url)
                        <img src="{{ $provider->logo_url }}" alt="{{ $provider->name }}">
                    @endif
                </div>
                <h3>{{ $provider->name }}</h3>
                <p>{{ \Illuminate\Support\Str::limit((string) ($provider->description ?: $provider->name), 110) }}</p>
                <div class="provider-actions">
                    <a href="{{ route('crm.providers.plans', $provider) }}" class="view-plan-btn">View Plan</a>
                </div>
            </div>
        @empty
            <p>No providers found.</p>
        @endforelse
    </div>
</section>
@endsection
