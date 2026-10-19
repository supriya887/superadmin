@extends('superadmin.layouts.crm')

@section('content')
<div class="crm-reports-page">
    <div class="page-header">
        <div>
            <div class="breadcrumb welcome-text">
                <p>
                    <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('crm.reports') }}">Reports</a>
                    <span>/</span>
                </p>
            </div>
            <h2>{{ $title }}</h2>
        </div>
        <div class="back">
            <a href="{{ route('crm.reports') }}" class="back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5" />
                    <path d="M12 19l-7-7 7-7" />
                </svg>
                <span>Back</span>
            </a>
        </div>
    </div>

    <section class="common-table-section">
        <div class="common-table-card rpt-card">
            <div class="common-table-header">
                <div>
                    <h3>{{ $title }}</h3>
                    <p class="rpt-sub">{{ $rows->count() }} records</p>
                </div>
            </div>
            <div class="crm-lead-table-wrap">
                <table class="rpt-emp-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>
                                        @if (!empty($cell['href']))
                                            <a href="{{ $cell['href'] }}">{{ $cell['text'] }}</a>
                                        @else
                                            {{ $cell['text'] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection
