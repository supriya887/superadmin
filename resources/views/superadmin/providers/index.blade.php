@extends('superadmin.layouts.app')

@section('content')
<style>
    .mobile-plan-badge{
        position: absolute;
    right: 44%;
    /* top: -9px; */
    z-index: 6;
    /* min-width: 52px; */
    height: 24px;
    border-radius: 24px;
    padding: 0 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: #fff;
    background: #0b56b6;
    }
</style>
    <div class="providers-page mt-4">
        <div class="card providers-card">
            <div class="card-header providers-card__header">
                <h3 class="providers-card__title mb-0">{{ $pageTitle }}</h3>
                <a href="{{ route('superadmin.providers.form') }}" class="btn btn-primary providers-card__add-btn">Add New
                    Provider</a>
            </div>
        </div>
        {{-- <div class="card providers-card"> --}}
            {{-- <div class="card-body providers-card__body"> --}}
                <div class="row g-3 providers-grid mt-1">
                    @foreach ($providers as $provider)
                        @php
                            if ($provider->has_variant == 'yes') {
                                $url = url('superadmin/providers-variants', $provider->id);
                            } else {
                                $url = url('superadmin/plans/' . $provider->id . '/0');
                            }

                            $logoFile = basename(str_replace('\\', '/', trim((string) ($provider->logo ?? ''))));
                            $logoUrl = $logoFile !== ''
                                ? route('superadmin.providers.logo', ['filename' => $logoFile])
                                : '';
                        @endphp
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="provider-card" data-provider-id="{{ $provider->id }}"
                                onclick="window.location.href='{{ $url }}'">
                                <div class="action-dropdown dropdown" onclick="event.stopPropagation();">
                                    <button class="btn dropdown-toggle" type="button"
                                        id="dropdownMenuButton{{ $provider->id }}" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end"
                                        aria-labelledby="dropdownMenuButton{{ $provider->id }}">
                                        <li><a class="dropdown-item"
                                                href="{{ url('superadmin/providers', $provider->id) }}">Edit Provider</a>
                                        </li>
                                        {{-- <li><a class="dropdown-item" href="{{ url('superadmin/providers-variants', $provider->id) }}">Variants</a></li> --}}
                                        {{-- <li><a class="dropdown-item" href="{{ $url }}">Plans</a></li> --}}
                                    </ul>
                                </div>
                                @if($logoUrl !== '')
                                    <img src="{{ $logoUrl }}" alt="{{ $provider->name }} Logo">
                                @endif
                                <h3>{{ $provider->name }}</h3>

                                @php
                                    $statusRaw = $provider->status;
                                    $isActive = in_array($statusRaw, [1, '1', 'active', 'Active', 'enabled', 'Enabled'], true);
                                @endphp
                                

                                <p class="provider-card__description text-muted mb-0 mt-3 ">
                                    {{ \Illuminate\Support\Str::limit((string) ($provider->description ?? '-'), 90) }}
                                </p>
                                <div class="mt-3">
                                    <span class="mobile-plan-badge">
                                        {{ $isActive ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
    </div>

    <script></script>
@endsection 
