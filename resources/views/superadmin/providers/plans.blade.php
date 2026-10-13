@extends('superadmin.layouts.app')

@section('content')
<div class="card">
    <div class="card-header">
        <h3>{{ $pageTitle }} for Provider ID: {{ $providerId }}</h3>
    </div>
    <div class="card-body">
        <p>This is the Plans Selection Page for provider ID {{ $providerId }}.</p>
        <p>Implement plan listing and selection here.</p>
        <a href="{{ route('superadmin.providers.index') }}" class="btn btn-primary">Back to Providers</a>
    </div>
</div>
@endsection
