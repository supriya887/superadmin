@extends('home.render')

@section('metaTitle')
  <title>Tizzy Cloud</title>
  <meta name="description" content="" />
  <meta name="keywords" content="" />
@endsection


@section('Body')
<section class="home-banner position-relative">
  <div class="microsoft-banner">
    <img src="{{ asset('public/home/images/microsoft/banner.jpg') }}" class="img" alt="microsoft banner">
  </div>
  <div class="container h-100">
    <div class="row h-100">
        <a href="{{ url('cloud-microsoft-365') }}">cloud-microsoft-365</a>
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="banner-text">
          <h1 class="title mb-0">Microsoft 365 for Modern Workplaces</h1>
          <p class="p-content">Work smarter, collaborate faster, and stay secure with Microsoft 365tools.</p>
          <ul class="workspace-list mt-40" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Professional business email with your domain</li>
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Seamless communication & team collaboration</li>
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Cloud apps for productivity anytime, anywhere</li>
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png') }}" alt="check-arrow"> Enterprise-grade security & compliance</li>
          </ul>
          <div class="d-flex flex-column flex-lg-row gap-5 mt-40">
            <a href="https://marketplace.microsoft.com/en-us/partners/052e1b67-9819-44bd-a613-7f6818c78543/overview/contact" target="_blank" class="tp-btn-black">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">Authorized Microsoft Partner</span>
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
<section class="workspace-plan-section pt-100 pb-100" id="pricing">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-xl-7 col-lg-9 col-md-12 col-12">
        <div class="workspace-plan-title text-center">
          <h2 class="div-sub-title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Find the Perfect Microsoft 365 Plan for Your Business</h2>
          <p data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">Flexible productivity, collaboration, and security tools designed for modern teams.</p>
        </div>
      </div>
    </div>
    <div class="row justify-content-center mt-20 mb-50">
      <div class="col-xl-12 col-lg-12 col-md-12 col-12">
        <div class="workspace-include" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1600">
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png') }}" alt="Microsoft Exchange"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png') }}" alt="OneDrive"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png') }}" alt="Microsoft Office SharePoint"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Teams.png') }}" alt="Microsoft Teams"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png') }}" alt="Microsoft Office PowerPoint"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png') }}" alt="Microsoft Outlook"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png') }}" alt="Microsoft Loop"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png') }}" alt="Microsoft Office OneNote"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png') }}" alt="Microsoft Office Word"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Outlook.png') }}" alt="Microsoft Office Outlook"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Forms.png') }}" alt="Microsoft Forms"></div>
          <div class="workspace-include-list"><img src="{{ asset('public/home/images/microsoft/microsoft-365/Windows_Defender.png') }}" alt="Windows Defender"></div>
        </div>
      </div>
    </div>
    <div class="row justify-content-center">
      <div class="col-lg-12 col-md-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
        <nav class="monthly-yearly-plan d-flex flex-column align-items-center">
          <div class="nav nav-tabs mb-20" id="nav-tab" role="tablist">
            <button type="button" class="nav-link active" id="nav-monthly-tab" data-bs-toggle="tab" data-bs-target="#nav-monthly" role="tab" aria-controls="nav-monthly" aria-selected="true">Plans with Teams</button>
            <button type="button" class="nav-link" id="nav-yearly-tab" data-bs-toggle="tab" data-bs-target="#nav-yearly" role="tab" aria-controls="nav-yearly" aria-selected="false">Plans without Teams</button>
            <button type="button" class="nav-link" id="nav-Copilot-tab" data-bs-toggle="tab" data-bs-target="#nav-Copilot" role="tab" aria-controls="nav-yearly" aria-selected="false">Plans with Copilot</button>
          </div>
        </nav>
        <div class="tab-content monthly-yearly-tab" id="nav-tabContent">
          <div class="tab-pane fade show active" id="nav-monthly" role="tabpanel">
            <div class="row gx-4">
              @foreach ($PlanswithTeams as $plan)
              <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div class="">
                    <div class="best-value">
                     <h3 class="div-text mb-0 weight-Regular">{{ $plan['title'] }}</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">Everything your business needs to be more productive and secure</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ {{ number_format($plan['price'], 2) }}</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                      {{-- <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
                        <div class="qty-wrapper ">
                          <button class="qty-btn minus">−</button>
                          <input type="number" class="qty-input" value="1" min="1">
                          <button class="qty-btn plus">+</button>
                        </div>
                        <div>
                          <h6 class="div-text mb-0 weight-SemiBold color-000000">₹20400</h6>  
                        </div>
                      </div> --}}
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Advanced identity and access management for up to 300 users</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Chat, call, meet, and host webinars with Microsoft Teams​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">1 TB of cloud storage per user​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">AI chat experience with web grounding, writing assistance, data analysis, and access to agents4​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Enhanced cyberthreat protection against viruses and phishing attacks​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Discovery, classification, and protection of sensitive data​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png') }}" alt="Check Arrow">Anytime phone and web support​</li>
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                    </div>
                  </div>
                  <div class="">
                    <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft-Entra-ID.svg')}}" alt="Microsoft Entra ID"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/windows-11.png')}}" alt="Windows 11"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/intune-icon.png')}}" alt="Intune Icon"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Windows_Defender.png')}}" alt="Windows Defender"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Purview.png')}}" alt="Purview"></li>
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                      <a href="{{ route('select-plan', $plan['id']) }}" class="buy-now">Buy Now</a>
                    </div>
                  </div>  
                </div>
              </div>
              @endforeach
              {{-- <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div>
                    <div class="best-value">
                      <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Standard</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">Powerful web, mobile, and desktop apps to run your business</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ 770.00</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
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
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Chat, call, meet, and host webinars with Microsoft Teams​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI chat experience with web grounding, writing assistance, data analysis, and access to agents4​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li>
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                    </div>
                  </div>
                  <div class="">
                    <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                      <button class="buy-now">Buy Now</button>
                    </div>  
                  </div>  
                </div>
              </div>
              <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div>
                    <div class="best-value">
                      <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Basic</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">Lightweight web and mobile apps to kickstart your business</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ 145.00</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
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
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web and mobile versions3 of Word, Excel, PowerPoint, and Outlook​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Chat, call, and meet with Microsoft Teams​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Bookings, Planner, and Forms)​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI chat experience with web grounding, writing assistance, data analysis, and access to agents4</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering​</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​​</li>
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                        
                    </div>
                  </div>
                  <div class="">
                    <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                       <button class="buy-now">Buy Now</button>
                    </div> 
                  </div>  
                </div>
              </div> --}}
            </div>
          </div>
          <div class="tab-pane fade" id="nav-yearly" role="tabpanel">
            <div class="row gx-4">
              @foreach ($PlanswithoutTeams as $planWT)
               <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div class="">
                    <div class="best-value">
                     <h3 class="div-text mb-0 weight-Regular">{{ $planWT['title'] }}</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">{{ $planWT['description'] }}</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ {{ number_format($planWT['price'], 2) }}</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                      {{-- <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
                        <div class="qty-wrapper ">
                          <button class="qty-btn minus">−</button>
                          <input type="number" class="qty-input" value="1" min="1">
                          <button class="qty-btn plus">+</button>
                        </div>
                        <div>
                          <h6 class="div-text mb-0 weight-SemiBold color-000000">₹20400</h6>  
                        </div>
                      </div> --}}
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              @php
                                $featuresWT = DB::table('plan_features')->where('plan_id', $planWT['id'])->get();
                              @endphp
                              @foreach($featuresWT as $feature)
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">{{ $feature->feature }}</li>
                              @endforeach
                              {{-- <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI chat experience with web grounding, writing assistance, data analysis, and access to agents4</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Enhanced cyberthreat protection against viruses and phishing attacks</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Discovery, classification, and protection of sensitive data</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li> --}}
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                    </div>
                  </div>
                  <div class="">
                    <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft-Entra-ID.svg')}}" alt="Microsoft Entra ID"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/windows-11.png')}}" alt="Windows 11"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/intune-icon.png')}}" alt="Intune Icon"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Windows_Defender.png')}}" alt="Windows Defender"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Purview.png')}}" alt="Purview"></li>
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                      <a href="{{ route('select-plan', $planWT['id']) }}" class="buy-now">Buy Now</a>
                    </div>  
                  </div>  
                </div>
              </div>
              @endforeach
              {{-- <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div class="">
                    <div class="best-value">
                     <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Standard (no Teams)</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">Powerful web, mobile, and desktop apps to run your business</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ 570.00</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
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
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI chat experience with web grounding, writing assistance, data analysis, and access to agents4</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li>
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                    </div>
                  </div>
                  <div class="">
                    <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                      
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                      <button class="buy-now">Buy Now</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div class="">
                    <div class="best-value">
                     <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Basic (no Teams)</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">Lightweight web and mobile apps to kickstart your business</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ 105.00</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
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
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web and mobile versions3 of Word, Excel, PowerPoint, and Outlook</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Bookings, Planner, and Forms)</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI chat experience with web grounding, writing assistance, data analysis, and access to agents4</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li>
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                    </div>
                  </div>
                  <div class="">
                   <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft SharePoint"></li>
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                      <button class="buy-now">Buy Now</button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                  <div class="">
                    <div class="best-value">
                     <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Apps for business</h3>
                    </div>
                    <div class="plans-details">
                      <div class="plans-details-text">
                        <div class="gst-bg" style="padding: 0;background: transparent;">
                        <p class="small-text renews">Popular desktop apps, excluding chat or calling</p>
                        </div>
                        <h3 class="div-title mb-0 weight-SemiBold">₹ 685.00</h3>
                        <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                      </div>
                      <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
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
                        <div class="">
                          <div class="qty-wrapper-bg features">
                            <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                          </div>
                          <div class="more-details-box">
                            <ul class="plans-details-list">
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Available for up to 300 users</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Desktop versions of Word, Excel, PowerPoint, and Outlook</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                              <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li>
                            </ul>
                            <div class="features-bottom">
                              <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                            </div>
                          </div>
                        </div> 
                    </div>
                  </div>
                  <div class="">
                    <ul class="plans-apps-list">
                      <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Word"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft PowerPoint"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft OneNote"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                      <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                    </ul>
                    <div class="plans-details-text plans-details-text-bottom">
                      <button class="buy-now">Buy Now</button>
                    </div>
                  </div>
                  
                </div>
              </div> --}}
            </div>
          </div>
          <div class="tab-pane fade" id="nav-Copilot" role="tabpanel">
            <div class="row gx-4">
              <nav class="with-without-teams d-flex flex-column align-items-lg-end align-items-center">
                <div class="nav nav-tabs mb-20" id="nav-tab2" role="tablist">
                  <button type="button" class="nav-link active" id="nav-monthly-tab2" data-bs-toggle="tab" data-bs-target="#nav-monthly2" role="tab" aria-controls="nav-monthly2" aria-selected="true">With Teams</button>
                  <button type="button" class="nav-link" id="nav-yearly-tab2" data-bs-toggle="tab" data-bs-target="#nav-yearly2" role="tab" aria-controls="nav-yearly2" aria-selected="false">Without Teams</button>
                </div>
              </nav>
              <div class="tab-content monthly-yearly-tab" id="nav-tabContent2">
                <div class="tab-pane fade show active" id="nav-monthly2" role="tabpanel">
                  <div class="row gx-4">
                    @foreach($with_copilot_WithTeams as $planCP)
                    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                      <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                        <div class="">
                          <div class="best-value">
                          <h3 class="div-text mb-0 weight-Regular">{{ $planCP['title'] }}</h3>
                          </div>
                          <div class="plans-details">
                            @if($planCP['limited_offer'] == 1)
                            <h4 class="small-text mb-0 weight-SemiBold most-popular most-popular-offer">Limited time discount offer*</h4>
                            @endif
                            <div class="plans-details-text">
                              <div class="gst-bg" style="padding: 0;background: transparent;">
                              <p class="small-text renews">{{ $planCP['description'] }}</p>
                              </div>
                              @if($planCP['actual_price'] != null)
                              <h4 class="div-text mb-0 weight-SemiBold"><s>₹ {{ number_format($planCP['actual_price'], 2) }}</s></h4>
                              @endif
                              <h4 class="div-title mb-0 weight-SemiBold">₹ {{ number_format($planCP['price'], 2) }}</h4>
                              <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                            </div>
                            <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                            <!-- <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
                              <div class="qty-wrapper ">
                                <button class="qty-btn minus">−</button>
                                <input type="number" class="qty-input" value="1" min="1">
                                <button class="qty-btn plus">+</button>
                              </div>
                              <div>
                                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹20400</h6>  
                              </div>
                            </div> -->
                              <div class="">
                                <div class="qty-wrapper-bg features">
                                  <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                                </div>
                                <div class="more-details-box">
                                  <ul class="plans-details-list">
                                    @php  
                                       $features = DB::table('plan_features')->where('plan_id', $planCP['id'])->get();
                                    @endphp
                                    @foreach($features as $feature)
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">{{ $feature->feature }}</li>
                                    @endforeach
                                    {{-- <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI-powered chat connected to your work data and access to reasoning AI for research and data analysis</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Advanced identity and access management for up to 300 users​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Chat, call, meet, and host webinars with Microsoft Teams</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Enhanced cyberthreat protection against viruses and phishing attacks​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Discovery, classification, and protection of sensitive data​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li> --}}
                                  </ul>
                                  <div class="features-bottom">
                                    <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                                  </div>
                                </div>
                              </div> 
                          </div>
                        </div>
                        <div class="">
                          <ul class="plans-apps-list">
                            <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/copilot.svg')}}" alt="Copilot"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft-Entra-ID.svg')}}" alt="Microsoft Entra ID"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/windows-11.png')}}" alt="windows 11"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/intune-icon.png')}}" alt="intune-icon"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Windows_Defender.png')}}" alt="Windows Defender"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Purview.png')}}" alt="Purview"></li>
                          </ul>
                          <div class="plans-details-text plans-details-text-bottom">
                            <a href="{{ url('select-plan/'.$planCP['id']) }}" class="buy-now">Buy Now</a>
                          </div>
                        </div>  
                      </div>
                    </div>
                    @endforeach
                    {{-- <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                      <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                        <div>
                          <div class="best-value">
                            <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Standard and Microsoft 365 Copilot Business</h3>
                          </div>
                          <div class="plans-details">
                            <h4 class="small-text mb-0 weight-SemiBold most-popular most-popular-offer">Limited time discount offer*</h4>
                            <div class="plans-details-text">
                              <div class="gst-bg" style="padding: 0;background: transparent;">
                              <p class="small-text renews">Powerful web, mobile, and desktop apps combined with Microsoft 365 Copilot</p>
                              </div>
                              <h5 class="div-text mb-0 weight-SemiBold"><s>₹ 2,785.00</s></h5>
                              <h6 class="div-title mb-0 weight-SemiBold">₹ 1,828.97</h6>
                              <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                            </div>
                            <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                            <!-- <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
                              <div class="qty-wrapper ">
                                <button class="qty-btn minus">−</button>
                                <input type="number" class="qty-input" value="1" min="1">
                                <button class="qty-btn plus">+</button>
                              </div>
                              <div>
                                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹20400</h6>  
                              </div>
                            </div> -->
                              <div class="">
                                <div class="qty-wrapper-bg features">
                                  <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                                </div>
                                <div class="more-details-box">
                                  <ul class="plans-details-list">
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Microsoft 365 Copilot in apps such as Word, Excel, PowerPoint, Outlook, and Microsoft Teams5</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI-powered chat connected to your work data and access to reasoning AI for research and data analysis</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Chat, call, meet, and host webinars with Microsoft Teams</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li>
                                  </ul>
                                  <div class="features-bottom">
                                    <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                                  </div>
                                </div>
                              </div> 
                          </div>
                        </div>
                        <div class="">
                          <ul class="plans-apps-list">
                            <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                           <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/copilot.svg')}}" alt="Copilot"></li>
                          </ul>
                          <div class="plans-details-text plans-details-text-bottom">
                            <button class="buy-now">Buy Now</button>
                          </div>  
                        </div>  
                      </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                      <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                        <div>
                          <div class="best-value">
                            <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Basic and Microsoft 365 Copilot Business</h3>
                          </div>
                          <div class="plans-details">
                            <div class="plans-details-text">
                              <div class="gst-bg" style="padding: 0;background: transparent;">
                              <p class="small-text renews">Lightweight web and mobile apps combined with Microsoft 365 Copilot</p>
                              </div>
                              <h4 class="div-title mb-0 weight-SemiBold">₹ 2,245.00</h4>
                              <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                            </div>
                            <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                            <!-- <div class="qty-wrapper-bg d-flex align-items-center justify-content-between">
                              <div class="qty-wrapper ">
                                <button class="qty-btn minus">−</button>
                                <input type="number" class="qty-input" value="1" min="1">
                                <button class="qty-btn plus">+</button>
                              </div>
                              <div>
                                <h6 class="div-text mb-0 weight-SemiBold color-000000">₹20400</h6>  
                              </div>
                            </div> -->
                              <div class="">
                                <div class="qty-wrapper-bg features">
                                  <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                                </div>
                                <div class="more-details-box">
                                  <ul class="plans-details-list">
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Microsoft 365 Copilot in apps such as Word, Excel, PowerPoint, Outlook, and Microsoft Teams5</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI-powered chat connected to your work data and access to reasoning AI for research and data analysis</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web and mobile versions3 of Word, Excel, PowerPoint, and Outlook</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Chat, call, and meet with Microsoft Teams</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Bookings, Planner, and Forms)</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​​</li>
                                  </ul>
                                  <div class="features-bottom">
                                    <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                                  </div>
                                </div>
                              </div> 
                              
                          </div>
                        </div>
                        <div class="">
                          <ul class="plans-apps-list">
                            <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                           <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/copilot.svg')}}" alt="Copilot"></li>
                          </ul>
                          <div class="plans-details-text plans-details-text-bottom">
                            <button class="buy-now">Buy Now</button>
                          </div> 
                        </div>  
                      </div>
                    </div> --}}
                  </div>
                </div>
                <div class="tab-pane fade" id="nav-yearly2" role="tabpanel">
                  <div class="row gx-4">
                    @foreach($with_copilot_WithoutTeams as $planCP)
                    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                      <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                        <div class="">
                          <div class="best-value">
                          <h3 class="div-text mb-0 weight-Regular">{{ $planCP['title'] }}</h3>
                          </div>
                          <div class="plans-details">
                             @if($planCP['limited_offer'] == 1)
                            <h4 class="small-text mb-0 weight-SemiBold most-popular most-popular-offer">Limited time discount offer*</h4>
                            @endif
                            <div class="plans-details-text">
                              <div class="gst-bg" style="padding: 0;background: transparent;">
                              <p class="small-text renews">{{ $planCP['description'] }}</p>
                              </div>
                              @if($planCP['actual_price'])
                                <h5 class="div-text mb-0 weight-SemiBold"><s>₹ {{ number_format($planCP['actual_price'], 2) }}</s></h5>
                              @endif
                              <h6 class="div-title mb-0 weight-SemiBold">₹ {{ number_format($planCP['price'], 2) }}</h6>
                              <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                            </div>
                            <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                            
                              <div class="">
                                <div class="qty-wrapper-bg features">
                                  <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                                </div>
                                <div class="more-details-box">
                                  <ul class="plans-details-list">
                                    @php 
                                       $featureCP = DB::table('plan_features')->where('plan_id',$planCP['id'])->get();
                                      //  print_r($featureCP);
                                      //  die;
                                    @endphp
                                    @foreach($featureCP as $feature)
                                      <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">{{ $feature->feature }}</li>
                                    @endforeach
                                    {{-- <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Microsoft 365 Copilot in apps such as Word, Excel, PowerPoint, and Outlook5​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI-powered chat connected to your work data and access to reasoning AI for research and data analysis</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Advanced identity and access management for up to 300 users​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Enhanced cyberthreat protection against viruses and phishing attacks</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Discovery, classification, and protection of sensitive data</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​</li> --}}
                                  </ul>
                                  <div class="features-bottom">
                                    <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                                  </div>
                                </div>
                              </div> 
                          </div>
                        </div>
                        <div class="">
                          <ul class="plans-apps-list">
                            <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/copilot.svg')}}" alt="Copilot"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft-Entra-ID.svg')}}" alt="Microsoft Entra ID"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/windows-11.png')}}" alt="windows 11"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/intune-icon.png')}}" alt="intune-icon"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Windows_Defender.png')}}" alt="Windows Defender"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Purview.png')}}" alt="Purview"></li>
                          </ul>
                          <div class="plans-details-text plans-details-text-bottom">
                            <a class="buy-now" href="{{ url('select-plan/'.$planCP['id']) }}">Buy Now</a>
                          </div>
                        </div>  
                      </div>
                    </div>
                    @endforeach
                    {{-- <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                      <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                        <div>
                          <div class="best-value">
                            <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Standard (No Teams) and Microsoft 365 Copilot Business</h3>
                          </div>
                          <div class="plans-details">
                            <h4 class="small-text mb-0 weight-SemiBold most-popular most-popular-offer">Limited time discount offer*</h4>
                            <div class="plans-details-text">
                              <div class="gst-bg" style="padding: 0;background: transparent;">
                              <p class="small-text renews">Powerful web, mobile, and desktop apps combined with Microsoft 365 Copilot</p>
                              </div>
                              <h5 class="div-text mb-0 weight-SemiBold"><s>₹ 2,520.00</s></h5>
                              <h6 class="div-title mb-0 weight-SemiBold">₹ 1,654.93</h6>
                              <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                            </div>
                            <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                            
                              <div class="">
                                <div class="qty-wrapper-bg features">
                                  <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                                </div>
                                <div class="more-details-box">
                                  <ul class="plans-details-list">
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Microsoft 365 Copilot in apps such as Word, Excel, PowerPoint, and Outlook5​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI-powered chat connected to your work data and access to reasoning AI for research and data analysis</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web, mobile, and desktop versions3 of Word, Excel, PowerPoint, and Outlook</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Loop, Clipchamp, Bookings, Planner, and Forms)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support</li>
                                  </ul>
                                  <div class="features-bottom">
                                    <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                                  </div>
                                </div>
                              </div> 
                          </div>
                        </div>
                        <div class="">
                          <ul class="plans-apps-list">
                            <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt="Microsoft Excel"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt="Microsoft Office PowerPoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt="Microsoft Office OneNote"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Loop.png')}}" alt="Microsoft Loop"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt="Clipchamp"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt="Microsoft Outlook"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt="Microsoft Exchange"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/apps-editor.png')}}" alt="Apps Editor"></li>
                           <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/copilot.svg')}}" alt="Copilot"></li>
                          </ul>
                          <div class="plans-details-text plans-details-text-bottom">
                            <button class="buy-now">Buy Now</button>
                          </div>  
                        </div>  
                      </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 col-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
                      <div class="plan-box workspace-plan-box d-flex flex-column justify-content-between">
                        <div>
                          <div class="best-value">
                            <h3 class="div-text mb-0 weight-Regular">Microsoft 365 Business Basic (No Teams) and Microsoft 365 Copilot Business</h3>
                          </div>
                          <div class="plans-details">
                            
                            <div class="plans-details-text">
                              <div class="gst-bg" style="padding: 0;background: transparent;">
                              <p class="small-text renews">Lightweight web and mobile apps combined with Microsoft 365 Copilot</p>
                              </div>
                              
                              <h3 class="div-title mb-0 weight-SemiBold">₹ 2,115.00</h3>
                              <span class="extra-small-text d-block mb-0">user/month, paid yearly</span>
                            </div>
                            <div class="gst-bg"><p class="small-text renews">(Annual subscription–auto renews) <br> GST extra as applicable</p></div>
                            
                              <div class="">
                                <div class="qty-wrapper-bg features">
                                  <h4 class="p-content mb-0 weight-SemiBold">Plan highlights:</h4>
                                </div>
                                <div class="more-details-box">
                                  <ul class="plans-details-list">
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Microsoft 365 Copilot in apps such as Word, Excel, PowerPoint, and Outlook5​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">AI-powered chat connected to your work data and access to reasoning AI for research and data analysis</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Identity and access management for up to 300 users</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Web and mobile versions3 of Word, Excel, PowerPoint, and Outlook</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Custom business email (you@yourbusiness.com)​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">1 TB of cloud storage per user​</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">10+ additional apps for your business needs (including Microsoft Bookings, Planner, and Forms)</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Automatic spam and malware filtering</li>
                                    <li><img src="{{ asset('public/home/images/icons/check-arrow2.png')}}" alt="Check Arrow">Anytime phone and web support​​</li>
                                  </ul>
                                  <div class="features-bottom">
                                    <a href="javascript:void(0)" class="more-details p-content">See All Features</a>
                                  </div>
                                </div>
                              </div> 
                              
                          </div>
                        </div>
                        <div class="">
                          <ul class="plans-apps-list">
                            <li class="p-content mb-0 weight-SemiBold w-100">Popular apps and services included:</li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_Word.png')}}" alt="Microsoft Office Word"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Excel.svg')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_PowerPoint.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_OneNote.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Clipchamp.svg')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Outlook.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Exchange.png')}}" alt=""></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/OneDrive.png')}}" alt="OneDrive"></li>
                            <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/Microsoft_Office_SharePoint.png')}}" alt="Microsoft Office SharePoint"></li>
                           <li><img src="{{ asset('public/home/images/microsoft/microsoft-365/copilot.svg')}}" alt="Copilot"></li>
                          </ul>
                          <div class="plans-details-text plans-details-text-bottom">
                            <button class="buy-now">Buy Now</button>
                          </div> 
                        </div>  
                      </div>
                    </div> --}}
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

<section class="microsoft-why-choose position-relative pt-100 pb-100">
  <div class="microsoft-banner">
    <img src="{{ asset('public/home/images/microsoft/why-choose-img.jpg')}}" class="img" alt="">
  </div>
  <div class="container h-100">
    <div class="row h-100">
      <div class="col-lg-10 col-md-12 col-12 d-flex align-items-center">
        <div class="why-choose-text">
          <img src="{{ asset('public/home/images/microsoft/microsoft-tizzy-logo.png')}}" class="img" alt="" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
          <h2 class="title mb-0" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">Why Choose Microsoft 365 with Tizzy Cloud?</h2>
          <ul class="workspace-list mt-40" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"> <strong>Easy Setup & Migration -</strong> Ensuring a transition to Microsoft 365 with zero data loss or downtime</li>
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"> <strong>Expert Support -</strong> Support from the beginning for troubleshooting, user, and Microsoft 365 optimisation</li>
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"> <strong>Customised Plans -</strong> From start-ups to enterprises, get flexible solutions tailored for every company size</li>
            <li><img src="{{ asset('public/home/images/icons/check-arrow.png')}}" alt="check-arrow"> <strong>Robust Security -</strong> Keep your data safe with built-in security features and compliance controls from Microsoft</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="microsoft-why-choose position-relative pt-100 pb-100">
  <div class="microsoft-banner">
    <img src="{{ asset('public/home/images/microsoft/get-started.jpg')}}" class="img" alt="Get Started with Microsoft 365">
  </div>
  <div class="container">
    <div class="row justify-content-end">
      <div class="col-lg-6 col-md-12 col-12 d-flex align-items-center">
        <div class="why-choose-text position-relative">
          <!-- <img src="images/microsoft/microsoft-tizzy-logo.png" class="img" alt="Tizzy Cloud Microsoft 365 Logo" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800"> -->
          <h2 class="title" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">Get Started with Tizzy Cloud <strong>Today</strong></h2>
          <p data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">Elevate your business with Tizzy Microsoft 365 cloud support. We take care of the entire process, from setup to continuous support, allowing you to concentrate on growing your company. Get in touch today with a Tizzy Cloud team member to identify the perfect Microsoft 365 plan for your needs.</p>
           <a href="#pricing" class="tp-btn-black mt-30" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
            <span class="tp-btn-black-filter d-inline-flex align-items-center">
              <span class="tp-btn-black-text">View Pricing</span>
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
<section class="testimonial-section pt-100 pb-100">
  <div class="row m-0">
      <div class="col-lg-12 p-0">
        <div class="testimonial-left">
          <img src="{{ asset('public/home/images/icons/quote.png')}}" class="quote" alt="Quote Icon" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600"> 
          <h2 class="div-title color-333233" data-aos="fade-left" data-aos-easing="linear" data-aos-duration="600">What Our Customers Are Saying</h2> 
          <div class="mt-50" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
            <div class="swiper-pagination-testimonial"></div>
            <div class="swiper-button-next" role="button" aria-label="Next testimonial slide" tabindex="0"><img src="{{ asset('public/home/images/icons/testi-arrow.png')}}" alt=""></div>
            <div class="swiper-button-prev" role="button" aria-label="Previous testimonial slide" tabindex="0"><img src="{{ asset('public/home/images/icons/testi-arrow2.png')}}" alt=""></div>
          </div> 
        </div>
        <div class="testimonial-right" data-aos="fade-right" data-aos-easing="linear" data-aos-duration="600">
          <div class="testimonial_slider swiper-container">
            <div class="swiper-wrapper">
              <div class="swiper-slide">
                <div class="testimonial-box">
                  <div class="customer-details position-relative">
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we  were about to migrate all 200 email users to either Google or Microsoft. After  trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
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
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>IP blacklisting issue, UCE Protect level 2 and level 3 listing issues, spamming issues, other DNS related issues all were solved after moving all 350 emails to Tizzy Mail. We always wanted Indian Data Center and with Tizzy Mail all our Email Data is now stored only in India.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Incoming spam emails are reduced to 60-70%, almost ZERO complaints from our customers, quick response to our emails. All IT companies should offer only Tizzy Mail service to their clients.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we were about to migrate all 200 email users to either Google or Microsoft. After trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
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
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>IP blacklisting issue, UCE Protect level 2 and level 3 listing issues, spamming issues, other DNS related issues all were solved after moving all 350 emails to Tizzy Mail. We always wanted Indian Data Center and with Tizzy Mail all our Email Data is now stored only in India.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Incoming spam emails are reduced to 60-70%, almost ZERO complaints from our customers, quick response to our emails. All IT companies should offer only Tizzy Mail service to their clients.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                    </ul>
                  </div>
                  <div class="customer-name d-flex align-items-center">
                    <img src="{{ asset('public/home/images/user.png')}}" alt="User">
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
                    <img src="{{ asset('public/home/images/icons/quote-black.png')}}" class="quote-black" alt="Quote Black Icon">
                    <p>Our existing vendor was not able to solve daily issues related to emails so we were about to migrate all 200 email users to either Google or Microsoft. After trying Tizzy Mail our team was very happy as almost all the issues were solved immediately after activation.</p>
                    <ul class="rating">
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
                      <li><img src="{{ asset('public/home/images/icons/star.png')}}" alt="star"></li>
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
              


            </div>
          </div>
          
        </div>
      </div>
    </div>
</section>
<section class="faq-section position-relative pt-100 pb-100">
  <!-- <div class="mascot mascot01">
    <img src="images/Mascot-02.png" alt="">
  </div> -->
  <div class="container">
    <div class="row mb-80 justify-content-center">
      <div class="col-xl-8 col-lg-8 col-md-8 col-12">
        <div class="faq-title text-center">
          <h2 class="title mb-2" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">Frequently Asked Questions</h2>
          
        </div>
      </div>
    </div>
    <div class="row justify-content-center">
      <div class="col-xxl-8 col-lg-10 col-md-12" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="600">
        <div class="accordion faq-accordion" id="accordionExample">
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="800">
            <h2 class="accordion-header" id="headingOne">
              <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                What is Microsoft 365 ?
              </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">Microsoft 365 is the productivity cloud designed to help each of us achieve what matters, in our work and life, with best-in-class Office apps, intelligent cloud services, and advanced security.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1000">
            <h2 class="accordion-header" id="headingTwo">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
               What happened to Office 365 business plans?
              </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
               <p>Office 365 business plans are now Microsoft 365 business plans. New name, same great value, same price.</p>
                <ul>
                    <li>Office 365 Business Essentials is now Microsoft 365 Business Basic</li>
                    <li>Office 365 Business Premium is now Microsoft 365 Business Standard</li>
                    <li>Microsoft 365 Business is now Microsoft 365 Business Premium</li>
                    <li>Office 365 Business is now Microsoft 365 Apps for business</li>
                </ul> 
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1200">
            <h2 class="accordion-header" id="headingThree">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
               Why did you change the name from Office 365 to Microsoft 365 ?
              </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">We changed the name to be more reflective of the range of features and benefits in the subscription. Microsoft 365 includes everything you know in Office 365, including best-in-class Office apps, intelligent cloud services, and advanced security.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1400">
            <h2 class="accordion-header" id="headingThree2">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree2" aria-expanded="false" aria-controls="collapseThree2">Where business plan names have changed, has the product or price changed ?</button>
            </h2>
            <div id="collapseThree2" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">There are no price or feature changes for small and medium business plans. The same plans are available, only with an updated name.</p>
              </div>
            </div>
          </div>
          <div class="accordion-item" data-aos="fade-up" data-aos-easing="linear" data-aos-duration="1600">
            <h2 class="accordion-header" id="headingThree3">
              <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseThree3" aria-expanded="false" aria-controls="collapseThree3">
               My business has an existing Office 365 business subscription. What does the change to Microsoft 365 mean for me ?
              </button>
            </h2>
            <div id="collapseThree3" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
              <div class="accordion-body">
                <p class="mb-0">No action is needed from you. Your Office 365 subscription will automatically be renamed to Microsoft 365. You will see the name of your subscription update to the new name across your Admin Portal and monthly billing statements.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
