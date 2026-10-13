@extends('admin.layouts.app')

@section('title', 'Services')

@section('css')
<style>
    .services-nav-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 4px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        margin-bottom: 30px;
        border: 1px solid #f0f0f0;
    }
    .services-tabs {
        display: flex;
        gap: 25px;
        margin-bottom: 0;
        border-bottom: none;
        padding-bottom: 0;
    }
    .service-tab {
        padding: 15px 5px;
        font-size: 13px;
        font-weight: 500;
        color: #666;
        text-decoration: none;
        transition: 0.3s;
        position: relative;
        border: none;
        background: none;
    }
    .service-tab:hover {
        color: #0056b3;
    }
    .service-tab.active {
        color: #0056b3;
        font-weight: 700;
        background: none;
    }
    .service-tab.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: #0056b3;
        border-radius: 3px 3px 0 0;
    }
    
    .search-box {
        position: relative;
        width: 300px;
    }
    .search-box input {
        width: 100%;
        padding: 8px 15px 8px 35px;
        border-radius: 6px;
        border: 1px solid #e1e8f0;
        font-size: 13px;
        outline: none;
        transition: 0.3s;
    }
    .search-box input:focus {
        border-color: #0056b3;
        box-shadow: 0 0 0 3px rgba(0,86,179,0.1);
    }
    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
        font-size: 14px;
    }
    
    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 14px;
        padding: 20px 0;
    }
    
    .plan-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        transition: transform 0.3s;
        display: flex;
        flex-direction: column;
        border: 1px solid #e1e8f0;
    }
    .plan-card:hover {
        transform: translateY(-5px);
    }
    
    .plan-header {
        background: #cde2f9;
        padding: 14px 12px;
        text-align: center;
    }
    .plan-title {
        font-size: 20px;
        font-weight: 600;
        color: #000;
        margin: 0;
    }
    
    .price-section {
        padding: 13px 20px;
        text-align: center;
        border-bottom: 1px solid #f0f0f0;
    }
    .plan-price {
        font-size: 29px;
        font-weight: 600;
        color: #0056b3;
        margin-bottom: 5px;
        display: block;
    }
    .plan-duration {
        font-size: 14px;
        color: #555;
        display: block;
        margin-bottom: 2px;
    }
    .gst-note {
        font-size: 12px;
        color: #999;
        display: block;
        margin-bottom: 20px;
    }
    
    .btn-add-cart {
        background: #0056b3;
        color: #fff;
        border: none;
        padding: 9px 40px;
        border-radius: 50px;
        font-weight: 500;
        font-size: 13px;
        transition: 0.3s;
        width: 80%;
        margin: 0 auto;
    }
    .btn-add-cart:hover {
        background: #00428a;
    }
    
    .plan-body {
        padding: 25px 16px;
    }
    .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .feature-item {
        font-size: 13px;
        color: #444;
        margin-bottom: 2px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .feature-item i {
        color: #00c292;
        font-size: 10px;
    }

    @media (min-width: 1400px) {
        .pricing-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
</style>
@endsection

@section('content')
<div class="page-header" data-aos="fade-up" data-aos-duration="600">
    <h1 class="page-title">Services & Plans</h1>
</div>

<div class="services-nav-bar" data-aos="fade-up" data-aos-duration="600" data-aos-delay="100">
    <div class="services-tabs">
        <a href="javascript:void(0)" class="service-tab active" id="#tizzy_mail">Tizzy Mail</a>
        <a href="javascript:void(0)" class="service-tab" id="#microsoft_365">Microsoft 365</a>
        <a href="javascript:void(0)" class="service-tab" id="#google_workspace">Google Workspace</a>
    </div>
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search Plan..." id="planSearch">
    </div>
</div>

<div class="pricing-grid">
    @forelse($mail as $index => $plan)
    <div class="plan-card" id="tizzy_mail" data-aos="fade-up" data-aos-duration="600" data-aos-delay="{{ 200 + ($index * 100) }}" >
        <div class="plan-header">
            <h2 class="plan-title">{{ $plan['name'] }}</h2>
        </div>
        <div class="price-section">
            <span class="plan-price">₹{{ number_format($plan['price'], 0) }}</span>
            <span class="plan-duration">Per User / Per Year</span>
            <span class="gst-note">GST 18% Additional</span>
            <a href="#" class="btn-add-cart">Add to Cart</a>
        </div>
        <div class="plan-body">
            <ul class="feature-list">
                @php
                   $features = DB::table('plan_features')->where('plan_id', $plan['id'])->get()->toArray();
                @endphp
                @foreach ($features as $feature)
                <li class="feature-item">
                    <i class="fas fa-check"></i>
                    <span>{{ $feature['feature'] }}</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    @empty
    <div class="col-12 text-center py-5">
        <p class="text-muted">No plans available for this category.</p>
    </div>
    @endforelse
</div>
@endsection

@section('js')
<script>
    $(document).ready(function() {
        $('#planSearch').on('input', function() {
            var value = $(this).val().toLowerCase();
            $('.plan-card').filter(function() {
                var title = $(this).find('.plan-title').text().toLowerCase();
                $(this).toggle(title.indexOf(value) > -1);
            });
        });
    });
</script>
@endsection
