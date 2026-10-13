@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection


@section('Body')
<section class="home-banner position-relative" style="background-color: var(--color-d1e9e5);">
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="banner-text">
          <h1 class="title mb-0">Google Workspace with Google AI</h1>
          <p class="p-content">Work smarter and collaborate better with powerful productivity tools.</p>
          <ul class="workspace-list mt-40" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Professional business email with your domain</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Easy communication and team collaboration</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Google AI to boost productivity</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Strong security and privacy controls</li>
          </ul>
          <div class="d-flex flex-column flex-lg-row gap-5 mt-40">
            <a href="https://cloud.google.com/find-a-partner/partner/tizzy-cloud-computing-private-limited" target="_blank" class="tp-btn-black">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Authorized Google Cloud Partner</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
            </a>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-end" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
        <div class="banner-img">
          <img src="{{  asset('public/home/images/workspace/workspace-banner.png') }}" class="img" alt="Google Workspace Banner">
        </div>
      </div>
    </div>
  </div>
</section>
<section class="workspace-plan-section hosting-plans-section pt-100 pb-100" id="pricing">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-xl-7 col-lg-9 col-md-12 col-12">
        <div class="workspace-plan-title text-center">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Choose the Right Google Workspace Plan</h2>
          <p data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">Simple, secure and powerful tools to Support your business grow</p>
        </div>
      </div>
    </div>
    <div class="row justify-content-center mt-20 mb-50">
      <div class="col-xl-12 col-lg-12 col-md-12 col-12">
        <div class="workspace-include" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/Gmail.svg') }}" alt="Gmail"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/Drive.svg') }}" alt="Drive"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/meet.svg') }}" alt="Meet"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/Calendar.svg') }}" alt="Calendar"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/chat.svg') }}" alt="Chat"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/gemini.png') }}" alt="Gemini"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/docs.svg') }}" alt="Docs"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/sheets.svg') }}" alt="Sheets"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/slides.svg') }}" alt="Slides"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/vids.svg') }}" alt="Vids"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/keep.svg') }}" alt="Keep"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/sites.svg') }}" alt="Sites"></div>
          <div class="workspace-include-list"><img src="{{  asset('public/home/images/workspace/workspace-tools/forms.svg') }}" alt="Forms"></div>
        </div>
      </div>
    </div>
    <div class="row gx-4" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1600">
      @foreach ($plans as $plan)
      <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
        <div class="plan-box workspace-height d-flex flex-column justify-content-between">
          <div class="">
            <div class="best-value">
            <h3 class="div-text mb-0 weight-Regular">{{ $plan['name'] }}</h3>
            <p class="mb-0">Recommended for growing businesses</p>
          </div>
          <div class="plans-details">
            <div class="plans-details-text">
              <h4 class="div-title mb-0 weight-SemiBold">₹{{ $plan['price'] }}</h4>
              <span class="extra-small-text d-block mb-0">User / Year</span>
            </div>
            <div class="gst-bg"><p class="small-text renews">GST 18% Additional</p></div>
            <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
              <div class="qty-wrapper ">
                <button class="qty-btn minus">-</button>
                <input type="number" class="qty-input" value="1" min="1">
                <button class="qty-btn plus">+</button>
              </div>
              <div>
                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹{{ $plan['price'] }}</h6>  
              </div>
            </div>
            <ul class="plans-details-list">
              @php
                 $features = DB::table('plan_features')->where(['plan_id'=>$plan['id']])->get()->toArray();
              @endphp
              @foreach ($features as $feature)
                <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow">{{ $feature->feature }}</li>
              @endforeach
            </ul>
          </div>
          </div>
          <div class="plans-details-text plans-details-text-bottom">
            <a href="{{ route('select-plan', $plan['id']) }}" class="buy-now">Buy Now</a>
          </div>
        </div>
      </div>
      @endforeach
      {{-- <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
        <div class="plan-box workspace-height d-flex flex-column justify-content-between">
          <div>
            <div class="best-value">
            <h3 class="div-text mb-0 weight-Regular">Business Standard</h3>
            <p class="mb-0">Ideal for developed establishments </p>
          </div>
          <div class="plans-details">
            <h4 class="small-text mb-0 weight-SemiBold most-popular">Recommended</h4>
            <div class="plans-details-text">
              <h4 class="div-title mb-0 weight-SemiBold">₹10368</h4>
              <span class="extra-small-text d-block mb-0">User / Year</span>
            </div>
            <div class="gst-bg"><p class="small-text renews">GST 18% Additional</p></div>
            <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
              <div class="qty-wrapper ">
                <button class="qty-btn minus">-</button>
                <input type="number" class="qty-input" value="1" min="1">
                <button class="qty-btn plus">+</button>
              </div>
              <div>
                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹10368</h6>  
              </div>
            </div>
            <ul class="plans-details-list">
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow">Custom & secure business email</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow">150 participant video meetings + recording</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow">2 TB cloud storage per user</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow">Advanced security controls</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow">Enhanced collaboration tools</li>
            </ul>
          </div>
          </div>
          <div class="plans-details-text plans-details-text-bottom">
            <button class="buy-now">Buy Now</button>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
        <div class="plan-box workspace-height d-flex flex-column justify-content-between">
          <div>
          <div class="best-value">
            <h3 class="div-text mb-0 weight-Regular">Business Plus</h3>
            <p class="mb-0">Suggested for established organizations </p>
          </div>
          <div class="plans-details">
            <div class="plans-details-text">
              <h4 class="div-title mb-0 weight-SemiBold">₹20400</h4>
              <span class="extra-small-text d-block mb-0">User / Year</span>
            </div>
            <div class="gst-bg"><p class="small-text renews">GST 18% Additional</p></div>
            <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
              <div class="qty-wrapper ">
                <button class="qty-btn minus">−</button>
                <input type="number" class="qty-input" value="1" min="1">
                <button class="qty-btn plus">+</button>
              </div>
              <div>
                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹20400</h6>  
              </div>
            </div>
            <ul class="plans-details-list">
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Advanced email security & retention</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>500</strong> participant video meetings + attendance tracking</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>5 TB</strong> cloud storage per user</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Endpoint management & advanced compliance</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Priority support</li>
            </ul>
          </div>
          </div>
          <div class="plans-details-text plans-details-text-bottom">
              <button class="buy-now">Buy Now</button>
            </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12">
        <div class="plan-box workspace-height d-flex flex-column justify-content-between">
          <div>
            <div class="best-value">
            <h3 class="div-text mb-0 weight-Regular">Enterprise</h3>
            <p class="mb-0">Powered for large-scale companies </p>
          </div>
          <div class="plans-details">
            <div class="plans-details-text">
              <h3 class="div-sub-title mb-0 weight-SemiBold">Contact sales for pricing</h3>
            </div>
            <div class="gst-bg"><p class="small-text renews">GST 18% Additional</p></div>
            <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
              <div class="qty-wrapper ">
                <button class="qty-btn minus">−</button>
                <input type="number" class="qty-input" value="1" min="1">
                <button class="qty-btn plus">+</button>
              </div>
              <div>
                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹2,549</h6>  
              </div>
            </div>
            <ul class="plans-details-list">
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Unlimited storage (as per policy)</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Enterprise-grade security & compliance</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Advanced DLP, encryption & access controls</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Large meetings with live streaming</li>
              <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow">Premium support & account management</li>
            </ul>
          </div>
          </div>
          <div class="plans-details-text plans-details-text-bottom">
              <button class="buy-now">Buy Now</button>
            </div>
        </div>
      </div> --}}
    </div>
  </div>
</section>
<section class="workspace-features-section position-relative pt-100 pb-100">
  <div class="mascot mascot01 mobile-md-none">
    <img src="{{  asset('public/home/images/Mascot-03.png')}}" alt="">
  </div>
  <div class="workspace-text-bg">
    <img src="{{  asset('public/home/images/workspace/Google-Workspace-text.png')}}" class="img" alt="">
  </div>
  <section class="workspace-transfer-section pb-80">
    <div class="container">
      <div class="row" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
        <div class="col-12 text-center">
          <div class="workspace-transfer-text">
            <p class="mb-0">Already have a Google Workspace account with another provider? <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#transferModal">Transfer Now.</a></p>
          </div>
        </div>
      </div>
      <div class="row mt-4" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
        <div class="col-12 workspace-terms-text text-center">
          <p class="mb-2">*Terms and Conditions: New Google Workspace customers only. This introductory price is available for the first 20 users added.</p>
          <p class="mb-0">**Google Workspace offers flexible pooled storage per user, shared across your organization.</p>
        </div>
      </div>
    </div>
  </section>
  <section class="boost-productivity-banner position-relative pt-80 pb-80" style="background-color: var(--color-d1e9e5);">
    <div class="container h-100">
      <div class="row h-100">
        <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="800">
          <div class="iframe-boost-productivity">
            <iframe src="https://www.youtube.com/embed/VOcSrLykC4E?si=RxQ-iaYUybyDFg4l" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
          </div>
        </div>
        <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
          <div class="boost-productivity-text">
            <h2 class="title">Boost Productivity with AI in Google Workspace</h2>
            <h3 class="div-sub-title">Supercharge your business with AI-powered Google Workspace, delivered by Tizzy Cloud.</h3>
            <p class="p-content">Simplify communication, meetings, documents, and daily tasks with seamless collaboration tools. Establish trust with a professional business email that matches your domain, backed by Google’s secure and reliable infrastructure.</p>
            <p>Leverage intelligent AI features to automate workflows, save time, and improve overall efficiency across your organization.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="achieve-section position-relative pt-100 pb-100">
    <div class="container">
      <div class="row">
        <div class="col-12">
          <div class="achieve-title text-center mb-40">
            <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">What can you achieve with Google Workspace?</h2>
          </div>
        </div>
        <div class="col-lg-12">
          <ul class="nav nav-tabs nav-tab-section flex-nowrap justify-content-between achieve-tabs" id="myTab" role="tablist" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab" aria-controls="features" aria-selected="true">Communicate</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="features-tab02" data-bs-toggle="tab" data-bs-target="#features02" type="button" role="tab" aria-controls="features02" aria-selected="false">Collaborate</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="features-tab03" data-bs-toggle="tab" data-bs-target="#features03" type="button" role="tab" aria-controls="features03" aria-selected="false">Manage</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="features-tab04" data-bs-toggle="tab" data-bs-target="#features04" type="button" role="tab" aria-controls="features04" aria-selected="false">Power of Gemini AI</button>
            </li>
          </ul>
          <div class="tab-content" id="myTabContent" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1800">
            <div class="tab-pane fade show active" id="features" role="tabpanel" aria-labelledby="features-tab" tabindex="0">
              <div class="row mt-50">
                <div class="col-xxl-4 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-content">
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/ai.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Communicate Smarter with Google Workspace</h3>
                        <p class="mb-0">Empower your team with seamless communication tools designed for modern businesses — delivered by Tizzy Cloud.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/gemini.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Professional Business Email with Gmail</h3>
                        <p class="mb-0">Create a trusted business presence with a custom email address on your domain, powered by Gmail’s advanced features, security, and reliability.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/notebookLM.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Never Miss a Meeting</h3>
                        <p class="mb-0">Stay organized with a smart calendar that helps you schedule meetings, set reminders, and manage your time efficiently — all in one place.</p>
                      </div>
                    </div>
                    
                  </div>
                </div>
                <div class="col-xxl-8 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-img">
                    <img src="{{  asset('public/home/images/workspace/Communicate.webp')}}" class="img" alt="">
                  </div>
                </div>
              </div>
            </div>
            <div class="tab-pane fade" id="features02" role="tabpanel" aria-labelledby="features-tab02" tabindex="0">
              <div class="row mt-50">
                <div class="col-xxl-4 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-content">
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/ai.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Collaborate Seamlessly</h3>
                        <p class="mb-0">Empower your team to work smarter and faster with real-time collaboration tools powered by Google Workspace.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/gemini.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Intelligent Collaboration</h3>
                        <p class="mb-0">Store your files securely in the cloud and collaborate in real time with your team — anytime, anywhere. Work together on documents, spreadsheets, and presentations without version conflicts.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/notebookLM.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Smart Search Across Everything</h3>
                        <p class="mb-0">Quickly find files, emails, and information across your organization using powerful cloud search. Access what you need, exactly when you need it.</p>
                      </div>
                    </div>
                    
                  </div>
                </div>
                <div class="col-xxl-8 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-img">
                    <img src="{{  asset('public/home/images/workspace/Collaborate.webp')}}" class="img" alt="">
                  </div>
                </div>
              </div>
            </div>
            <div class="tab-pane fade" id="features03" role="tabpanel" aria-labelledby="features-tab03" tabindex="0">
              <div class="row mt-50">
                <div class="col-xxl-4 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-content">
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/ai.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Manage with Confidence</h3>
                        <p class="mb-0">Take full control of your business operations with powerful admin tools and enterprise-grade security — all in one place.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/gemini.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Efficient Admin Control</h3>
                        <p class="mb-0">Easily manage users, groups, and permissions from a centralized admin console. Simplify onboarding, access control, and day-to-day management with complete visibility.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/notebookLM.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Secure Data Archiving</h3>
                        <p class="mb-0">Store, retain, and archive your business data effortlessly with Google Vault. Ensure compliance and never lose critical information.</p>
                      </div>
                    </div>
                    
                  </div>
                </div>
                <div class="col-xxl-8 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-img">
                    <img src="{{  asset('public/home/images/workspace/Manage.webp')}}" class="img" alt="">
                  </div>
                </div>
              </div>
            </div>
            <div class="tab-pane fade" id="features04" role="tabpanel" aria-labelledby="features-tab04" tabindex="0">
              <div class="row mt-50">
                <div class="col-xxl-4 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-content">
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/ai.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Power of Gemini AI</h3>
                        <p class="mb-0">Unlock next-level productivity with advanced AI capabilities built directly into your Google Workspace — seamlessly delivered by Tizzy Cloud.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/gemini.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">AI Assistance Across Workspace</h3>
                        <p class="mb-0">Enhance your daily workflow with intelligent AI integrated into Gmail, Docs, Sheets, Meet, Chat, and more. Automate tasks, generate content, and boost efficiency across every tool you use.</p>
                      </div>
                    </div>
                    <div class="achieve-box d-flex align-items-start gap-3">
                      <img src="{{  asset('public/home/images/icons/notebookLM.png')}}" alt="">
                      <div class="">
                        <h3 class="div-text mb-0">Gemini Advanced</h3>
                        <p class="mb-0">Simplify complex work with powerful AI support for coding, research, data analysis, brainstorming, and planning. Turn ideas into action faster with smarter insights and automation.</p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-xxl-8 col-lg-6 col-md-6 col-sm-12 col-12 d-flex align-items-center">
                  <div class="achieve-img">
                    <img src="{{  asset('public/home/images/workspace/power_gemini.webp')}}" class="img" alt="">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div class="container">
    <div class="row">
      <div class="col-12">
        <div class="achieve-title text-center mb-40">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">What's included with Google Workspace</h2>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-12">
        <div class="timeline">
          <!-- Horizontal Line -->
          <span class="timeline-line" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600"></span>

          <div class="row">
            <div class="col-lg-4 col-md-4 col-6">
              <div class="timeline-item top" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/cloud.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h2 class="div-text">Ample Cloud Storage</h2>
                  <p class="mb-0">Store, manage, and access all your important files and data with generous cloud storage—no space worries.</p>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-4 col-6">
              <div class="timeline-item top" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/work-anywhere.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h4 class="div-text">Work From Anywhere</h4>
                  <p class="mb-0">Stay productive wherever you are with Google Workspace apps available on iOS, Android, and Windows devices.</p>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-4 col-6">
              <div class="timeline-item top" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/support.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h4 class="div-text">Quick Expert Support</h4>
                  <p class="mb-0">Get round-the-clock assistance whenever you need it,  backed by a dedicated support team.</p>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-4 col-6 timeline-desktop-none">
              <div class="timeline-item bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/collaboration.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h4 class="div-text">Real-Time Team Collaboration</h4>
                  <p class="mb-0">Collaborate effortlessly using Docs, Sheets, and Slides with real-time editing and instant updates.</p>
                </div>
              </div>
            </div>
             <div class="col-lg-4 col-md-4 col-6 timeline-desktop-none">
              <div class="timeline-item bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/security.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h4 class="div-text">Enterprise-Grade Security</h4>
                  <p class="mb-0">Protect your data with advanced security features including encryption and multi-factor authentication.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="row timeline-mobile-none">
             <div class="col-lg-4 col-md-4 col-6">
              <div class="timeline-item bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/collaboration.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h4 class="div-text">Real-Time Team Collaboration</h4>
                  <p class="mb-0">Collaborate effortlessly using Docs, Sheets, and Slides with real-time editing and instant updates.</p>
                </div>
              </div>
            </div>
             <div class="col-lg-4 col-md-4 col-6">
              <div class="timeline-item bottom" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
                <div class="icon-circle">
                  <img src="{{  asset('public/home/images/icons/security.png')}}" alt="">
                </div>
                <div class="timeline-text">
                  <h4 class="div-text">Enterprise-Grade Security</h4>
                  <p class="mb-0">Protect your data with advanced security features including encryption and multi-factor authentication.</p>
                </div>
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
          <img src="{{  asset('public/home/images/icons/quote.png')}}" class="quote" alt="" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600"> 
          <h2 class="div-title color-333233" data-aos="fade-left" data-aos-easing="linear" data-aos-duration="600">What Our Customers Are Saying</h2> 
          <div class="mt-50" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <div class="swiper-pagination-testimonial"></div>
            <div class="swiper-button-next" role="button" aria-label="Next testimonial slide" tabindex="0"><img src="{{  asset('public/home/images/icons/testi-arrow.png')}}" alt=""></div>
            <div class="swiper-button-prev" role="button" aria-label="Previous testimonial slide" tabindex="0"><img src="{{  asset('public/home/images/icons/testi-arrow2.png')}}" alt=""></div>
          </div> 
        </div>
        <div class="testimonial-right" data-aos="fade-right" data-aos-easing="linear" data-aos-duration="600">
          <div class="testimonial_slider swiper-container">
            <div class="swiper-wrapper">
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we  were about to migrate all 200 email users to either Google or Microsoft. After  trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>IP blacklisting issue, UCE Protect level 2 and level 3 listing issues, spamming issues, other DNS related issues all were solved after moving all 350 emails to Tizzy Mail. We always wanted Indian Data Center and with Tizzy Mail all our Email Data is now stored only in India.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Incoming spam emails are reduced to 60-70%, almost ZERO complaints from our customers, quick response to our emails. All IT companies should offer only Tizzy Mail service to their clients.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we were about to migrate all 200 email users to either Google or Microsoft. After trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>IP blacklisting issue, UCE Protect level 2 and level 3 listing issues, spamming issues, other DNS related issues all were solved after moving all 350 emails to Tizzy Mail. We always wanted Indian Data Center and with Tizzy Mail all our Email Data is now stored only in India.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Incoming spam emails are reduced to 60-70%, almost ZERO complaints from our customers, quick response to our emails. All IT companies should offer only Tizzy Mail service to their clients.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{  asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we were about to migrate all 200 email users to either Google or Microsoft. After trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{  asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{  asset('public/home/images/user.png')}}" alt="User">
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
    <img src="{{  asset('public/home/images/Mascot-02.png')}}" alt="">
  </div> -->
  <div class="container">
    <div class="row mb-80 justify-content-center">
      <div class="col-xl-8 col-lg-8 col-md-8 col-12">
        <div class="faq-title text-center">
          <h2 class="title mb-2" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">Frequently Asked Questions</h2>
          <!-- <p class="mb-0" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">We are glad to answer the frequently asked questions. You can email us on sales@tizzycloud.com for any further queries.</p> -->
        </div>
      </div>
    </div>
    <div class="row justify-content-center">
      <div class="col-xxl-8 col-lg-10 col-md-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
        <div class="accordion faq-accordion" id="accordionExample">
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <h2 class="accordion-header" id="headingOne">
              <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                What is a user?
              </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">A user is defined as one personalized email address (you@yourcompany). Group email aliases such as sales@yourcompany and support@yourcompany are included in pricing and don't count as additional users.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <h2 class="accordion-header" id="headingTwo">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
               Can I purchase individual Google Workspace apps?
              </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
               <p class="mb-0">No. When you sign up for Google Workspace, you'll gain access to a set of integrated apps that work together seamlessly. For instance, you can receive a message in Gmail and instantly convert it into a Calendar event. When you make a comment in Docs, Sheets or Slides, collaborators automatically receive email alerts. With a single click, you can join a video meeting in Meet from your inbox or calendar. Using these tools together improves productivity and gives your business the most value. However, you are welcome to purchase Google Workspace and only use the services of your choice.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <h2 class="accordion-header" id="headingThree">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
               What is the payment plan?
              </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">We offer a flexible payment plan. You can add and delete users at anytime. You are billed Yearly. For more information on pricing, Contact <a href="{{ url('contact') }}">Tizzy Cloud Sales Team</a>.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="boost-business-section position-relative" style="background-color: var(--color-f4f9ff);">
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="boost-business-text">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">We help you boost your business</h2>
          <p class="p-content" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Empower your team with smart tools, secure communication, and AI-driven productivity—built to help your business grow faster.</p>
          <ul class="workspace-list  mt-40" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="900">
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>Professional</strong> email with your business domain</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>Seamless team</strong> collaboration and communication</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>AI-powered</strong> tools to work smarter and faster</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>Secure cloud </strong> storage and enterprise-grade security</li>
            <li><img src="{{  asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"><strong>Reliable support</strong> whenever you need it</li>
          </ul>
          <div class="d-flex gap-5 mt-40">
          <a href="{{ url('contact') }}" class="tp-btn-black" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Get Started</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
          </a>
          <a href="{{ url('contact') }}" class="tp-btn-black tp-btn-white tp-btn-white2" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="900">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Talk to an Expert</span>
              <span class="tp-btn-black-circle">
                <svg width="10" height="10" viewbox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 9L9 1M9 1H1M9 1V9" stroke="currentcolor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
              </span>
            </span>
          </a>
          </div>
          <div class="feature-strip d-flex flex-wrap justify-content-center align-items-center gap-4" data-aos="zoom-in" data-aos-easing="linear" data-aos-duration="600">
            <div class="feature-item">
              <img src="{{  asset('public/home/images/icons/security-black.png')}}" alt="">
              <span>Secure & Reliable</span>
            </div>
            <span class="divider">|</span>
            <div class="feature-item">
              <img src="{{  asset('public/home/images/icons/googl-logo.png')}}" alt="">
              <span>Google-powered solutions</span>
            </div>
            <span class="divider">|</span>
            <div class="feature-item">
              <img src="{{  asset('public/home/images/icons/24-call-support.png')}}" alt="">
              <span>Quick Expert Support</span>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-end">
        <div class="banner-img">
          <img src="{{  asset('public/home/images/workspace/boost-business-img.png')}}" class="img" alt="">
        </div>
      </div>
    </div>
  </div>
</section>
<!-- Transfer Modal -->
<div class="modal fade transfer-modal" id="transferModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="btn-close custom-close" data-bs-dismiss="modal"></button>
      <!-- Header -->
      <div class="transfer-header">
        <h3 class="div-sub-title">Transfer Your Google Workspace Account</h3>
      </div>
      <div class="row g-0">
        <div class="col-lg-6 border-end">
          <div class="transfer-left">
            <h5>Transfer Identifier</h5>
            <p class="small-text">
              Use the identifier below to initiate the transfer from your existing Google Workspace Admin Console.
            </p>
            <div class="copy-box">
              <input type="text" id="copyText" value="C016a5q7s" readonly>
              <button onclick="copyText()"><img src="{{  asset('public/home/images/icons/copy.svg')}}" title="Copy Text" alt="copyText"></button>
            </div>
            <label class="mt-4">Enter your domain</label>
            <p>Provide your domain name to begin the transfer process.</p>
            <input type="text" class="form-control mt-2" placeholder="example.com">
            <button class="transfer-btn mt-4">
              Transfer My Account →
            </button>

            <div class="error-sms mt-4 d-none">
              <p>We’re currently unable to retrieve your transfer details. This may be due to IP address mismatch or incorrect RPI (Reseller Public Identifier).</p>
              <p class="p-content weight-SemiBold">Please ensure:</p>
              <p class="mb-0">You are using the same network (IP) used to initiate the transfer</p>
              <p class="mb-0">The correct transfer identifier (RPI) is entered</p>
            </div>
          </div>
        </div>
        <!-- RIGHT -->
        <div class="col-lg-6">
          <div class="transfer-right">
            <h4>How Does It Work?</h4>
            <p>We securely migrate all your email accounts from your current provider</p>
            <p>No data loss – all emails, files, and settings remain intact</p>
            <p>Your admin account and login credentials stay unchanged</p>
            <p>Minimal to zero downtime during the transfer</p>
            <div class="note">
              <strong>Note:</strong> Your existing subscription tenure with the previous provider will not be carried forward.
            </div>
            <h6 class="mt-4">Need help?</h6>
            <p>Still have questions? Our support team is here to assist you at every step.</p>
            <a href="#" class="support-link">Contact Support</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection