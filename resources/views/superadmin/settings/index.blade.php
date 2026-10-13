@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div>
            <br>
            <br>
            <h4 class="content-title mb-2">{{ $title ?? 'Admin Settings' }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item active">Admin Settings</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-body">
            @php
                $savedLogo = (string) data_get($settings, 'logo', '');
                $logoSrc = '';
                if ($savedLogo !== '') {
                    if (str_starts_with($savedLogo, 'http://') || str_starts_with($savedLogo, 'https://')) {
                        $logoSrc = $savedLogo;
                    } elseif (str_starts_with($savedLogo, 'settings/') || str_starts_with($savedLogo, 'providers/')) {
                        $logoSrc = asset('storage/app/public/' . ltrim($savedLogo, '/'));
                    } else {
                        $logoSrc = asset('public/' . ltrim($savedLogo, '/'));
                    }
                }
            @endphp
            <form method="POST" action="{{ route('superadmin.settings.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company Title</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                            value="{{ old('title', data_get($settings, 'title')) }}" placeholder="Company title">
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Logo</label>
                        @if($logoSrc !== '')
                            <div class="mb-2">
                                <img src="{{ $logoSrc }}" alt="Current Logo" style="max-height: 56px; border: 1px solid #ddd; border-radius: 6px; padding: 4px;">
                            </div>
                        @endif
                        <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.svg">
                        @error('logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
                            value="{{ old('address', data_get($settings, 'address', data_get($settings, 'addres'))) }}" placeholder="Address">
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pincode</label>
                        <input type="text" name="pincode" class="form-control @error('pincode') is-invalid @enderror"
                            value="{{ old('pincode', data_get($settings, 'pincode')) }}" placeholder="Pincode">
                        @error('pincode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Google Transfer Code</label>
                        <input type="text" name="google_transfer_code" class="form-control @error('google_transfer_code') is-invalid @enderror"
                            value="{{ old('google_transfer_code', data_get($settings, 'google_transfer_code')) }}"
                            placeholder="Google Workspace transfer code">
                        @error('google_transfer_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Microsoft Transfer Code</label>
                        <input type="text" name="microsoft_transfer_code" class="form-control @error('microsoft_transfer_code') is-invalid @enderror"
                            value="{{ old('microsoft_transfer_code', data_get($settings, 'microsoft_transfer_code')) }}"
                            placeholder="Microsoft 365 transfer code">
                        @error('microsoft_transfer_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tizzy Code</label>
                        <input type="text" name="transfer_code" class="form-control @error('transfer_code') is-invalid @enderror"
                            value="{{ old('transfer_code', data_get($settings, 'transfert_code', data_get($settings, 'transfer_code'))) }}" placeholder="Tizzy transfer code">
                        @error('transfer_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" class="form-control @error('invoice_prefix') is-invalid @enderror"
                            value="{{ old('invoice_prefix', data_get($settings, 'invoice_prefix')) }}"
                            placeholder="Example: INV">
                        @error('invoice_prefix')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subscription Prefix</label>
                        <input type="text" name="subscription_prefix" class="form-control @error('subscription_prefix') is-invalid @enderror"
                            value="{{ old('subscription_prefix', data_get($settings, 'subscription_prefix', 'SUB')) }}"
                            placeholder="Example: SUB">
                        @error('subscription_prefix')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-3">
                        <div class="border rounded p-3 bg-light">
                            <h6 class="mb-1">Order prefixes by type</h6>
                           <br>
                           <br>
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <label class="form-label">New Order Prefix</label>
                                    <input type="text" name="new_order_prefix" class="form-control @error('new_order_prefix') is-invalid @enderror"
                                        value="{{ old('new_order_prefix', data_get($settings, 'new_order_prefix')) }}"
                                        placeholder="Example: TZN">
                                    @error('new_order_prefix')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3 mb-md-0">
                                    <label class="form-label">Renewal Prefix</label>
                                    <input type="text" name="renewal_prefix" class="form-control @error('renewal_prefix') is-invalid @enderror"
                                        value="{{ old('renewal_prefix', data_get($settings, 'renewal_prefix')) }}"
                                        placeholder="Example: TZR">
                                    @error('renewal_prefix')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-0">
                                    <label class="form-label">Upgrade/Downgrade Prefix</label>
                                    <input type="text" name="upgrade_prefix" class="form-control @error('upgrade_prefix') is-invalid @enderror"
                                        value="{{ old('upgrade_prefix', data_get($settings, 'upgrade_prefix')) }}"
                                        placeholder="Example: TZU">
                                    @error('upgrade_prefix')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-2">
                    <button type="submit" class="btn btn-success px-4">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
