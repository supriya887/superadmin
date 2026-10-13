@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection


@section('Body')
<section class="home-banner position-relative">
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-6 col-12">
        <div class="banner-text">
          <h1 class="title">Tizzy® Cloud Email Plans</h1>
          <h2 class="title"><span class="d-inline-block weight-Regular" style="color: #333233;">at ₹</span> 792.</h2>
          <p>Tizzy Mail, Microsoft 365 and Google Workspace — migrations, and quick support that keeps your team productive and compliant.</p>
          <a href="{{ url('tizzy-mail') }}" class="tp-btn-black">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Get Start Now</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
          </a>
        </div>
      </div>
      <div class="col-lg-6 col-md-6 col-12 d-flex align-items-end">
        <div class="banner-img home-banner-img" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
          <img src="{{ asset('public/home/images/banner/home-banner.png') }}" class="img" alt="Home Banner">
        </div>
      </div>
    </div>
  </div>
</section>
<section class="home-plan-section">
  <div class="container">
    <div class="row">
      <div class="col-lg-4 col-md-4 col-xl-4 col-sm-4 col-12">
        <div class="home-plan-box" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
          <div class="text-center">
            <img src="{{ asset('public/home/images/icons/email.png') }}" alt="email" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
            <h2 class="div-text mb-1">Email Send/Receive Speed</h2>
            <p>Email Send/ Receive Speed Fastest Outlook/Any Email Application Sync Speed</p>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-xl-4 col-sm-4 col-12">
        <div class="home-plan-box" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
          <div class="text-center">
            <img src="{{ asset('public/home/images/icons/data-recovery.png') }}" alt="data recovery" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
            <h2 class="div-text mb-1">Backup & Restore</h2>
            <p>Automated 30 days scheduled backup on AWS S3 Bucket</p>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-4 col-xl-4 col-sm-4 col-12">
        <div class="home-plan-box" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
          <div class="text-center">
            <img src="{{ asset('public/home/images/icons/verified.png') }}" alt="verified" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
            <h3 class="div-text mb-1">AWS Security Standards</h3>
            <p>AWS Foundational Security Best Practices, CIS AWS Foundations Benchmark & PCI DSS</p>
          </div>
        </div>
      </div>
    </div>
  </div>  
</section>
<section class="home-about-section position-relative pt-100 pb-100">
  <div class="container">
    <div class="row position-relative">
      <!-- <div class="mascot mascot01-about">
        <img src="{{ asset('public/home/images/Mascot-03.png') }}" alt="Mascot">
      </div> -->
      <div class="col-lg-6 col-md-6 col-12">
      <div class="home-about-left position-relative img-zoom" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
        <img src="{{ asset('public/home/images/home-about-img.jpg') }}" class="img zoom" alt="home about">
      </div>  
    </div>
    <div class="col-lg-6 col-md-6 col-12">
      <div class="home-about-right">
        <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">Features of Tizzy® Mail</h2>
        <p data-aos="fade-up" data-aos-easing="linear" data-aos-duration="900">Tizzy Mail can be setup in automatic or manual mode on any device and in any email application. <br> It also has a user friendly Webmail Interface to access from browser. Creating your business email address is very fast and easy on Tizzy® Mail for eg. sales@YourCompanyName.com</p>
        <a href="{{ url('tizzy-mail') }}" class="tp-btn-black mt-30" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
          <span class="tp-btn-black-filter d-inline-flex align-items-center">
            <span class="tp-btn-black-text">Get Start Now</span>
            <span class="tp-btn-black-circle">
              <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </span>
          </span>
        </a>
        <div class="google-rating" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="1500">
          <div class="google-img">
            <p class="mb-0">4.6 stars out of 5</p>
            <img src="{{ asset('public/home/images/icons/rating.png') }}" alt="Rating">
          </div>
          <div class="google-img">
            <img src="{{ asset('public/home/images/icons/clients-appreciate.png') }}" alt="Clients Appreciate">
            <p class="mt-2 mb-0">Clients appreciate our email hosting, cloud setups & Workspace support</p>
          </div>
        </div>
      </div>  
    </div>
    </div>
    <div class="row mt-50">
      <div class="col-lg-12">
        <ul class="nav nav-tabs nav-tab-section flex-nowrap features-tabs" id="myTab" role="tablist" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" role="tab" aria-controls="features" aria-selected="true">Faster Desktop and Mobile Webmail</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="features-tab02" data-bs-toggle="tab" data-bs-target="#features02" role="tab" aria-controls="features02" aria-selected="false">Faster POP, IMAP and SMTP Protocols</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="features-tab03" data-bs-toggle="tab" data-bs-target="#features03" role="tab" aria-controls="features03" aria-selected="false">Integrated Spam, Antivirus and Email Filtering</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="features-tab04" data-bs-toggle="tab" data-bs-target="#features04" role="tab" aria-controls="features04" aria-selected="false">DNS Management</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="features-tab05" data-bs-toggle="tab" data-bs-target="#features05" role="tab" aria-controls="features05" aria-selected="false">DNS Management</button>
          </li>
          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" id="features-tab06" data-bs-toggle="tab" data-bs-target="#features06" role="tab" aria-controls="features06" aria-selected="false">SSL/TLS Security</button>
          </li>
        </ul>
        <div class="tab-content" id="myTabContent" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
          <div class="tab-pane fade show active" id="features" role="tabpanel" aria-labelledby="features-tab" tabindex="0">
            <div class="features-content mt-50">
              <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                  <div class="features-img img-zoom">
                    <img src="{{ asset('public/home/images/features-img.png') }}" class="img zoom" alt="Features Image">
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="features-text">
                    <p>New Faster and Improved User-Friendly Webmail Interface. Fully Indexed Search engine returns results almost immediately.</p>
                    <p>Forgot Password Feature, Skin or Layout change and much more is available in our latest Webmail version.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="features02" role="tabpanel" aria-labelledby="features-tab02" tabindex="0">
            <div class="features-content mt-50">
              <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                  <div class="features-img img-zoom">
                    <img src="{{ asset('public/home/images/features-img02.png') }}" class="img zoom" alt="Features Image 02">
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="features-text">
                    <p>Auto discovery setup in all Email Clients like Outlook, Thunderbird etc. Faster Send/Receive or Sync of emails/folders. Sending emails with attachments is fast so delivery time of email to recipient inbox is also much shorter.</p>
                  </div>
                </div>
              </div>
            </div>  
          </div>
          <div class="tab-pane fade" id="features03" role="tabpanel" aria-labelledby="features-tab03" tabindex="0">
            <div class="features-content mt-50">
              <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                  <div class="features-img img-zoom">
                    <img src="{{ asset('public/home/images/features-img03.png') }}" class="img zoom" alt="Features Image 03">
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="features-text">
                    <p>Filtering based on Reverse DNS Blacklisting, PTR DNS Record, DKIM, Greylisting, SPF, URL Blacklisting, Third Party Antivirus Scanner, IP Blacklisting, Domain Blacklisting or Email Address Blacklisting and Detecting/Blocking Abuse/Attacks.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="features04" role="tabpanel" aria-labelledby="features-tab04" tabindex="0">
            <div class="features-content mt-50">
              <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                  <div class="features-img img-zoom">
                    <img src="{{ asset('public/home/images/features-img04.png') }}" class="img zoom" alt="Features Image 04">
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="features-text">
                    <p>Managing DNS with all required records to make sure there is no warning or error shown when checking the overall Email Health for the domain. Email sent from such DNS of a Domain results in inbox delivery within seconds of sending it.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="features05" role="tabpanel" aria-labelledby="features-tab05" tabindex="0">
            <div class="features-content mt-50">
              <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                  <div class="features-img img-zoom">
                    <img src="{{ asset('public/home/images/features-img05.png') }}" class="img zoom" alt="Features Image 05">
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="features-text">
                    <p>Its very easy to manage your Email Accounts in few clicks. The IT manager of your company can now create, edit features or delete any of the Email Account in no time. You can update password or disable webmail and do lot more with various features.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="features06" role="tabpanel" aria-labelledby="features-tab06" tabindex="0">
            <div class="features-content mt-50">
              <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-12">
                  <div class="features-img img-zoom">
                    <img src="{{ asset('public/home/images/features-img06.png') }}" class="img zoom" alt="Features Image 06">
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="features-text">
                    <p>SSL and TLS Encryption is enabled for both incoming and outgoing emails.  For incoming email you can set SSL and for Outgoing you can set TLS as Security Type while setting up your emails with any of Email Client like Outlook, Thunderbird etc.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="hosting-plans-section position-relative pt-100 pb-100">
  <!-- <div class="mascot mascot04">
    <img src="{{ asset('public/home/images/Mascot-04.png') }}" alt="Mascot 04">
  </div> -->
  <div class="container">
    <div class="row">
      <div class="col-lg-12">
        <div class="plans-title text-center" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
          <h2 class="title">Tizzy Cloud Email Plans</h2>
        </div>
      </div>
    </div>
    <div class="row justify-content-center mt-40">
      <div class="col-xxl-10 col-xl-10 col-lg-12 col-md-12">
        <div class="row gx-4">
          @foreach($plans as $plan)
          <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <div class="plan-box">
              <div class="best-value">
                <h3 class="small-text mb-0 weight-Regular">{{ $plan->name }}</h3>
              </div>
              <div class="plans-details">
                <div class="plans-details-text">
                  <h4 class="div-text mb-0 weight-Regular">{{ $plan->name }}</h4>
                  <h5 class="div-sub-title mb-0 weight-SemiBold">{{ $plan->storage }}</h5>
                  <h6 class="div-title mb-0 weight-SemiBold color-1e58b4">₹ {{ $plan->price }} </h6>
                  <span class="extra-small-text d-block">/Per user /Per Year</span>
                  <a href="{{ url('select-plan/'.$plan->id) }}" class="buy-now">Buy Now</a>
                </div>
                <div class="gst-bg"><div class="small-text renews">Renews at <span class="div-text">₹ 792</span> </div></div>
                
                <div class="plans-details-text plans-details-text-bottom">
                  <h4 class="small-text">GST 18% Additional</h4>
                  <a href="{{ url('tizzy-mail') }}" class="tp-btn-black">
                    <span class="tp-btn-black-filter d-inline-flex align-items-center">
                      <span class="tp-btn-black-text">Details</span>
                      <span class="tp-btn-black-circle">
                        <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                      </span>
                    </span>
                  </a>
                </div>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
      <div class="col-12 mt-80 d-flex justify-content-center">
        <a href="{{ url('tizzy-mail') }}" class="tp-btn-black btn-red-bg" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="800">
          <span class="tp-btn-black-filter d-inline-flex align-items-center">
            <span class="tp-btn-black-text">View All Plans</span>
            <span class="tp-btn-black-circle">
              <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </span>
          </span>
        </a>
      </div>  
    </div>
  </div>
</section>
<section class="faq-section position-relative pt-100 pb-100">
  <!-- <div class="mascot mascot01">
    <img src="{{ asset('public/home/images/Mascot-02.png') }}" alt="Mascot 02">
  </div> -->
  <div class="container">
    <div class="row mb-80 justify-content-center">
      <div class="col-xl-8 col-lg-8 col-md-8 col-12">
        <div class="faq-title text-center">
          <h2 class="title mb-2" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Frequently Asked Questions</h2>
        </div>
      </div>
    </div>
    <div class="row justify-content-center">
      <div class="col-xxl-8 col-lg-10 col-md-12">
        <div class="accordion faq-accordion" id="accordionExample">
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <h2 class="accordion-header" id="headingOne">
              <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                What is Cloud Business Email Hosting Service?
              </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">Tizzy Cloud Email Hosting is a service setup on Cloud Computing Environment.  Cloud enables high availability or uptime of service along with safety and security of stored data.  Backup and disaster recovery is very fast on Cloud servers.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <h2 class="accordion-header" id="headingTwo">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
               What is Tizzy®Mail?
              </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
               <p class="mb-0">Tizzy®Mail is a Cloud Business Email Hosting Solution provided by Tizzy®Cloud.  It is hosted on the most advanced Cloud Computing Infrastructure of the world.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <h2 class="accordion-header" id="headingThree">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
               We have not heard about Tizzy®Mail so How old is Tizzy®Mail?
              </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0"> Tizzy®Mail has a consistent growth record of more than 7 years.  We have thousands of email users comprises of small and big corporate companies.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
            <h2 class="accordion-header" id="headingThree2">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree2" aria-expanded="false" aria-controls="collapseThree2">How do we migrate to Tizzy®Mail?</button>
            </h2>
            <div id="collapseThree2" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0"> Migrating to Tizzy® Mail is very easy.  Our technical support specialist can help you in migrating your email services or email data to Tizzy®Mail.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1600">
            <h2 class="accordion-header" id="headingThree3">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree3" aria-expanded="false" aria-controls="collapseThree3">
               How to renew the services next year?
              </button>
            </h2>
            <div id="collapseThree3" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">  You will get a daily email notification on your registered email address 7 days prior to the expiry date.  You can reply to the email and make payment as per the invoice to process the renewal.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="testimonial-section pt-100 pb-100">
  <div class="row m-0">
      <div class="col-lg-12 p-0">
        <div class="testimonial-left">
          <img src="{{ asset('public/home/images/icons/quote.png') }}" class="quote" alt="Quote Icon" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600"> 
          <h2 class="div-title color-333233" data-aos="fade-left" data-aos-easing="linear" data-aos-duration="600">What Our Customers Are Saying</h2> 
          <div class="mt-50" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <div class="swiper-pagination-testimonial"></div>
            <div class="swiper-button-next" role="button" aria-label="Next testimonial slide" tabindex="0"><img src="{{ asset('public/home/images/icons/testi-arrow.png') }}" alt=""></div>
            <div class="swiper-button-prev" role="button" aria-label="Previous testimonial slide" tabindex="0"><img src="{{ asset('public/home/images/icons/testi-arrow2.png') }}" alt=""></div>
          </div> 
        </div>
        <div class="testimonial-right" data-aos="fade-right" data-aos-easing="linear" data-aos-duration="600">
          <div class="testimonial_slider swiper-container">
            <div class="swiper-wrapper">
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we  were about to migrate all 200 email users to either Google or Microsoft. After  trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="Star Icon"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="Star Icon"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="Star Icon"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="Star Icon"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="Star Icon"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User Icon">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">IT Manager of leading HR Company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>IP blacklisting issue, UCE Protect level 2 and level 3 listing issues, spamming issues, other DNS related issues all were solved after moving all 350 emails to Tizzy Mail. We always wanted Indian Data Center and with Tizzy Mail all our Email Data is now stored only in India.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">IT Manager of multinational manufacturing company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>Incoming spam emails are reduced to 60-70%, almost ZERO complaints from our customers, quick response to our emails. All IT companies should offer only Tizzy Mail service to their clients.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">Owner of 22-year-old IT company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we were about to migrate all 200 email users to either Google or Microsoft. After trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">IT Manager of leading HR Company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>IP blacklisting issue, UCE Protect level 2 and level 3 listing issues, spamming issues, other DNS related issues all were solved after moving all 350 emails to Tizzy Mail. We always wanted Indian Data Center and with Tizzy Mail all our Email Data is now stored only in India.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">IT Manager of multinational manufacturing company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>Incoming spam emails are reduced to 60-70%, almost ZERO complaints from our customers, quick response to our emails. All IT companies should offer only Tizzy Mail service to their clients.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">Owner of 22-year-old IT company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png') }}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we were about to migrate all 200 email users to either Google or Microsoft. After trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png') }}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png') }}" alt="User">
                    <div>
                      <h3 class="p-content weight-SemiBold mb-0">IT Manager of leading HR Company</h3>
                      <h4 class="small-text color-333233 weight-Medium mb-0">MUMBAI, INDIA</h4>
                    </div>
                  </div>
                </div>
              </div> 
            </div>
          </div>
          
        </div>
      </div>
    </div>
</section>
@endsection
