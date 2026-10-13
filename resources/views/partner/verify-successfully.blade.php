@extends('partner.render')

@section('metaTitle')
    <title>Verification Successful | Tizzy Group</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .success-icon-wrapper {
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-heading {
            color: #43475b;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .success-description {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
            text-align: center;
        }
        .btn-register {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            color: white;
            padding: 0.6rem 2rem;
            font-size: 0.85rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .text-center-wrapper {
            text-align: center;
        }
    </style>
@endsection

@section('Body')
    <div data-aos="zoom-in" data-aos-duration="800">
        <div class="success-icon-wrapper" data-aos="scale-up" data-aos-delay="200">
            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#00bcd4" stroke-width="1.5"/>
                <path d="M8 12L11 15L16 9" stroke="#00bcd4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="12" stroke="#00bcd4" stroke-width="1" stroke-dasharray="4 4" stroke-opacity="0.5"/>
            </svg>
        </div>
        
        <h1 class="success-heading" data-aos="fade-up" data-aos-delay="400">
            Your email has been verified Successfully.
        </h1>
        
        <p class="success-description" data-aos="fade-up" data-aos-delay="600">
            You will receive an email confirmation within 2 working days,<br>
            post verification of your account details.
        </p>
        
        <div class="text-center-wrapper" data-aos="fade-up" data-aos-delay="800">
            <a href="{{ url('partner/login') }}" class="btn btn-register fw-bold">
                Know more about Tizzy Group
            </a>
        </div>
    </div>
@endsection
