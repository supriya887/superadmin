@extends('superadmin.layouts.crm')

@section('content')

<div class="crm-page">
    <div class="page-header">
        <div class="">
            <div class="breadcrumb welcome-text">
                <p><a href="{{ route('crm.dashboard') }}">Dashboard</a><span>/</span> </p>
            </div>
            <h2>Follow-Ups </h2>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success success-alert">
        {{ session('success') }}
    </div>
    @endif

    <section class="common-table-section">

        <div class="crm-lead-tabs">

            {{-- All Follow-Ups --}}
            <a href="{{ route('crm.followups', array_filter([
                'search' => request('search')
            ])) }}"
                class="{{ !request('filter') ? 'active' : '' }}">
                All Follow-Ups
            </a>

            {{-- Overdue --}}
            <a href="{{ route('crm.followups', array_filter([
                'filter' => 'overdue',
                'search' => request('search')
            ])) }}"
                class="{{ request('filter') === 'overdue' ? 'active' : '' }}">
                Overdue
            </a>

            {{-- Today --}}
            <a href="{{ route('crm.followups', array_filter([
                'filter' => 'today',
                'search' => request('search')
            ])) }}"
                class="{{ request('filter') === 'today' ? 'active' : '' }}">
                Today
            </a>

            {{-- Upcoming --}}
            <a href="{{ route('crm.followups', array_filter([
                'filter' => 'upcoming',
                'search' => request('search')
            ])) }}"
                class="{{ request('filter') === 'upcoming' ? 'active' : '' }}">
                Upcoming
            </a>

        </div>


        <div class="common-table-card">

            {{-- Header --}}
            <div class="common-table-header crm-lead-header">

                <div class="crm-lead-title">

                    <p>
                        Showing
                        <strong>
                            {{ $leads->firstItem() ?? 0 }}
                            -
                            {{ $leads->lastItem() ?? 0 }}
                        </strong>
                        from
                        <strong>
                            {{ $leads->total() }}
                        </strong>
                        Follow-Ups
                    </p>

                    <span class="header-divider"></span>

                    <label class="items-selected">

                        <input type="checkbox" id="selectAll">

                        <div>
                            <span id="selectedLeadCount">0</span>
                            /{{ $leads->count() }} Items Selected
                        </div>

                    </label>

                </div>


                {{-- Search --}}
                <form method="GET"
                    action="{{ route('crm.followups') }}"
                    class="table-search">

                    @if(request('filter'))
                    <input type="hidden"
                        name="filter"
                        value="{{ request('filter') }}">
                    @endif

                    <input
                        type="text"
                        id="partnerSearch"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search Follow-Ups">

                    <i data-lucide="search"></i>

                </form>

            </div>


            {{-- Filter --}}
            <div class="table-filter">

                <div class="border-line"></div>

                <button class="filter-btn"
                    id="toggleFilter"
                    type="button">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="12"
                        height="9"
                        viewBox="0 0 12 9"
                        fill="none">

                        <path
                            d="M0.75 0.75H10.75M2.75 4.25H8.75M4.25 7.75H7.25"
                            stroke="white"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round" />

                    </svg>

                    <span>Filters</span>

                </button>

            </div>


            {{-- Table --}}
            <table class="crm-lead-table">

                <thead>

                    <tr class="table-head">

                        <td class="select-col">

                            <input type="checkbox"
                                id="selectAllTable">

                        </td>

                        <td>
                            Customer Name
                        </td>

                        <td>
                            Contact Information
                        </td>

                        <td class="status-col">
                            Status
                        </td>

                        <td>
                            Follow Up Date
                        </td>

                    </tr>

                </thead>


                <tbody class="table-body">

                    @forelse($leads as $lead)

                    @php

                    /*
                    * Status
                    */
                    $statusLabel = $lead->lead_status ?: 'New';

                    $status = strtolower(trim($statusLabel));

                    /*
                    * Normalize Quote statuses
                    */
                    if (in_array($status, [
                    'quote sent',
                    'quotation sent',
                    'quote send'
                    ], true)) {

                    $statusLabel = 'Quote Send';
                    $status = 'quote send';

                    }


                    /*
                    * Status Badge Class
                    */
                    $badgeClass = match ($status) {

                    'new'
                    => 'new',

                    'contacted'
                    => 'contacted',

                    'qualified'
                    => 'qualified',

                    'negotiation',
                    'in process'
                    => 'negotiation',

                    'quotation sent',
                    'quote sent',
                    'quote send',
                    'quote approved'
                    => 'quotation-sent',

                    'won',
                    'deal done'
                    => 'won',

                    'lost',
                    'not interested'
                    => 'lost',

                    'nurturing'
                    => 'nurturing',

                    default
                    => 'new',

                    };


                    /*
                    * Avatar
                    */
                    $companyName = $lead->company_name
                    ?: $lead->name
                    ?: 'L';

                    $initial = strtoupper(
                    substr($companyName, 0, 1)
                    );


                    /*
                    * Avatar Colors
                    */
                    $avatarColor = isset($avatarColors)
                    ? $avatarColors[$lead->id % count($avatarColors)]
                    : 'primary';


                    /*
                    * Assigned User
                    */
                    $assigned = $lead->assigned_to
                    ?: 'Unassigned';

                    $assignedInitial = strtoupper(
                    substr($assigned, 0, 1)
                    );

                    @endphp


                    <tr
                        class="crm-lead-row table-row"
                        data-href="{{ route('crm.followups.show', $lead->id) }}"
                        style="cursor:pointer;">

                        {{-- Checkbox --}}
                        <td class="select-col"
                            data-label="">

                            <input
                                type="checkbox"
                                name="selected_leads[]"
                                value="{{ $lead->id }}">

                        </td>


                        {{-- Customer --}}
                        <td class="customer"
                            data-label="Customer">

                            <div class="avatar {{ $avatarColor }}">
                                {{ $initial }}
                            </div>

                            <div>

                                <h5>
                                    {{ $lead->company_name }}
                                </h5>

                                <p>

                                    {{ $lead->name }}

                                    @if($lead->designation)
                                    - {{ $lead->designation }}
                                    @endif

                                </p>

                            </div>

                        </td>


                        {{-- Contact --}}
                        <td class="contact-info"
                            data-label="Contact">

                            <div>

                                <h5>
                                    {{ $lead->email }}
                                </h5>

                                <p>
                                    {{ $lead->mobile }}
                                </p>

                            </div>

                        </td>


                        {{-- Status --}}
                        <td class="status-col"
                            data-label="Status">

                            <span class="badge {{ $badgeClass }}">
                                {{ $statusLabel }}
                            </span>

                        </td>


                        {{-- Follow Up --}}
                        <td class="plan-action"
                            data-label="Follow Up Date">

                            <div class="assigned-user">

                                <div class="user-info">

                                    <div class="avatar-flex">

                                        <div class="mini-avatar primary">
                                            {{ $assignedInitial }}
                                        </div>

                                        <h5>
                                            {{ $assigned }}
                                        </h5>

                                    </div>


                                    <small>

                                        @if($lead->follow_up_date)

                                        Follow up on&nbsp;

                                        <strong>
                                            {{ \Carbon\Carbon::parse($lead->follow_up_date)->format('d M, Y g:i A') }}
                                        </strong>

                                        @else

                                        Added&nbsp;

                                        <strong>
                                            {{ \Carbon\Carbon::parse($lead->created_at)->format('d M, Y h:i A') }}
                                        </strong>

                                        @endif

                                    </small>

                                </div>


                                {{-- Action --}}
                                <div class="actions">

                                    <a
                                        href="{{ route('crm.followups.show', $lead->id) }}"
                                        class="action-btn">

                                        <i data-lucide="chevron-right"></i>

                                    </a>

                                </div>

                            </div>

                        </td>

                    </tr>


                    @empty

                    <tr class="crm-lead-row table-row">

                        <td class="select-col"></td>

                        <td class="customer"
                            colspan="4">

                            No follow-ups found.

                        </td>

                    </tr>

                    @endforelse

                </tbody>

            </table>


            {{-- Pagination --}}
            <div class="table-pagination">

                @if($leads->onFirstPage())

                <a href="#"
                    class="page-btn">

                    <i data-lucide="chevron-left"></i>

                </a>

                @else

                <a
                    href="{{ $leads->previousPageUrl() }}"
                    class="page-btn">

                    <i data-lucide="chevron-left"></i>

                </a>

                @endif


                @php
                $lastPage = max($leads->lastPage(), 1);
                @endphp


                @for(
                $page = 1;
                $page <= min($lastPage, 5);
                    $page++
                    )

                    <a
                    href="{{ $leads->appends(request()->query())->url($page) }}"
                    class="page-btn {{ $page == $leads->currentPage() ? 'active' : '' }}">

                    {{ $page }}

                    </a>

                    @endfor


                    @if($leads->hasMorePages())

                    <a
                        href="{{ $leads->nextPageUrl() }}"
                        class="page-btn">

                        <i data-lucide="chevron-right"></i>

                    </a>

                    @else

                    <a href="#"
                        class="page-btn">

                        <i data-lucide="chevron-right"></i>

                    </a>

                    @endif

            </div>

        </div>

    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('tr.table-row[data-href]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, label')) {
                return;
            }
            window.location.href = row.getAttribute('data-href');
        });
    });
});
</script>
@endsection
