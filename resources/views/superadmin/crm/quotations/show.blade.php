@extends('superadmin.layouts.crm')

@section('content')
@php
$logo = asset('superadmin/crm/images/logo.png');
$quotePages = $quotePages ?? collect([['quotation' => $quotation, 'sheets' => $sheets]]);
$showArrow = $quotePages->count() > 1;
@endphp

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                <span>/</span>
                <a href="{{ route('crm.quotations') }}">Quotations</a>
            </p>
        </div>
        <h2>Quotations Detail <span class="badge approved">{{ $quotation->dealTypeLabel() }}</span></h2>
    </div>

    <div class="back">
        <a href="{{ route('crm.quotations') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg"
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif
<section class="common-form pb-0 quotation-detail">

    @foreach($quotePages as $page)
    @include('superadmin.crm.quotations._preview', [
    'pageQuotation' => $page['quotation'],
    'pageSheets' => $page['sheets'],
    'isOpen' => (int) $page['quotation']->id === (int) $quotation->id,
    'showArrow' => $showArrow,
    'pageLatest' => $page['quotation']->isLatest(),
    'isLocked' => $page['quotation']->isLocked(),
    'logo' => $logo,
    ])
    @endforeach

</section>
@endsection
