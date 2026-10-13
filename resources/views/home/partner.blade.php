@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection


@section('Body')
<section class="home-banner pt-100 h-100 position-relative" style="background: #d6d6f2;">
  <div class="microsoft-banner">
    <img src="{{ asset('public/home/images/partner/partner-banner.jpg') }}" class="img" alt="partner banner">
  </div>
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="banner-text position-relative">
          <h1 class="title mb-0">Services that Partners Can offer</h1>
          <div class=" mt-40">
            <a href="{{ url('partner/register') }}" class="tp-btn-black-text">Register or login</button>

            <a href="{{ url('contact') }}" class="tp-btn-black">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Contact Us</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
          </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="partners-section position-relative pt-100 pb-100">
  <div class="container">
    <div class="row">
      <div class="col-lg-12">
        <div class="plans-title text-center" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
          <h2 class="title">Partners with us</h2>
        </div>
      </div>   
    </div>
    <div class="row mt-30 position-relative">
      <div class="col-xxl-6 col-lg-6 col-md-12 col-12">
        <div class="partners-img img-zoom">
          <img src="{{ asset('public/home/images/partner/partners-img.jpg') }}" class="img zoom" alt="partners img">
        </div>
      </div> 
      <div class="col-xxl-6 col-lg-6 col-md-12 col-12">
        <div class="partners-list-box">
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Free Partner Registration</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">No Investments Required</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">No Technical Skills Required</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Manage your customer's account on our partner panel</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Earn recurring income every year on retainer clients</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Technical support will be provided from our side</h4>
          </div>

        </div>
      </div>   
    </div>
    <div class="row mt-80">
      <div class="col-xxl-6 col-lg-6 col-md-12 col-12 order-md-div">
        <div class="partners-list-box">
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Assistance for finalizing the deal</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Lifetime Free Tizzy® Cloud Hosting for Premium Partners</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Slab Wise Special Offers or Incentives for Partners</h4>
          </div>
          <div class="partners-list" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
            <img src="{{ asset('public/home/images/icons/partners-check.png') }}" alt="partners check">
            <h4 class="div-text">Partner Badge allowed for Premium Partners</h4>
          </div>
        </div>
      </div> 
      <div class="col-xxl-6 col-lg-6 col-md-12 col-12">
        <div class="partners-img img-zoom">
          <img src="{{ asset('public/home/images/partner/partners-img02.jpg') }}" class="img zoom" alt="">
        </div>
      </div>  
    </div>

  </div>
</section>
<section class="testimonial-section pt-100 pb-100">
  <div class="row m-0">
      <div class="col-lg-12 p-0">
        <div class="testimonial-left">
          <img src="{{ asset('public/home/images/icons/quote.png') }}" class="quote" alt="" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600"> 
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
                    <img src="{{ asset('public/home/images/user.png')}}" alt="User">
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
<section class="faq-section position-relative pt-100 pb-100">
  <!-- <div class="mascot mascot01">
    <img src="{{ asset('public/home/images/Mascot-02.png') }}" alt="">
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
      <div class="col-xxl-8 col-lg-10 col-md-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
        <div class="accordion faq-accordion" id="accordionExample">
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <h2 class="accordion-header" id="headingOne">
              <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                What is Cloud Business Email Hosting Service?
              </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">Cloud Email Hosting is a service setup on Cloud Computing Environment.  Cloud enables high availability or uptime of service along with safety and security of stored data.  Backup and disaster recovery is very fast on Cloud servers.</p>
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
@endsection

