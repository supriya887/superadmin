@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection


@section('Body')
<section class="home-banner position-relative" style="background-color: var(--color-d1e9e5);">
  <!-- <div class="mascot mascot01">
    <img src="{{  asset('public/home/images/Mascot-02.png')}}" alt="">
  </div> -->
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="800">
        <div class="banner-img img-zoom">
          <img src="{{  asset('public/home/images/about-img.jpg')}}" class="img zoom" alt="about img">
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="banner-text">
          <h1 class="title">Data Migration Service</h1>
          <p class="p-content">Data Migration is considered as one of the biggest costs during development.  It always depends on size of data to be migrated. Migration can be from one environment to  another or from one company to other.</p>
          <a href="{{ url('contact') }}" class="tp-btn-black mt-50">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Get Started</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="migration-section position-relative pt-100 pb-100">
  <div class="container">
    <div class="row position-relative">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center order-md-div">
        <div class="automated-text">
          <h2 class="title" data-aos="fade-down" data-aos-easing="linear" data-aos-duration="800">Automated Platform</h2>
          <div class="Migration-text" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <p>Data Migration to Cloud is the primary service provided by our company.  Pre-Migration checks are done to see if the host and client both can perform the migration without any errors.  If there are any chances of errors then it is solved before starting migration.</p>
            <p>Complete backup is taken to make sure in case of any data loss or disaster the important data can be recovered.  Usually, data migration time depends on Instance type/configuration and internet speed.  Bandwidth limit or charges needs to be checked calculating the overall size of the data to be migrated.</p>
          </div>
          <p class="p-content" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">We are thriving to built a fully automated platform that can help our clients to manage their cloud computing services and save cost.  Research and innovation are our core business principles.</p>
          
          <div class="row">
            <div class="col-lg-6 col-md-6 col-12 p-0 d-lg-flex" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
              <div class="migration-list">
                <h2 class="p-content color-1e58b4">Migration Service by leading Cloud Computing Companies</h2>
                <ul class="mig">
                  <li><img src="{{ asset('public/home/images/icons/cloudy.svg')}}" class="svg" alt="cloudy">AWS Migration Service</li>
                  <li><img src="{{ asset('public/home/images/icons/cloudy.svg')}}" class="svg" alt="cloudy">Microsoft Azure Migration Service</li>
                  <li><img src="{{ asset('public/home/images/icons/cloudy.svg')}}" class="svg" alt="cloudy">Google Cloud Migration Service</li>
                </ul>
              </div>
            </div>
            <div class="col-lg-6 col-md-6 col-12 p-0 d-lg-flex" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
              <div class="migration-list">
                <h3 class="p-content color-1e58b4">Migration</h3>
                <ul class="mig">
                  <li><img src="{{ asset('public/home/images/icons/cloudy.svg')}}" class="svg" alt="cloudy">Cloud Instance</li>
                  <li><img src="{{ asset('public/home/images/icons/cloudy.svg')}}" class="svg" alt="cloudy">Application</li>
                  <li><img src="{{ asset('public/home/images/icons/cloudy.svg')}}" class="svg" alt="cloudy">High Availability Database</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12">
        <div class="banner-img img-zoom" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="1200">
          <img src="{{  asset('public/home/images/Migration-Automated-Platform.jpg')}}" class="img zoom" alt="migration automated platform">
        </div>
      </div>
    </div>
  </div>
</section>
@endsection