@extends('home.render')

@section('metaTitle')
    <title>Reseller KYC Registration - Tizzy Cloud</title>
@endsection

@section('myCSS')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('Body')
    <div class="position-relative dashboard-layout">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        @include('home.sidebar')

        <div class="bg-light min-vh-100 dashboard-main-wrapper">
            <div class="form-wizard-page">
                <div class="container-fluid px-4">

                    <div class="col-12 mb-3">
                        <h2 class="address-section-title">Reseller KYC Registration</h2>
                    </div>

                    <div class="form-wizard-card">

                        {{-- ── Wizard Sidebar ── --}}
                        <aside class="wizard-sidebar">
                            <div class="wizard-step active" data-step="1">Basic Details</div>
                            <div class="wizard-step" data-step="2">Business Details</div>
                            <div class="wizard-step" data-step="3">Address Details</div>
                            <div class="wizard-step" data-step="4">Bank Details</div>
                        </aside>

                        {{-- ── Wizard Body ── --}}
                        <div class="wizard-body">

                            {{-- ══ STEP 1: Basic Details ══ --}}
                            <div class="wizard-section" id="step-1">
                                <div class="row g-3 kyc-single-col">
                                    <div class="col-12">
                                        <label class="form-label-custom">Company Category <span class="required">*</span></label>
                                        <select class="form-input-custom">
                                            <option value="" disabled selected>Select Category</option>
                                            {{-- foreach --}}
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="wizard-actions wizard-actions-center">
                                    <button type="button" class="btn-next" onclick="goToStep(2)">Next</button>
                                </div>
                            </div>

                            {{-- ══ STEP 2: Business Details ══ --}}
                            <div class="wizard-section d-none" id="step-2">
                                <div class="row g-4">

                                    {{-- Left Column --}}
                                    <div class="col-lg-6">
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <label class="form-label-custom">Udyog Adhaar</label>
                                                <div class="radio-group">
                                                    <label class="radio-option">
                                                        <input type="radio" name="udyog_adhaar" value="yes"> Yes
                                                    </label>
                                                    <label class="radio-option">
                                                        <input type="radio" name="udyog_adhaar" value="no" checked> No
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">GST Number <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="GST Number">
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Upload GST Certificate <span class="required">*</span></label>
                                                <input type="file" class="form-input-custom form-input-file">
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Tan Number <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Tan Number">
                                            </div>

                                        </div>
                                    </div>

                                    {{-- Right Column --}}
                                    <div class="col-lg-6 address-col-divider">
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <label class="form-label-custom">Pan Card Number <span class="required">*</span></label>
                                                <select class="form-input-custom">
                                                    <option value="" disabled selected>Select Category</option>
                                                    <option>Individual</option>
                                                    <option>Company</option>
                                                    <option>HUF</option>
                                                    <option>Firm</option>
                                                    <option>Trust</option>
                                                </select>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Upload Pan Card <span class="required">*</span></label>
                                                <input type="file" class="form-input-custom form-input-file">
                                            </div>

                                        </div>
                                    </div>

                                </div>
                                <div class="wizard-actions">
                                    <button type="button" class="btn-back" onclick="goToStep(1)">Back</button>
                                    <button type="button" class="btn-next" onclick="goToStep(3)">Next</button>
                                </div>
                            </div>

                            {{-- ══ STEP 3: Address Details ══ --}}
                            <div class="wizard-section d-none" id="step-3">
                                <div class="row g-4">

                                    {{-- Left: Registered Address --}}
                                    <div class="col-lg-6">
                                        <h2 class="address-section-title">Registered Address</h2>
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <label class="form-label-custom">Address <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Enter address">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">City <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="City">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">Pincode <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Pincode">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">State <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="State">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">Contact Number <span class="required">*</span></label>
                                                <input type="tel" class="form-input-custom" placeholder="Contact Number">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-custom">Region <span class="required">*</span></label>
                                                <select class="form-input-custom">
                                                    <option value="" disabled selected>Select Region</option>
                                                    <option>North</option>
                                                    <option>South</option>
                                                    <option>East</option>
                                                    <option>West</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-custom">Company Type <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Company Type">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-custom">Is Office/Delivery Address same as Registered Address? <span class="required">*</span></label>
                                                <div class="radio-group">
                                                    <label class="radio-option"><input type="radio" name="reg_same" value="yes"> Yes</label>
                                                    <label class="radio-option"><input type="radio" name="reg_same" value="no" checked> No</label>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    {{-- Right: Office/Delivery Address --}}
                                    <div class="col-lg-6 address-col-divider">
                                        <h2 class="address-section-title">Office / Delivery Address</h2>
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <label class="form-label-custom">Address <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Enter address">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">City <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="City">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">Pincode <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Pincode">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">State <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="State">
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label-custom">Contact Number <span class="required">*</span></label>
                                                <input type="tel" class="form-input-custom" placeholder="Contact Number">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-custom">Region <span class="required">*</span></label>
                                                <select class="form-input-custom">
                                                    <option value="" disabled selected>Select Region</option>
                                                    <option>North</option>
                                                    <option>South</option>
                                                    <option>East</option>
                                                    <option>West</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-custom">Company Type <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Company Type">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-custom">Is Office/Delivery Address same as Registered Address? <span class="required">*</span></label>
                                                <div class="radio-group">
                                                    <label class="radio-option"><input type="radio" name="off_same" value="yes"> Yes</label>
                                                    <label class="radio-option"><input type="radio" name="off_same" value="no" checked> No</label>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                </div>
                                <div class="wizard-actions">
                                    <button type="button" class="btn-back" onclick="goToStep(2)">Back</button>
                                    <button type="button" class="btn-next" onclick="goToStep(4)">Next</button>
                                </div>
                            </div>

                            {{-- ══ STEP 4: Bank Details ══ --}}
                            <div class="wizard-section d-none" id="step-4">
                                <div class="row g-4">

                                    {{-- Left Column --}}
                                    <div class="col-lg-6">
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <label class="form-label-custom">IFSC Code <span class="required">*</span></label>
                                                <div class="ifsc-input-group">
                                                    <input type="text" class="form-input-custom" placeholder="IFSC Code">
                                                    <button type="button" class="btn-verify">Verify</button>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Branch Name <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Branch Name">
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Account Holder Name <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Holder Name">
                                            </div>

                                        </div>
                                    </div>

                                    {{-- Right Column --}}
                                    <div class="col-lg-6 address-col-divider">
                                        <div class="row g-3">

                                            <div class="col-12">
                                                <label class="form-label-custom">Bank Name <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Bank Name">
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Account Number <span class="required">*</span></label>
                                                <input type="text" class="form-input-custom" placeholder="Account Number">
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label-custom">Upload Canceled Cheque <span class="required">*</span></label>
                                                <input type="file" class="form-input-custom form-input-file">
                                            </div>

                                        </div>
                                    </div>

                                </div>
                                <div class="wizard-actions">
                                    <button type="button" class="btn-back" onclick="goToStep(3)">Back</button>
                                    <button type="submit" class="btn-next">Submit</button>
                                </div>
                            </div>

                        </div>{{-- end wizard-body --}}
                    </div>{{-- end form-wizard-card --}}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('myJS')
    <script>
        function goToStep(step) {
            $('.wizard-section').addClass('d-none');
            $('#step-' + step).removeClass('d-none');
            $('.wizard-step').removeClass('active');
            $('.wizard-step[data-step="' + step + '"]').addClass('active');
        }

        $(document).ready(function() {
            $('.wizard-step').on('click', function() {
                goToStep($(this).data('step'));
            });

            // ── Sidebar toggle: dual mode ──
            function isDesktop() { return $(window).width() >= 992; }

            $('#sidebarArrowToggle').on('click', function () {
                if (isDesktop()) {
                    $('body').toggleClass('sidebar-collapsed');
                    $('#sidebar').toggleClass('collapsed');
                } else {
                    $('body').toggleClass('sidebar-open');
                    $('#sidebar').toggleClass('show');
                    $('#sidebarOverlay').toggleClass('show');
                }
            });

            $('#sidebarOverlay').on('click', function () {
                $('body').removeClass('sidebar-open');
                $('#sidebar').removeClass('show');
                $(this).removeClass('show');
            });

            $(window).on('resize', function () {
                if (isDesktop()) {
                    $('body').removeClass('sidebar-open');
                    $('#sidebar').removeClass('show');
                    $('#sidebarOverlay').removeClass('show');
                } else {
                    $('body').removeClass('sidebar-collapsed');
                    $('#sidebar').removeClass('collapsed');
                }
            });
        });
    </script>
@endsection
