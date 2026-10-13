@extends('home.render')

@section('metaTitle')
    <title>Verification Successful | Tizzy Cloud</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .success-icon-wrapper {
            font-size: 5rem;
            color: #1665b6;
            background: #eef5ff;
            width: 120px;
            height: 120px;
            line-height: 120px;
            border-radius: 50%;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-heading {
            color: #43475b;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
        }
        .success-description {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }
    </style>
@endsection

@section('Body')
    <div class="auth-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-7">
                    <div class="auth-card p-5 text-center" data-aos="zoom-in" data-aos-duration="800">
                        <div class="success-icon-wrapper mb-4" data-aos="scale-up" data-aos-delay="200">
                            <i class="fa fa-circle-check"></i>
                        </div>
                        
                        <h1 class="success-heading" data-aos="fade-up" data-aos-delay="400">
                            Your email has been verified Successfully.
                        </h1>
                        
                        <p class="success-description" data-aos="fade-up" data-aos-delay="600">
                            You will receive an email confirmation within 2 working days,<br>
                            post verification of your account details.
                        </p>
                        
                        <div data-aos="fade-up" data-aos-delay="800">
                            <a href="https://www.tizzycloud.com" class="btn btn-register text-white px-5 py-3 rounded-pill fw-bold">
                                Know more about Tizzy 
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
