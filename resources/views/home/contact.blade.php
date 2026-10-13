@extends('home.render')

@section('metaTitle')
  <title>Contact Us - Tizzy Cloud</title>
  <meta name="description" content="Contact Tizzy Cloud for corporate sales, technical support or billing." />
  <meta name="keywords" content="tizzy cloud, contact us, corporate sales, technical support, cloud computing" />
@endsection

@section('Body')
<section class="home-banner h-100 pb-100 pt-100 position-relative" style="background-image: url({{ asset('public/home/images/bg/contact-bg.png') }});">
 <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-6 col-md-10 col-12">
        <div class="banner-text p-0 mb-80 text-center">
          <h1 class="title">Contact Info</h1>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="contact-section position-relative">
  <div class="container">
    <div class="row position-relative">
      <div class="col-lg-4 col-md-12 col-12">
        <div class="contact-plan-box" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
          <img src="{{ asset('public/home/images/icons/smart-email.png') }}" alt="smart-email">
          <h2 class="div-text mb-2">Email</h2>
          <div class="mb-20">
            <h2 class="p-content mb-0 weight-Medium">For Corporate Sales -</h2>
            <a href="mailto:sales@tizzycloud.com">sales@tizzycloud.com</a>
          </div>
          <div class="mb-20">
            <h3 class="p-content mb-0 weight-Medium">For Technical Support - </h3>
            <a href="mailto:support@tizzycloud.com">support@tizzycloud.com</a>
          </div>
          <div class="">
            <h4 class="p-content mb-0 weight-Medium">For Billing / Renewal - </h4>
            <a href="mailto:accounts@tizzycloud.com">accounts@tizzycloud.com</a>
          </div>
        </div>
      </div>
      <div class="col-lg-4 col-md-12 col-12">
        <div class="contact-plan-box" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
          <img src="{{ asset('public/home/images/icons/location-pin.png') }}" alt="location-pin">
          <h3 class="div-text mb-2">CORPORATE OFFICE ADDRESS</h3>
          <h4 class="p-content weight-Bold">Tizzy®Cloud Computing Private Limited</h4>
          <p>Office No. 511, Kalpataru Avenue, <br> Akurli Road, Kandivali East,<br> Mumbai - 400101, <br>Maharashtra,  INDIA</p>
        </div>
      </div>
      <div class="col-lg-4 col-md-12 col-12">
        <div class="contact-plan-box" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
          <img src="{{ asset('public/home/images/icons/phone-call.png') }}" alt="phone-call">
          <h4 class="div-text mb-1">Global Helpline</h4>
          <p> <a href="tel:+919925992599"> +91 9925992599</a></p>
          <div class="social-media-c pt-5">
            <ul class="d-flex">
              <li><a href="https://www.instagram.com/tizzy_cloud/" title="Instagram" target="_blank"><img src="{{ asset('public/home/images/icons/instagram.png') }}" alt="instagram"></a></li>
              <li><a href="https://www.facebook.com/tizzycloudmumbai" title="Facebook" target="_blank"><img src="{{ asset('public/home/images/icons/facebook.png') }}" alt="facebook"></a></li>
              <li><a href="https://www.linkedin.com/company/tizzy-cloud" title="LinkedIn" target="_blank"><img src="{{ asset('public/home/images/icons/linkedin.png') }}" alt="linkedin"></a></li>
              <li><a href="https://twitter.com/tizzy_cloud" title="Twitter" target="_blank"><img src="{{ asset('public/home/images/icons/twitter.png') }}" alt="twitter"></a></li>
              <li><a href="https://goo.gl/maps/esg8rK3ftHYF2KRA9" title="Google Reviews" target="_blank"><img src="{{ asset('public/home/images/icons/googl-logo.png') }}" alt="Google Reviews"></a></li>
              <li><a href="https://api.whatsapp.com/send?phone=919925992599" title="Whatsapp" target="_blank"><img src="{{ asset('public/home/images/icons/whatsapp.png') }}" alt="Whatsapp"></a></li>
            </ul>
        </div>
        </div>
      </div>
    </div>
  </div> 
  <div class="iframe-banner">
    <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d7535.662339906239!2d72.855722!3d19.202575!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3be7b730ec5f42c7%3A0xe2a6eb0fac916142!2sTizzy%20Cloud%20Computing%20Private%20Limited!5e0!3m2!1sen!2sin!4v1768912004924!5m2!1sen!2sin" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
  </div>
</section>
@endsection
