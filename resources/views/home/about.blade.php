@extends('home.render')

@section('metaTitle')
  <title>Contact Us - Tizzy Cloud</title>
  <meta name="description" content="Contact Tizzy Cloud for corporate sales, technical support or billing." />
  <meta name="keywords" content="tizzy cloud, contact us, corporate sales, technical support, cloud computing" />
@endsection

@section('Body')
<section class="home-banner position-relative" style="background-color: var(--color-d1e9e5);">
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center order-md-div">
        <div class="banner-text">
          <h1 class="title">About Tizzy Cloud</h1>
          <p class="p-content">Tizzy Cloud is a team of enthusiastic techies specialized in Cloud Computing services.  Since inception we are focused on educating rather just selling services..  Gaining trust of our elite clients we have a customer retain ratio of almost 94%.</p>
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="800">
        <div class="banner-img img-zoom">
          <img src="{{ asset('public/home/images/about-img.jpg')}}" class="img zoom" alt="about img">
        </div>
      </div>
    </div>
  </div>
</section>
<section class="strong-platform-section position-relative pt-100 pb-100">
  <!-- <div class="mascot mascot01 ">
    <img src="{{ asset('public/home/images/Mascot-02.png')}}" alt="Mascot 02">
  </div> -->
  <div class="container">
    <div class="row">
      <div class="col-lg-6 col-md-12 col-12">
        <div class="banner-img img-zoom" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
          <img src="{{ asset('public/home/images/Strong-Platform.jpg')}}" class="img zoom" alt="Strong Platform">
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12">
        <div class="banner-text">
          <h2 class="title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Strong Platform</h2>
          <p class="p-content" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">Hundreds of companies are served under this strong platform of our company and thousands of email users are sending or receiving emails almost every second.</p>
          <p class="p-content" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">Our country is heading towards an era of digital transformation and cutting-edge technology.  Enhancing skills with innovation and adopting technologies always results in scalable growth of business.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="automated-platform pt-100 pb-100">
  <!-- <div class="mascot mascot04 mobile-md-none">
    <img src="{{ asset('public/home/images/Mascot-04.png')}}" alt="">
  </div> -->
  <div class="container">
    <div class="row position-relative">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center order-md-div">
        <div class="automated-text">
          <h2 class="title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Automated Platform</h2>
          <p class="p-content" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">We are thriving to built a fully automated platform that can help our clients to manage their cloud computing services and save cost.  Research and innovation are our core business principles.</p>
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12">
        <div class="banner-img img-zoom" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="1200">
          <img src="{{ asset('public/home/images/Automated-Platform.jpg')}}" class="img zoom" alt="Automated Platform">
        </div>
      </div>
    </div>
  </div>
</section>
@endsection