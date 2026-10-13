@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection


@section('Body')
<section class="home-banner h-100 position-relative" style="background: #d6d6f2;">
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="banner-text">
          <h1 class="title mb-0">Tizzy® Mail</h1>
          <p class="p-content">Tizzy® Mail users will never have to worry about delayed emails, missing incoming mails, spam issues, IP blacklisting, DNS-related problems, email spoofing, or data backup and restore. <br> All our plans are designed with robust solutions to handle commonly faced email issues.</p>
          <div class="d-flex flex-column flex-lg-row gap-5 mt-40">
            <a href="#pricing" class="tp-btn-black">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">View Pricing</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
          </a>
          </div>
          <div class="DedicatedSupport d-flex gap-5 mt-40">
            <div class="w-50 d-flex flex-column justify-content-between uptime position-relative" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
              <div>
                <span class="div-sub-title d-inline-block mb-0">99.99%</span><br>
               <span class="small-text color-000000 d-inline-block mb-0">Uptime</span>
              </div>
              <h2 class="p-content mb-0">Dedicated Support</h2>
            </div>
            <div class="w-50" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
              <h3 class="div-sub-title">AI Based Spam Protection</h3>
              <h4 class="p-content mb-0">Dedicated Support</h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-end" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
        <div class="banner-img home-banner-img">
          <img src="{{  asset('public/home/images/tizzy-mail/banner-img.png')}}" class="img" alt="banner img">
        </div>
      </div>
    </div>
  </div>
</section>
<section class="workspace-plan-section pt-100 pb-100" id="pricing">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-xl-7 col-lg-9 col-md-12 col-12">
        <div class="workspace-plan-title text-center">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Tizzy® Mail INDIA <br> Cloud Business Email Hosting Plans on Amazon AWS</h2>
          <p data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">AI & Machine Learning–based Email Security with Tizzy Mail Enterprise & Corporate Plans.</p>
        </div>
      </div>
    </div>
    <div class="row">
      @foreach ($plans as $item)
      <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
        <div class="plan-box workspace-plan-box">
          <div class="best-value">
            <h3 class="div-text mb-0 weight-Bold">{{ $item->title }}</h3>
          </div>
          <div class="plans-details">
            <h4 class="small-text mb-0 weight-SemiBold most-popular">{{ $item->name }}</h4>
            <div class="plans-details-text">
              <h5 class="div-title mb-0 weight-SemiBold">₹{{ $item->price }}</h5>
              <span class="extra-small-text d-block mb-0">Per User / Per Year</span>
              <span class="extra-small-text d-block mb-0">GST 18% Additional</span>
            </div>
            <div class="qty-wrapper-bg features">
              <h6 class="p-content mb-0 weight-SemiBold">Top Features</h6>
            </div>
            <div class="more-details-box">
              <ul class="plans-details-list">
                @php
                   $features = DB::table('plan_features')->where('plan_id', $item->id)->get();
                @endphp
                @foreach ($features as $feature)
                  <li><img src="{{ asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">{{ $feature->feature }}</li>
                @endforeach
              </ul>
              <div class="plans-details-text plans-details-text-bottom">
                <a href="{{ url('select-plan/'.$item->id) }}" class="buy-now">Buy Now</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      @endforeach
     
    

    </div>
  
  </div>
</section>
<section class="data-backup-section hosting-plans-section position-relative pt-100 pb-100">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-xl-7 col-lg-9 col-md-12 col-12">
        <div class="workspace-plan-title text-center">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Powerful webmail features for productivity</h2>
          <p data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">Discover a full set of powerful features designed to enhance your email, contacts, and scheduling experience.</p>
        </div>
      </div>
    </div>
    <div class="row mt-50">
      <div class="col-xl-4 col-lg-4 col-md-4 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
        <div class="data-backup-box">
          <img src="{{  asset('public/home/images/icons/smart-email.png')}}" alt="smart email">
          <h3 class="div-text">Smart Email Experience</h3>
          <ul class="data-backup-list">
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Fast & fully indexed search</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Drag & drop emails</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Separate compose & preview windows</li>
          </ul>
        </div>
      </div>
      <div class="col-xl-4 col-lg-4 col-md-4 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
        <div class="data-backup-box">
          <img src="{{  asset('public/home/images/icons/calendar.png')}}" alt="calendar">
          <h3 class="div-text">Contacts & Calendar</h3>
          <ul class="data-backup-list">
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Personal contacts & groups</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Calendar, tasks & reminders</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Appointment scheduling</li>
          </ul>
        </div>
      </div>
      <div class="col-xl-4 col-lg-4 col-md-4 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
        <div class="data-backup-box">
          <img src="{{  asset('public/home/images/icons/shield.png')}}" alt="shield">
          <h3 class="div-text">Security & Control</h3>
          <ul class="data-backup-list">
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Spam rules & blacklist</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Password recovery</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Mailbox & access control</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="data-backup-section why-choose-tizzy position-relative pt-80 pb-80">
  <!-- <div class="mascot mascot01">
    <img src="{{  asset('public/home/images/Mascot-02.png')}}" alt="">
  </div> -->
  <div class="why-choose-tizzy">
    <img src="{{  asset('public/home/images/tizzy-mail/Why-Choose-Tizzy-Mail-bg.png')}}" class="img" alt="Why Choose Tizzy Mail Background">
  </div>
  <div class="container container-1760">
    <div class="row justify-content-center">
      <div class="col-xl-7 col-lg-9 col-md-12 col-12">
        <div class="workspace-plan-title text-center">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Why Choose Tizzy Mail?</h2>
        </div>
      </div>
    </div>
    <div class="row mt-50 justify-content-end">
      <div class="col-xl-8 col-lg-9 col-md-12 col-12">
        <div class="row justify-content-start">
          <div class="col-xl-4 col-lg-4 col-md-12 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <div class="data-backup-box">
              <h3 class="div-text">Fast Performance </h3>
              <p class="p-0">Rapid synchronised email experiences with any email clients, such as Outlook and Thunderbird. This will ensure the sending and receiving of emails with large attachments using our optimised POP, IMAP, and SMTP.</p>
            </div>
          </div>
          <div class="col-xl-4 col-lg-4 col-md-12 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <div class="data-backup-box">
              <h3 class="div-text">Special Security Measures</h3>
              <p class="p-0">Strong protection for your business communications along with advanced safeguards, such as SSL/TLS encryption, DKIM, SPF, etc., and spam and antivirus filtering. All these measures keep your data safe from threats and illegitimate access.</p>
            </div>
          </div>
          <div class="col-xl-4 col-lg-4 col-md-12 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <div class="data-backup-box">
              <h3 class="div-text">Guaranteed Backup of Data</h3>
              <p class="p-0">Thanks to automated scheduled backups on AWS S3 Buckets every 30 days, vital data stored in our facilities can be kept and restored when required.</p>
            </div>
          </div>
          <div class="col-xl-4 col-lg-4 col-md-12 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <div class="data-backup-box">
              <h3 class="div-text">Simple Webmail Interface</h3>
              <p class="p-0">An easily accessible webmail platform where your email can be equipped with drag-and-drop, auto-complete addressing, and a search engine fully indexed to make information speedy.</p>
            </div>
          </div>
          <div class="col-xl-4 col-lg-4 col-md-12 col-12 data-backup-bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
            <div class="data-backup-box">
              <h3 class="div-text">Scalable Storage Options</h3>
              <p class="p-0">Users can select the plan that best suits their business, ranging from 10GB to 1 TB of storage per user.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection