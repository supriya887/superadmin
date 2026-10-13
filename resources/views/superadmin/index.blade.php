@extends('superadmin.layouts.app')
@section('content')
<div class="m-3 d-flex align-items-center">
   <div class="flex-grow-1">
      <h4 class="page-main-title m-0">Finance Dashboard</h4>
   </div>
   <div class="text-end">
      <ol class="breadcrumb m-0 py-0">
         <li class="breadcrumb-item">Tizzy</li>
         <li class="breadcrumb-item">SuperAdmin</li>
         <li class="breadcrumb-item ">Dashboard</li>
      </ol>
   </div>
</div>
<style>
   .blue-gradient{
   background: linear-gradient(135deg, #0b56b6, #2563eb);
   color: #fff;
   }
   .finance-icon{
   width: 52px;
   height: 52px;
   border-radius: 14px;
   display: flex;
   align-items: center;
   justify-content: center;
   }
   .chart-card{
   overflow: hidden;
   transition: transform 0.2s ease, box-shadow 0.2s ease;
   }
   .chart-card:hover {
   transform: translateY(-2px);
   box-shadow: 0 8px 20px rgba(0,0,0,0.08);
   }
   .apexcharts-tooltip{
   color:#000 !important;
   }
   .table-card .table th {
   font-weight: 600;
   font-size: 12px;
   text-transform: uppercase;
   letter-spacing: 0.5px;
   }
   .status-badge {
   padding: 5px 10px;
   border-radius: 20px;
   font-size: 12px;
   font-weight: 500;
   }
   .status-completed {
   background: #dcfce7;
   color: #166534;
   }
   .status-pending {
   background: #fef9c3;
   color: #854d0e;
   }
   .status-failed {
   background: #fee2e2;
   color: #991b1b;
   }
</style>

<div class="row">
   <!-- LEFT MAIN CARD - Total Revenue -->
   <div class="col-xxl-4">
      <div class="card card-h-100 chart-card border-0 shadow-sm rounded-4">
         <div class="card-header border-0 justify-content-between bg-transparent">
            <h4 class="card-title fw-semibold">Total Revenue</h4>
            <div class="dropdown ms-auto">
               <a href="#" class="btn btn-sm btn-light btn-icon" data-bs-toggle="dropdown">
               <i class="ti ti-dots-vertical fs-lg"></i>
               </a>
               <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                     <a class="dropdown-item" href="{{ route('superadmin.plans.add_selector') }}">
                     <i class="ti ti-wallet me-2"></i> Add Plan
                     </a>
                  </li>
                  <li>
                     <a class="dropdown-item" href="{{ route('superadmin.providers.index') }}">
                     <i class="ti ti-history me-2"></i> Provider
                     </a>
                  </li>
               </ul>
            </div>
         </div>
         <div class="card-body pt-0">
            <h2 class="fw-bold mb-3">₹{{ number_format($revenue ?? 0, 2) }}</h2>
            <div class="p-3 bg-light rounded-3 d-flex align-items-center">
               <div class="bg-success bg-opacity-10 rounded-circle p-3 me-3">
                  <i class="ti ti-calendar-stats text-success fs-4"></i>
               </div>
               <div>
                  <h6 class="mb-0 fw-bold">₹{{ number_format($monthlyRevenue ?? 0, 2) }}</h6>
                  <small class="text-muted">Monthly Revenue</small>
               </div>
               @php
               $revenueGrowth = $monthlyRevenue > 0 ? round((($monthlyRevenue - ($revenue / 12)) / ($revenue / 12)) * 100, 1) : 0;
               @endphp
               <div class="ms-auto text-end">
                  <span class="badge {{ $revenueGrowth >= 0 ? 'bg-success' : 'bg-danger' }}">
                  <i class="ti {{ $revenueGrowth >= 0 ? 'ti-trending-up' : 'ti-trending-down' }}"></i>
                  {{ abs($revenueGrowth) }}%
                  </span>
               </div>
            </div>
            <div class="row g-2 mt-3">
               <div class="col-6">
                  <a href="{{route('superadmin.domain_transfer_orders.index')}}" class="btn btn-primary w-100 rounded-3">
                  <i class="ti ti-transfer me-1"></i> Transfer
                  </a>
               </div>
               <div class="col-6">
                  <a href="{{ route('superadmin.credit_requests.index') }}" class="btn btn-info w-100 text-white rounded-3">
                  <i class="ti ti-credit-card me-1"></i> Credit
                  </a>
               </div>
            </div>
         </div>
      </div>
   </div>
   <!-- STATS CARDS WITH BETTER VISUALS -->
   <div class="col-xxl-8">
      <div class="row g-3">
         <!-- CARD 1: Unpaid Invoices with Progress Bar -->
         <div class="col-xl-3 col-md-6">
            <div class="card chart-card border-0 shadow-sm rounded-4 h-100">
               <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                     <div class="finance-icon bg-danger-subtle text-danger">
                        <i class="ti ti-wallet fs-1"></i>
                     </div>
                     <div class="dropdown">
                        <a href="#" class="btn btn-sm btn-light btn-icon" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical"></i>
                        </a>
                     </div>
                  </div>
                  <h3 class="fw-bold mb-1">₹{{ number_format(($invoiceStats['unpaidAmount'] ?? 0)/1000,1) }}k</h3>
                  <p class="text-muted mb-2">Unpaid Invoices</p>
                  <div class="mb-2">
                     <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $invoiceStats['unpaidCount'] ?? 0 }} Pending</span>
                        <span class="text-danger">{{ $invoiceStats['unpaidCount'] ?? 0 }}%</span>
                     </div>
                     <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-danger" style="width: {{ min($invoiceStats['unpaidCount'] ?? 0, 100) }}%"></div>
                     </div>
                  </div>
                  <div class="mt-3 pt-2 border-top">
                     <div class="d-flex justify-content-between small">
                        <span class="text-muted">vs last month</span>
                        <span class="text-danger">
                        <i class="ti ti-arrow-up"></i> +12%
                        </span>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <!-- CARD 2: Active Subscriptions with Donut Indicator -->
         <div class="col-xl-3 col-md-6">
            <div class="card chart-card border-0 shadow-sm rounded-4 h-100">
               <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                     <div class="finance-icon bg-warning-subtle text-warning">
                        <i class="ti ti-chart-arcs fs-1"></i>
                     </div>
                     <span class="badge bg-success-subtle text-success rounded-pill">
                     <i class="ti ti-refresh"></i> {{ $renewalsThisMonth ?? 0 }}
                     </span>
                  </div>
                  <h3 class="fw-bold mb-1">{{ $activeSubscriptions ?? 0 }}</h3>
                  <p class="text-muted mb-2">Active Subscriptions</p>
                  <div class="mt-3">
                     <div class="d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                           <div class="d-flex justify-content-between small mb-1">
                              <span>Renewal Rate</span>
                              <span class="text-success">78%</span>
                           </div>
                           <div class="progress" style="height: 4px;">
                              <div class="progress-bar bg-success" style="width: 78%"></div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="mt-3 pt-2 border-top">
                     <div class="d-flex justify-content-between small">
                        <span class="text-muted">Retention</span>
                        <span class="text-success">94%</span>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <!-- CARD 3: Total Customers with Growth Indicator -->
         <div class="col-xl-3 col-md-6">
            <a href="{{ route('superadmin.b2b_partners.list') }}" class="text-decoration-none text-dark">
               <div class="card chart-card border-0 shadow-sm rounded-4 h-100">
                  <div class="card-body">
                     <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="finance-icon bg-primary-subtle text-primary">
                           <i class="ti ti-users fs-1"></i>
                        </div>
                        <div class="text-end">
                           <span class="badge bg-primary-subtle text-primary rounded-pill">
                           <i class="ti ti-building"></i> B2B
                           </span>
                        </div>
                     </div>
                     <h3 class="fw-bold mb-1">{{ number_format($totalCustomers ?? 0) }}</h3>
                     <p class="text-muted mb-2">Total Customers</p>
                     <div class="row g-2 mt-2">
                        <div class="col-6">
                           <div class="bg-light rounded-3 p-2 text-center">
                              <small class="text-muted d-block">B2B</small>
                              <strong class="text-primary">{{ $totalCustomersB2B ?? 0 }}</strong>
                           </div>
                        </div>
                        <div class="col-6">
                           <div class="bg-light rounded-3 p-2 text-center">
                              <small class="text-muted d-block">B2C</small>
                              <strong class="text-info">{{ $totalCustomersB2C ?? 0 }}</strong>
                           </div>
                        </div>
                     </div>
                     <div class="mt-3 pt-2 border-top">
                        <div class="d-flex justify-content-between small">
                           <span class="text-muted">Growth Rate</span>
                           <span class="text-success">
                           <i class="ti ti-trending-up"></i> +23%
                           </span>
                        </div>
                     </div>
                  </div>
               </div>
            </a>
         </div>
    
         <!-- CARD 4: Total Orders with Daily Average -->
         <div class="col-xl-3 col-md-6">
            <a href="{{ route('superadmin.orders.index') }}" class="text-decoration-none text-dark">
               <div class="card chart-card border-0 shadow-sm rounded-4 h-100">
                  <div class="card-body">
                     <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="finance-icon bg-info-subtle text-info">
                           <i class="ti ti-shopping-cart fs-1"></i>
                        </div>
                        <span class="badge bg-info-subtle text-info rounded-pill">Lifetime</span>
                     </div>
                     <h3 class="fw-bold mb-1">{{ number_format($totalOrders ?? 0) }}</h3>
                     <p class="text-muted mb-2">Total Orders</p>
                     <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                           <span class="small text-muted">Daily Avg (This Month)</span>
                           <span class="small fw-bold">
                           @php
                           $daysInMonth = now()->daysInMonth;
                           $dailyAvg = ($monthlyOrderCount ?? 0) / $daysInMonth;
                           @endphp
                           {{ round($dailyAvg, 1) }} / day
                           </span>
                        </div>
                        <div class="d-flex justify-content-between small">
                           <span class="text-muted">Peak Day</span>
                           <span class="text-info fw-bold">{{ $peakOrderDay ?? 24 }} orders</span>
                        </div>
                     </div>
                     <div class="mt-3 pt-2 border-top">
                        <div class="d-flex justify-content-between small">
                           <span class="text-muted">Conversion</span>
                           <span class="text-success">68%</span>
                        </div>
                     </div>
                  </div>
               </div>
            </a>
         </div>
    
      </div>
   </div>
</div>
<style>
   /* Card styles */
   .chart-card {
   transition: transform 0.2s ease, box-shadow 0.2s ease;
   }
   .chart-card:hover {
   transform: translateY(-3px);
   box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important;
   }
   .finance-icon {
   width: 48px;
   height: 48px;
   border-radius: 14px;
   display: flex;
   align-items: center;
   justify-content: center;
   }
   .bg-opacity-10 {
   --bs-bg-opacity: 0.1;
   }
   .rounded-3 {
   border-radius: 12px !important;
   }
   .rounded-4 {
   border-radius: 16px !important;
   }
   .progress {
   background-color: #e9ecef;
   border-radius: 10px;
   }
   .progress-bar {
   border-radius: 10px;
   }
</style>

<!-- FINANCIAL OVERVIEW SECTION -->
<div class="row mt-1">
   <div class="col-xl-8">
      <div class="card card-h-100 border-0 shadow-sm rounded-4">
         <div class="card-header justify-content-between border-0 bg-transparent">
            <h4 class="card-title fw-semibold">Financial Overview</h4>
            {{-- 
            <div class="btn-group btn-group-sm" role="group">
               <button type="button" class="btn btn-light overview-btn" data-range="12">All</button>
               <button type="button" class="btn btn-light overview-btn" data-range="3">3M</button>
               <button type="button" class="btn btn-light overview-btn" data-range="6">6M</button>
               <button type="button" class="btn btn-light overview-btn active" data-range="12">1Y</button>
            </div>
            --}}
         </div>
         <div class="card-body p-3">
            <div class="row g-3">
               {{-- LEFT SIDE - SMALL STATS CARDS --}}
               <div class="col-lg-4">
                  <div class="row g-3">
                     {{-- Total Revenue Card --}}
                     <div class="col-12">
                        <div class="small-card p-3 rounded-3">
                           <div class="d-flex justify-content-between align-items-start">
                              <div>
                                 <p class="small-card-label mb-1">Total Revenue</p>
                                 <h4 class="small-card-value mb-0 fw-bold">₹{{ number_format($revenue ?? 0, 0) }}</h4>
                              </div>
                              <div class="small-card-icon rounded-circle bg-primary bg-opacity-10 p-2">
                                 <i class="ti ti-wallet text-primary"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                     {{-- Monthly Revenue Card --}}
                     <div class="col-6">
                        <div class="small-card p-3 rounded-3 text-center">
                           <p class="small-card-label mb-1">Monthly Revenue</p>
                           <p class="small-card-value mb-0 fw-bold text-info">₹{{ number_format($monthlyRevenue ?? 0, 0) }}</p>
                        </div>
                     </div>
                     {{-- Total Orders Card --}}
                     <div class="col-6">
                        <div class="small-card p-3 rounded-3 text-center">
                           <p class="small-card-label mb-1">Total Orders</p>
                           <p class="small-card-value mb-0 fw-bold text-primary">{{ number_format($totalOrders ?? 0) }}</p>
                        </div>
                     </div>
                     {{-- Monthly Orders Card --}}
                     <div class="col-6">
                        <div class="small-card p-3 rounded-3 text-center">
                           <p class="small-card-label mb-1">Monthly Orders</p>
                           <p class="small-card-value mb-0 fw-bold text-warning">{{ $monthlyOrderCount ?? 0 }}</p>
                        </div>
                     </div>
                     {{-- Active Partners Card --}}
                     <div class="col-6">
                        <div class="small-card p-3 rounded-3 text-center">
                           <p class="small-card-label mb-1">Active Partners</p>
                           <p class="small-card-value mb-0 fw-bold text-success">{{ $activePartners ?? 0 }}</p>
                        </div>
                     </div>
                     {{-- Total Providers Card --}}
                     <div class="col-12">
                        <div class="small-card p-3 rounded-3">
                           <div class="d-flex justify-content-between align-items-center">
                              <div>
                                 <p class="small-card-label mb-1">Total Providers</p>
                                 <p class="small-card-value mb-0 fw-bold text-danger">{{ $totalProviders ?? 0 }}</p>
                              </div>
                              <div class="small-card-icon rounded-circle bg-danger bg-opacity-10 p-2">
                                 <i class="ti ti-server text-danger"></i>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
               {{-- RIGHT SIDE - CHART --}}
               <div class="col-lg-8">
                  {{-- Custom Legend --}}
                  <div class="fin-legend mb-2">
                     <span class="fin-legend-item">
                     <span class="fin-legend-dot" style="background:#185FA5;"></span>
                     Revenue (₹)
                     </span>
                     <span class="fin-legend-item">
                     <span class="fin-legend-line" style="background:#22c55e;"></span>
                     Orders
                     </span>
                  </div>
                  {{-- Chart Canvas --}}
                  <div class="fin-chart-container">
                     <canvas id="financialOverviewChart" style="width:100%; height:100%;"></canvas>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
   <style>
      /* Small Cards Styles */
      .small-card {
      background: #fff;
      border: 1px solid rgba(0,0,0,0.05);
      transition: all 0.2s ease;
      }
      .small-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      border-color: rgba(0,0,0,0.1);
      }
      .small-card-label {
      font-size: 11px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
      }
      .small-card-value {
      font-size: 18px;
      color: #212529;
      }
      .small-card-icon i {
      font-size: 18px;
      }
      .rounded-3 {
      border-radius: 12px !important;
      }
      /* Legend */
      .fin-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 12px;
      justify-content: flex-end;
      }
      .fin-legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      font-weight: 500;
      color: #6c757d;
      }
      .fin-legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 3px;
      flex-shrink: 0;
      }
      .fin-legend-line {
      width: 24px;
      height: 3px;
      border-radius: 2px;
      flex-shrink: 0;
      }
      /* Chart Container */
      .fin-chart-container {
      position: relative;
      width: 100%;
      height: 320px;
      }
      /* Responsive */
      @media (max-width: 992px) {
      .fin-chart-container {
      height: 280px;
      margin-top: 20px;
      }
      .fin-legend {
      justify-content: center;
      }
      }
      @media (max-width: 768px) {
      .small-card-value {
      font-size: 16px;
      }
      .fin-chart-container {
      height: 260px;
      }
      }
      @media (max-width: 576px) {
      .fin-chart-container {
      height: 220px;
      }
      .small-card {
      padding: 0.75rem !important;
      }
      .small-card-value {
      font-size: 14px;
      }
      }
   </style>
   {{-- RIGHT COLUMN --}}
   <div class="col-xl-4">
      <div class="card blue-gradient border-0">
         <div class="card-body">
            <h4 class="text-white">Business Overview</h4>
            <p class="text-white text-opacity-75 mb-2">Total Providers: {{ $totalProviders ?? 0 }}</p>
            <p class="text-white text-opacity-75 mb-2">Active Providers: {{ $activeProviders ?? 0 }}</p>
            <p class="text-white text-opacity-75 mb-3">Total Partners: {{ $totalPartners ?? 0 }}</p>
            <a href="#" class="btn btn-light rounded-pill">View Details</a>
         </div>
      </div>
      <div class="card">
         <div class="card-header border-0">
            <h4 class="card-title mb-0">Quick Stats</h4>
         </div>
         <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
               <span>New Requests</span>
               <strong>{{ $newRequests ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-3">
               <span>Today's Orders</span>
               <strong>{{ $newOrders ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
               <span>Paid</span>
               <strong class="text-success">₹{{ number_format($invoiceStats['paidAmount'] ?? 0, 2) }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
               <span>Pending</span>
               <strong class="text-warning">₹{{ number_format($invoiceStats['pendingAmount'] ?? 0, 2) }}</strong>
            </div>
            <div class="d-flex justify-content-between">
               <span>Unpaid</span>
               <strong class="text-danger">₹{{ number_format($invoiceStats['unpaidAmount'] ?? 0, 2) }}</strong>
            </div>
         </div>
      </div>
   </div>
</div>
<style>
   /* Stats row */
   .fin-stats-row {
   display: grid;
   grid-template-columns: repeat(4, 1fr);
   border-bottom: 0.5px solid rgba(0,0,0,.08);
   }
   .fin-stat-cell {
   padding: 1.1rem 1.25rem;
   text-align: center;
   position: relative;
   }
   .fin-stat-cell + .fin-stat-cell::before {
   content: '';
   position: absolute;
   left: 0; top: 20%; height: 60%;
   width: 0.5px;
   background: rgba(0,0,0,.08);
   }
   .fin-stat-label {
   margin: 0 0 4px;
   font-size: 11px;
   color: #6c757d;
   text-transform: uppercase;
   letter-spacing: .5px;
   }
   .fin-stat-value {
   margin: 0;
   font-size: 16px;
   font-weight: 600;
   color: #212529;
   }
   /* Chart area */
   .fin-chart-area { padding: 1.1rem 1.25rem 1rem; }
   /* Legend */
   .fin-legend {
   display: flex;
   flex-wrap: wrap;
   gap: 14px;
   margin-bottom: 12px;
   }
   .fin-legend-item {
   display: flex;
   align-items: center;
   gap: 6px;
   font-size: 12px;
   color: #6c757d;
   }
   .fin-legend-dot {
   width: 10px; height: 10px;
   border-radius: 2px;
   flex-shrink: 0;
   }
   .fin-legend-line {
   width: 18px; height: 3px;
   border-radius: 2px;
   flex-shrink: 0;
   }
   /* Chart canvas wrapper */
   .fin-chart-container {
   position: relative;
   width: 100%;
   height: 340px;
   }
   /* Custom tooltip */
   .fin-tooltip {
   position: absolute;
   background: #fff;
   border: 0.5px solid rgba(0,0,0,.12);
   border-radius: 8px;
   padding: 10px 14px;
   pointer-events: none;
   opacity: 0;
   transition: opacity .15s;
   font-size: 12px;
   min-width: 140px;
   z-index: 10;
   box-shadow: 0 4px 16px rgba(0,0,0,.08);
   }
   .fin-tt-month {
   font-weight: 600;
   font-size: 13px;
   color: #212529;
   margin-bottom: 6px;
   border-bottom: 0.5px solid rgba(0,0,0,.06);
   padding-bottom: 5px;
   }
   .fin-tt-row {
   display: flex;
   justify-content: space-between;
   gap: 14px;
   color: #6c757d;
   margin-top: 4px;
   }
   .fin-tt-val {
   font-weight: 600;
   color: #212529;
   }
   /* Responsive */
   @media (max-width: 768px) {
   .fin-stats-row { grid-template-columns: repeat(2, 1fr); }
   .fin-stat-cell:nth-child(3)::before { display: none; }
   .fin-stat-cell:nth-child(3),
   .fin-stat-cell:nth-child(4) {
   border-top: 0.5px solid rgba(0,0,0,.08);
   }
   .fin-chart-container { height: 260px; }
   .fin-stat-value { font-size: 14px; }
   }
   @media (max-width: 480px) {
   .fin-chart-container { height: 220px; }
   .fin-chart-area { padding: .75rem; }
   .fin-stat-cell { padding: .9rem .75rem; }
   }
</style>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
   var providerChartLabels = {!! json_encode($providerChart['labels'] ?? []) !!};
   var tizzySales = {!! json_encode($providerChart['tizzy_sales'] ?? []) !!};
   var microsoftSales = {!! json_encode($providerChart['microsoft_sales'] ?? []) !!};
   var googleSales = {!! json_encode($providerChart['google_sales'] ?? []) !!};
   var chartData = {!! json_encode($chartData ?? []) !!};
   var monthlyLabels = {!! json_encode($salesGraph['labels'] ?? []) !!};
   var revenueSeries = {!! json_encode($salesGraph['salesSeries'] ?? []) !!};
   var orderSeries = {!! json_encode($salesGraph['orderCountSeries'] ?? []) !!};
   
   document.addEventListener('DOMContentLoaded', function () {
   
       // MINI CHARTS for all cards
       function miniChart(id, data, color){
           new ApexCharts(document.querySelector(id), {
               chart: {
                   type: 'area',
                   height: 70,
                   sparkline: { enabled: true },
                   toolbar: { show: false }
               },
               series: [{ data: data }],
               stroke: { curve: 'smooth', width: 2 },
               fill: { opacity: 0.2 },
               colors: [color]
           }).render();
       }
   
       // Generate sample data for new cards
       var orderTrend = [120, 135, 148, 170, 165, 180, 175, 190, 210, 225, 240, 260];
       var renewalTrend = [15, 18, 22, 25, 28, 30, 35, 32, 38, 42, 45, 48];
       var requestTrend = [8, 10, 12, 15, 14, 18, 20, 22, 25, 24, 28, 30];
       var todayOrdersTrend = [5, 8, 12, 7, 15, 10, 9, 14, 11, 13, 16, 8];
   
       // Existing mini charts
       miniChart("#total-income-chart", chartData.total_income ?? [10,20,30,40,50,45,60], "#22c55e");
       miniChart("#total-expenses-chart", chartData.total_expenses ?? [30,25,40,35,20,28,32], "#ef4444");
       miniChart("#investments-chart", chartData.investments ?? [5,15,10,20,25,18,30], "#f59e0b");
       miniChart("#savings-chart", chartData.savings ?? [8,12,18,22,30,28,35], "#2563eb");
       
       // New mini charts
       miniChart("#total-orders-chart", chartData.total_income ?? orderTrend, "#0dcaf0");
       miniChart("#renewals-chart", chartData.investments ?? renewalTrend, "#198754");
       miniChart("#requests-chart", chartData.total_expenses ?? requestTrend, "#dc3545");
       miniChart("#todays-orders-chart", chartData.savings ?? todayOrdersTrend, "#0d6efd");
   
       // Financial Overview Chart (Chart.js)
       var canvas = document.getElementById('financialOverviewChart');
       if (canvas) {
           var ctx2d = canvas.getContext('2d');
           var barGrad = ctx2d.createLinearGradient(0, 0, 0, 320);
           barGrad.addColorStop(0, 'rgba(24,95,165,.45)');
           barGrad.addColorStop(1, 'rgba(24,95,165,.04)');
           var lineGrad = ctx2d.createLinearGradient(0, 0, 0, 320);
           lineGrad.addColorStop(0, 'rgba(34,197,94,.22)');
           lineGrad.addColorStop(1, 'rgba(34,197,94,.01)');
   
           new Chart(canvas, {
               data: {
                   labels: monthlyLabels,
                   datasets: [
                       {
                           type: 'bar',
                           label: 'Revenue',
                           data: revenueSeries,
                           backgroundColor: barGrad,
                           hoverBackgroundColor: '#378ADD',
                           borderColor: '#185FA5',
                           borderWidth: 0,
                           borderRadius: { topLeft: 6, topRight: 6 },
                           borderSkipped: false,
                           yAxisID: 'yRev',
                           order: 2
                       },
                       {
                           type: 'line',
                           label: 'Orders',
                           data: orderSeries,
                           borderColor: '#22c55e',
                           borderWidth: 2.5,
                           pointBackgroundColor: '#22c55e',
                           pointBorderColor: '#fff',
                           pointBorderWidth: 2,
                           pointRadius: 4,
                           pointHoverRadius: 6,
                           tension: 0.45,
                           fill: true,
                           backgroundColor: lineGrad,
                           yAxisID: 'yOrd',
                           order: 1
                       }
                   ]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   interaction: { mode: 'index', intersect: false },
                   plugins: { legend: { display: false }, tooltip: { enabled: false } },
                   scales: {
                       x: {
                           grid: { color: 'rgba(0,0,0,.05)', drawTicks: false },
                           border: { display: false },
                           ticks: { color: '#9ca3af', font: { size: 11 }, padding: 8, autoSkip: false, maxRotation: 0 }
                       },
                       yRev: {
                           position: 'left',
                           grid: { color: 'rgba(0,0,0,.05)', drawTicks: false },
                           border: { display: false },
                           ticks: {
                               color: '#9ca3af',
                               font: { size: 11 },
                               padding: 8,
                               callback: function (v) {
                                   if (v >= 100000) return '₹' + (v / 100000).toFixed(1) + 'L';
                                   if (v >= 1000) return '₹' + (v / 1000).toFixed(0) + 'k';
                                   return '₹' + v;
                               }
                           }
                       },
                       yOrd: {
                           position: 'right',
                           grid: { display: false },
                           border: { display: false },
                           ticks: { color: '#9ca3af', font: { size: 11 }, padding: 8, callback: function (v) { return Math.round(v); } }
                       }
                   }
               }
           });
       }
   });
   
</script>
@endpush
<!-- RECENT TRANSACTIONS TABLE -->
<div class="row mt-4">
   <div class="col-12">
      <div class="card table-card">
         <div class="card-header">
            <h4 class="card-title mb-0">Recent Transactions</h4>
            <small class="text-muted">{{ $txRecent->count() }} Transactions</small>
         </div>
         <div class="card-body p-0">
            <div class="table-responsive">
               <table class="table table-hover mb-0">
                  <thead class="table-light">
                     <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Plan / Domain</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Action</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($txRecent as $order)
                     <tr>
                        <td>
                           <span class="fw-medium">#{{ $order->id ?? 'N/A' }}</span>
                        </td>
                        <td>
                           <div class="d-flex align-items-center">
                              <div class="avatar-xs me-2">
                                 <span class="avatar-title bg-light rounded-circle">
                                 {{ strtoupper(substr(($order->customer_name ?? $order->domain_name ?? '-'), 0, 1)) }}
                                 </span>
                              </div>
                              <span>{{ $order->customer_name ?? $order->domain_name ?? '-' }}</span>
                           </div>
                        </td>
                        <td style="max-width: 250px;">
                           <strong>
                           {{ $order->plan->title }}
                           @if(!empty($order->plan->name))
                           ({{ $order->plan->name }})
                           @endif
                           </strong>
                           <small class="d-block text-muted text-break">
                           {{ str_replace(',', ', ', $order->domain_name ?? '-') }}
                           </small>
                        </td>
                        <td class="fw-semibold">
                           ₹{{ number_format($order->total_amount ?? 0, 2) }}
                        </td>
                        <td>
                           {{ isset($order->created_at) ? \Carbon\Carbon::parse($order->created_at)->format('d M, Y') : '-' }}
                           <small class="d-block text-muted">{{ isset($order->created_at) ? \Carbon\Carbon::parse($order->created_at)->format('h:i A') : '' }}</small>
                        </td>
                        <td>{{ $order->provider_name ?? '-' }}</td>
                        <td>
                           @php
                           $status = strtolower($order->status ?? 'pending');
                           $badgeClass = match($status) {
                           'completed', 'success', 'paid', 'active' => 'status-completed',
                           'pending_payment' => 'bg-danger text-white',
                           'pending' => 'status-pending',
                           'failed', 'cancelled' => 'status-failed',
                           default => 'bg-dark text-white'
                           };
                           $statusText = ucwords(str_replace('_', ' ', $status));
                           @endphp
                           <span class="status-badge {{ $badgeClass }}">
                           {{ $statusText }}
                           </span>
                        </td>
                        <td>
                           <a href="{{ url('superadmin/orders-details/' . ($order->id ?? 0)) }}" 
                              class="btn btn-sm btn-light">
                           <i class="ti ti-eye"></i>
                           </a>
                        </td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                           <i class="ti ti-database-off fs-2 d-block mb-2"></i>
                           No transactions found
                        </td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
<!-- RENEWALS TABLE - SAME AS RENEWALS PAGE (ALL ROWS WHITE BG) -->
<div class="row mt-4">
   <div class="col-12">
      <div class="card table-card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <div>
               <h4 class="card-title mb-0">Renewals Overview</h4>
               <small class="text-muted">Upcoming and recent renewals</small>
            </div>
            <a href="{{ route('superadmin.renewals.index') }}" class="btn btn-sm btn-primary">
            <i class="ti ti-calendar-stats"></i> Manage All Renewals
            </a>
         </div>
         <div class="card-body p-0">
            <div class="table-responsive">
               <table class="table mb-0 align-middle" id="dashboardRenewalsTable">
                  <thead class="table-light">
                     <tr>
                        <th>Partner</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Domain</th>
                        <th>Plan</th>
                        <th class="text-center">Sub. Start</th>
                        <th class="text-center">Sub. End</th>
                        <th class="text-center">Renewal Date</th>
                        <th class="text-center">Days Left</th>
                        <th class="text-end">Action</th>
                     </tr>
                  </thead>
                  <tbody>
                     @forelse($dashboardRenewalsData ?? [] as $renewal)
                     @php
                     $daysLeft = $renewal->days_left;
                     if($daysLeft !== null && $daysLeft <= 7) {
                     $badgeClass = 'bg-danger';
                     $badgeText = $daysLeft . ' days';
                     } elseif($daysLeft !== null && $daysLeft <= 15) {
                     $badgeClass = 'bg-warning text-dark';
                     $badgeText = $daysLeft . ' days';
                     } elseif($daysLeft !== null && $daysLeft <= 30) {
                     $badgeClass = 'bg-info';
                     $badgeText = $daysLeft . ' days';
                     } elseif($daysLeft !== null && $daysLeft > 30) {
                     $badgeClass = 'bg-secondary';
                     $badgeText = $daysLeft . ' days';
                     } elseif($daysLeft !== null && $daysLeft <= 0) {
                     $badgeClass = 'bg-danger';
                     $badgeText = 'Expired';
                     } else {
                     $badgeClass = 'bg-secondary';
                     $badgeText = '-';
                     }
                     @endphp
                     <tr style="background-color: #ffffff !important;">
                        <td><strong>{{ $renewal->partner_name }}</strong></td>
                        <td>{{ $renewal->customer_name }}</td>
                        <td>{{ $renewal->customer_email }}</td>
                        <td>{{ $renewal->customer_mobile }}</td>
                        <td>{{ $renewal->domain_list }}</td>
                        <td>{{ $renewal->plan_name }}</td>
                        <td class="text-center">{{ $renewal->subscription_start_date }}</td>
                        <td class="text-center">{{ $renewal->subscription_end_date }}</td>
                        <td class="text-center">{{ $renewal->renewal_date_display }}</td>
                        <td class="text-center">
                           <span class="badge {{ $badgeClass }}">{{ $badgeText }}</span>
                        </td>
                        <td class="text-end">
                           <a href="{{ $renewal->renew_url }}" class="btn btn-sm btn-primary" title="Renew">
                           <i class="ti ti-refresh"></i>
                           </a>
                        </td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="11" class="text-center py-4 text-muted">
                           <i class="ti ti-calendar-off fs-2 d-block mb-2"></i>
                           No renewals found
                        </td>
                     </tr>
                     @endforelse
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
<!-- PARTNERS PENDING APPROVAL -->
@if(($partnersPendingApproval ?? collect())->count() > 0)
<div class="row mt-4">
   <div class="col-12">
      <div class="card table-card">
         <div class="card-header d-flex justify-content-between align-items-center">
            <div>
               <h4 class="card-title mb-0">Partners Pending Approval</h4>
               <small class="text-muted">{{ $partnersPendingApproval->count() }} requests awaiting review</small>
            </div>
            <a href="{{route('superadmin.partner_requests.index')}}" class="btn btn-sm btn-primary">View All</a>
         </div>
         <div class="card-body p-0">
            <div class="table-responsive">
               <table class="table table-hover mb-0">
                  <thead class="table-light">
                     <tr>
                        <th>Name</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Requested On</th>
                        <th>Action</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($partnersPendingApproval as $partner)
                     <tr>
                        <td>
                           <div class="d-flex align-items-center">
                              <div class="avatar-xs me-2">
                                 <span class="avatar-title bg-light rounded-circle">
                                 {{ strtoupper(substr($partner->name ?? '-', 0, 1)) }}
                                 </span>
                              </div>
                              <strong>{{ $partner->name ?? '-' }}</strong>
                           </div>
                        </td>
                        <td>{{ $partner->company_name ?? '-' }}</td>
                        <td>{{ $partner->email ?? '-' }}</td>
                        <td>{{ $partner->mobile ?? '-' }}</td>
                        <td>
                           {{ isset($partner->created_at) ? \Carbon\Carbon::parse($partner->created_at)->format('d M Y') : '-' }}
                        </td>
                        <td>
                           <a href="{{ url('superadmin/partner-requests/' . ($partner->id ?? 0)) }}" 
                              class="btn btn-sm btn-primary">
                           Review
                           </a>
                        </td>
                     </tr>
                     @endforeach
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
@endif
@endsection