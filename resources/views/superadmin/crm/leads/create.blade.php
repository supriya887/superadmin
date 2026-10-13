@extends('superadmin.layouts.crm')

@section('content')
@php
    $dialCodes = \App\Models\Lead::orderedDialCodes();
    $oldCode = old('mobile_code', '91');
@endphp
<div class="crm-leads-page crm-leads-add">

<div class="page-header">
    <div>
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a> <span>/</span>
                <a href="{{ route('crm.leads') }}">Leads</a>
            </p>
        </div>
        <h2>Add New Lead</h2>
    </div>
    <div class="back">
        <a href="{{ route('crm.leads') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('crm.leads.store') }}" enctype="multipart/form-data" id="leadForm" novalidate>
    @csrf
    <section class="common-form">
        <div class="main-form-layout">
            <div class="form-left">
                <h3>Customer Detail</h3>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Name <span>*</span></label>
                        <div class="phone-group name-title-row">
                            <select name="salutation" class="form-control phone-code-select title-select" required>
                                <option value="">Title</option>
                                @foreach(['Mr', 'Mrs', 'Ms'] as $title)
                                    <option value="{{ $title }}" {{ old('salutation') === $title ? 'selected' : '' }}>{{ $title }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="name" value="{{ old('name') }}" required placeholder="Full name">
                        </div>
                        @error('salutation')<div class="form-error">{{ $message }}</div>@enderror
                        @error('name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        @if (!empty($designations))
                            <select name="designation">
                                <option value="">Select Designation</option>
                                @foreach ($designations as $desig)
                                    <option value="{{ $desig }}" {{ old('designation') == $desig ? 'selected' : '' }}>{{ $desig }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="designation" value="{{ old('designation') }}">
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Email <span>*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                        @error('email')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Mobile No. <span>*</span></label>
                        <div class="phone-group">
                            <div class="loc-select phone-country-picker" data-loc="phone-code">
                                <button type="button" class="loc-select-btn" aria-label="Select country calling code">
                                    +{{ $oldCode }}
                                </button>
                                <div class="loc-select-panel">
                                    <input type="text" class="loc-select-search" placeholder="Search country or code">
                                    <div class="loc-select-list">
                                        @foreach($dialCodes as $code => $country)
                                            <button type="button" data-code="{{ $code }}" class="{{ (string) $oldCode === (string) $code ? 'active' : '' }}">+{{ $code }} {{ $country }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <input type="hidden" name="mobile_code" value="{{ $oldCode }}">
                            </div>
                            <input type="tel" name="mobile" value="{{ old('mobile') }}" required inputmode="numeric" maxlength="15" pattern="[0-9]{6,15}" title="Enter mobile number" class="js-mobile-digits">
                        </div>
                        @error('mobile')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="{{ old('company_name') }}">
                        @error('company_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Industry</label>
                        <input type="text" name="industry" value="{{ old('industry') }}" placeholder="Filled from GSTIN">
                    </div>
                    <div class="form-group">
                        <label>No. of Employees</label>
                        <input type="number" name="no_of_employees" min="0" value="{{ old('no_of_employees') }}">
                    </div>
                    <div class="form-group">
                        <label>GSTIN</label>
                        <input type="text" name="gstin" value="{{ old('gstin') }}" maxlength="15" autocomplete="off">
                        <div id="gstStatus" class="gst-status"></div>
                        @error('gstin')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h3 class="section-title">Address Information</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Mailing Address</label>
                        <input type="text" name="mailing_address" value="{{ old('mailing_address') }}">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <div class="loc-select" data-loc="country">
                            <button type="button" class="loc-select-btn is-placeholder" id="countryBtn">Select Country</button>
                            <div class="loc-select-panel">
                                <input type="text" class="loc-select-search" placeholder="Search country">
                                <div class="loc-select-list" id="countryList"></div>
                            </div>
                            <input type="hidden" name="country" id="leadCountry" value="{{ old('country') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>State</label>
                        <div class="loc-select" data-loc="state">
                            <button type="button" class="loc-select-btn is-placeholder" id="stateBtn">Select State</button>
                            <div class="loc-select-panel">
                                <input type="text" class="loc-select-search" placeholder="Search state">
                                <div class="loc-select-list" id="stateList"></div>
                            </div>
                            <input type="hidden" name="state" id="leadState" value="{{ old('state') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <div class="loc-select" data-loc="city">
                            <button type="button" class="loc-select-btn is-placeholder" id="cityBtn">Select City</button>
                            <div class="loc-select-panel">
                                <input type="text" class="loc-select-search" placeholder="Search city">
                                <div class="loc-select-list" id="cityList"></div>
                            </div>
                            <input type="hidden" name="city" id="leadCity" value="{{ old('city') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" value="{{ old('pincode') }}">
                    </div>
                </div>

                <div class="form-group business-choice-group">
                    <label>Choose Business</label>
                    <div class="business-choice">
                        <label>
                            <input type="radio" name="business_type" value="new" {{ old('business_type', 'new') !== 'existing' ? 'checked' : '' }}>
                            New Business
                        </label>
                        <label>
                            <input type="radio" name="business_type" value="existing" {{ old('business_type') === 'existing' ? 'checked' : '' }}>
                            Existing Business
                        </label>
                    </div>
                </div>

                <div id="existingVendorWrap">
                    <div class="section-heading">
                        <h3>Current Service (Info)</h3>
                        <button type="button" class="add-plan-btn" id="addVendorServiceBtn">+ Add Service</button>
                    </div>
                    <p class="form-hint">For information only — what they use with another vendor. Add Tizzy plans below to quote.</p>
                    <div id="vendorServicesWrapper" class="stack-list">
                        <div class="plan-box vendor-service-box">
                            <div class="plan-header">
                                <strong>Service 1</strong>
                                <button type="button" class="remove-plan remove-vendor-service" aria-label="Remove Service">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>
                            <div class="form-grid form-grid-3 vendor-service-grid">
                                <div class="form-group">
                                    <label>Domain Name</label>
                                    <input type="text" name="vendor_services[0][domain]" placeholder="Domain Name">
                                </div>
                                <div class="form-group">
                                    <label>Tizzy Service</label>
                                    <select name="vendor_services[0][provider_id]" class="form-control provider-select">
                                        <option value="">Select Service</option>
                                        @foreach($providers as $provider)
                                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Plan Name</label>
                                    <select name="vendor_services[0][plan_id]" class="form-control plan-select plan-name-select">
                                        <option value="">Plan Name</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>No of Users</label>
                                    <input type="number" name="vendor_services[0][quantity]" min="1" placeholder="Quantitys">
                                </div>
                                <div class="form-group">
                                    <label>Current Price</label>
                                    <input type="number" name="vendor_services[0][rate]" min="0" step="0.01" placeholder="Price">
                                </div>
                                <div class="form-group">
                                    <label>Renewal Date</label>
                                    <div class="date-input">
                                        <input type="date" name="vendor_services[0][renewal_date]">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="newBusinessOffer">
                <h3 class="section-title">Domain</h3>
                <div class="form-grid domain-fields">
                    <div class="form-group">
                        <label>Domain option</label>
                        <select name="domain_type" id="domainType">
                            <option value="">No domain</option>
                            <option value="existing" {{ old('domain_type') === 'existing' ? 'selected' : '' }}>Already have domain</option>
                            <option value="new" {{ old('domain_type') === 'new' ? 'selected' : '' }}>Need new domain</option>
                        </select>
                    </div>
                    <div class="form-group domain-name-wrap">
                        <label>Domain Name</label>
                        <input type="text" name="domain" id="domainName" value="{{ old('domain') }}" placeholder="example.com">
                        @error('domain')<div class="form-error">{{ $message }}</div>@enderror
                        <div id="domainHaveHint" class="form-hint"></div>
                    </div>
                    <div class="form-group domain-have-wrap">
                        <label>Renewal Date</label>
                        <div class="date-input">
                            <input type="date" name="domain_renewal_date" value="{{ old('domain_renewal_date') }}">
                        </div>
                    </div>
                    <div class="form-group domain-new-wrap">
                        <label>&nbsp;</label>
                        <button type="button" class="add-plan-btn domain-check-btn" id="checkDomainBtn">Check Availability</button>
                        <div id="domainCheckStatus" class="form-hint"></div>
                    </div>
                    <div class="form-group domain-new-wrap">
                        <label>Period</label>
                        <select name="domain_period" id="domainPeriod">
                            <option value="">Select Period</option>
                            <option value="Monthly" {{ old('domain_period') === 'Monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="Quarterly" {{ old('domain_period') === 'Quarterly' ? 'selected' : '' }}>Quarterly</option>
                            <option value="Half Yearly" {{ old('domain_period') === 'Half Yearly' ? 'selected' : '' }}>Half Yearly</option>
                            <option value="Yearly" {{ old('domain_period', 'Yearly') === 'Yearly' ? 'selected' : '' }}>Yearly</option>
                        </select>
                    </div>
                    <div class="form-group domain-new-wrap">
                        <label>Rate</label>
                        <input type="number" name="domain_rate" id="domainRate" min="0" step="0.01" value="{{ old('domain_rate') }}">
                    </div>
                    <div class="form-group domain-new-wrap">
                        <label>SAC</label>
                        <input type="text" name="sac" value="{{ old('sac') }}">
                    </div>
                    <div class="form-group domain-new-wrap">
                        <label>Domain Start</label>
                        <div class="date-input">
                            <input type="date" name="domain_start" value="{{ old('domain_start') }}">
                        </div>
                    </div>
                    <div class="form-group domain-new-wrap">
                        <label>Domain End</label>
                        <div class="date-input">
                            <input type="date" name="domain_end" value="{{ old('domain_end') }}">
                        </div>
                    </div>
                </div>
                </div>

                <div class="section-heading">
                    <h3>Our Service / Plan Detail</h3>
                    <button type="button" class="add-plan-btn" id="addPlanBtn">+ Add Plan</button>
                </div>
                <p class="form-hint">Add Tizzy plans here. These are the services used on quotations. For Existing Business, Current Service above is information only.</p>
                <div id="plansWrapper" class="stack-list">
                    <div class="plan-box">
                        <div class="plan-header">
                            <strong class="plan-number">Sr. No. 1</strong>
                            <button type="button" class="remove-plan" aria-label="Remove Plan">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        <div class="form-grid form-grid-3">
                            <div class="form-group">
                                <label>Provider</label>
                                <select name="plans[0][provider_id]" class="form-control provider-select">
                                    <option value="">Select Provider</option>
                                    @foreach($providers as $provider)
                                        <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Plan</label>
                                <select name="plans[0][plan_id]" class="form-control plan-select">
                                    <option value="">Select Service</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quote Period</label>
                                <select name="plans[0][quote_period]" class="form-control quote-period-select">
                                    <option value="">Select Period</option>
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Half Yearly">Half Yearly</option>
                                    <option value="Yearly" selected>Yearly</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>SKU</label>
                                <input type="text" name="plans[0][sku]" class="sku-input" readonly>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="plans[0][quantity]" min="1" value="1">
                            </div>
                            <div class="form-group">
                                <label>Rate</label>
                                <input type="text" name="plans[0][rate]" class="rate-input">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-heading additional-contact-heading">
                    <h3>Additional Contact</h3>
                    <button type="button" class="add-plan-btn" id="addContactBtn">+ Add Contact</button>
                </div>
                <div id="contactsWrapper" class="stack-list">
                    <div class="contact-person">
                        <div class="plan-header">
                            <strong class="contact-person-title">Contact Person</strong>
                            <button type="button" class="remove-plan remove-contact" aria-label="Remove Contact">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        <div class="form-grid additional-contact-grid">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="contacts[0][name]">
                            </div>
                            <div class="form-group">
                                <label>Designation</label>
                                @if (!empty($designations))
                                    <select name="contacts[0][designation]">
                                        <option value="">Select Designation</option>
                                        @foreach ($designations as $desig)
                                            <option value="{{ $desig }}">{{ $desig }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="contacts[0][designation]">
                                @endif
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="contacts[0][email]">
                            </div>
                            <div class="form-group">
                                <label>Mobile No.</label>
                                <input type="tel" name="contacts[0][mobile]" inputmode="numeric" maxlength="15" pattern="[0-9]{6,15}" class="js-mobile-digits">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-right">
                <div class="side-card followup-card">
                    <h3>Follow Up Detail</h3>
                    <div class="form-group">
                        <label>Follow Up Date & Time</label>
                        <div class="date-input">
                            <input type="datetime-local" name="follow_up_date" value="{{ old('follow_up_date') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Add Note</label>
                        <textarea name="follow_up_note" rows="4">{{ old('follow_up_note') }}</textarea>
                    </div>
                </div>

                <div class="lead-specification">
                    <h3>Lead Specification</h3>
                    <div class="form-group">
                        <label>Lead Priority</label>
                        <div class="select-wrap">
                            <select name="lead_category">
                                <option value="">Select Priority</option>
                                @foreach (($leadPriorities ?? $leadCategories ?? []) as $cat)
                                    <option value="{{ $cat }}" {{ old('lead_category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Lead Status <span>*</span></label>
                        <div class="select-wrap">
                            <select name="lead_status" required>
                                <option value="">Select Status</option>
                                @foreach (($leadStatuses ?? ['New', 'Contacted', 'Qualified', 'Negotiation', 'Quote Send', 'Won', 'Lost', 'Nurturing']) as $st)
                                    <option value="{{ $st }}" {{ old('lead_status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('lead_status')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label>Lead Bifurcation</label>
                        <div class="select-wrap">
                            <select name="lead_bifurcation">
                                <option value="">Select Bifurcation</option>
                                @foreach (($leadBifurcations ?? []) as $bif)
                                    <option value="{{ $bif }}" {{ old('lead_bifurcation') == $bif ? 'selected' : '' }}>{{ $bif }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Lead Source</label>
                        <div class="select-wrap">
                            <select name="lead_source">
                                <option value="">Select Source</option>
                                @foreach (($leadSources ?? []) as $src)
                                    <option value="{{ $src }}" {{ old('lead_source') == $src ? 'selected' : '' }}>{{ $src }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Assigned To</label>
                        <div class="select-wrap">
                            <select name="assigned_to">
                                <option value="">Select Employee</option>
                                @foreach(($assignees ?? collect()) as $assignee)
                                    <option value="{{ $assignee }}" {{ old('assigned_to') == $assignee ? 'selected' : '' }}>{{ $assignee }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="document-section lead-doc-card">
                    <div class="lead-doc-head">
                        <div class="lead-doc-icon">
                            <i data-lucide="folder-up"></i>
                        </div>
                        <div>
                            <h3>Add Document</h3>
                            <p class="form-hint doc-help">PDF, JPG, PNG, DOC or DOCX. Max 10 MB each. Pick a type, then drop or click to upload.</p>
                        </div>
                    </div>
                    <div class="lead-doc-type form-group">
                        <label>Document Type</label>
                        <select id="documentTypeSelect">
                            <option value="PAN Card">PAN Card</option>
                            <option value="GST Certificate">GST Certificate</option>
                            <option value="Address Proof">Address Proof</option>
                            <option value="Company Registration">Company Registration</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <label class="upload-box" id="leadUploadBox">
                        <div class="upload-icon">
                            <i data-lucide="cloud-upload"></i>
                        </div>
                        <p>Click to upload or drag files here</p>
                        <span class="upload-formats">PDF · JPG · PNG · DOC · DOCX</span>
                        <input type="file" name="documents[]" class="document-input" id="documentInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    </label>
                    <div id="documentTypeFields"></div>
                    <div class="upload-status" id="uploadingStatus" hidden>
                        <div class="upload-title">
                            <span id="uploadingTitle">Uploading 1/1 Files</span>
                        </div>
                        <div class="file-item uploading-file">
                            <span id="uploadingFileName"></span>
                            <button type="button" aria-label="Cancel Upload" id="cancelUploadBtn">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        <div class="progress-bar">
                            <span></span>
                        </div>
                    </div>
                    <div class="upload-status uploaded-status doc-file-list" id="fileList" hidden>
                        <div class="upload-title">
                            <span>Selected files</span>
                        </div>
                    </div>
                    @error('documents')<div class="form-error">{{ $message }}</div>@enderror
                    @error('documents.*')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="submit-wrap">
            <button type="submit" class="submit-btn" id="saveLeadBtn">Save Lead</button>
        </div>
    </section>
</form>

<div class="credit-modal" id="previewModal">
    <div class="credit-modal-box">
        <button class="credit-close" type="button" id="previewClose">&times;</button>
        <h2 id="previewTitle">Document Preview</h2>
        <div id="previewBody"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const locationData = {
        'India': {
            'Andhra Pradesh': ['Visakhapatnam', 'Vijayawada', 'Guntur'],
            'Delhi': ['New Delhi', 'Dwarka', 'Rohini'],
            'Goa': ['Panaji', 'Margao', 'Vasco'],
            'Gujarat': ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot'],
            'Haryana': ['Gurugram', 'Faridabad', 'Panipat'],
            'Karnataka': ['Bengaluru', 'Mysuru', 'Mangaluru'],
            'Kerala': ['Kochi', 'Thiruvananthapuram', 'Kozhikode'],
            'Madhya Pradesh': ['Bhopal', 'Indore', 'Jabalpur'],
            'Maharashtra': ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Thane'],
            'Punjab': ['Ludhiana', 'Amritsar', 'Chandigarh'],
            'Rajasthan': ['Jaipur', 'Udaipur', 'Jodhpur'],
            'Tamil Nadu': ['Chennai', 'Coimbatore', 'Madurai'],
            'Telangana': ['Hyderabad', 'Warangal', 'Nizamabad'],
            'Uttar Pradesh': ['Lucknow', 'Noida', 'Kanpur', 'Varanasi'],
            'West Bengal': ['Kolkata', 'Howrah', 'Durgapur']
        },
        'United Arab Emirates': {
            'Dubai': ['Dubai', 'Deira', 'Jebel Ali'],
            'Abu Dhabi': ['Abu Dhabi', 'Al Ain'],
            'Sharjah': ['Sharjah']
        },
        'United States': {
            'California': ['Los Angeles', 'San Francisco', 'San Diego'],
            'New York': ['New York', 'Buffalo'],
            'Texas': ['Houston', 'Dallas', 'Austin']
        },
        'United Kingdom': {
            'England': ['London', 'Manchester', 'Birmingham'],
            'Scotland': ['Edinburgh', 'Glasgow'],
            'Wales': ['Cardiff']
        }
    };

    function fillList(listId, values, selected) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.innerHTML = '';
        if (!values.length) {
            list.innerHTML = '<div class="empty">No options. Select the previous field first.</div>';
            return;
        }
        values.forEach(function (value) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = value;
            if (selected && selected === value) {
                btn.classList.add('active');
            }
            list.appendChild(btn);
        });
    }

    function setSelectLabel(btn, value, placeholder) {
        if (!btn) return;
        if (value) {
            btn.textContent = value;
            btn.classList.remove('is-placeholder');
        } else {
            btn.textContent = placeholder;
            btn.classList.add('is-placeholder');
        }
    }

    const countryInput = document.getElementById('leadCountry');
    const stateInputEl = document.getElementById('leadState');
    const cityInputEl = document.getElementById('leadCity');
    const countryBtn = document.getElementById('countryBtn');
    const stateBtn = document.getElementById('stateBtn');
    const cityBtn = document.getElementById('cityBtn');

    function currentStates() {
        const country = countryInput ? countryInput.value : '';
        return locationData[country] ? Object.keys(locationData[country]) : [];
    }

    function currentCities() {
        const country = countryInput ? countryInput.value : '';
        const state = stateInputEl ? stateInputEl.value : '';
        return (locationData[country] && locationData[country][state]) ? locationData[country][state] : [];
    }

    function refreshStates(keepCity) {
        const states = currentStates();
        if (stateInputEl && states.indexOf(stateInputEl.value) === -1) {
            stateInputEl.value = '';
        }
        fillList('stateList', states, stateInputEl ? stateInputEl.value : '');
        setSelectLabel(stateBtn, stateInputEl ? stateInputEl.value : '', 'Select State');
        if (!keepCity) {
            refreshCities();
        } else {
            refreshCities();
        }
    }

    function refreshCities() {
        const cities = currentCities();
        if (cityInputEl && cities.indexOf(cityInputEl.value) === -1) {
            cityInputEl.value = '';
        }
        fillList('cityList', cities, cityInputEl ? cityInputEl.value : '');
        setSelectLabel(cityBtn, cityInputEl ? cityInputEl.value : '', 'Select City');
    }

    fillList('countryList', Object.keys(locationData), countryInput ? countryInput.value : '');
    setSelectLabel(countryBtn, countryInput ? countryInput.value : '', 'Select Country');
    refreshStates(true);

    document.querySelectorAll('.loc-select').forEach(function (wrap) {
        const btn = wrap.querySelector('.loc-select-btn');
        const search = wrap.querySelector('.loc-select-search');
        const list = wrap.querySelector('.loc-select-list');
        const hidden = wrap.querySelector('input[type="hidden"]');
        const kind = wrap.getAttribute('data-loc');

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.loc-select.open').forEach(function (other) {
                if (other !== wrap) other.classList.remove('open');
            });
            wrap.classList.toggle('open');
            if (wrap.classList.contains('open') && search) {
                search.value = '';
                search.focus();
                Array.from(list.querySelectorAll('button')).forEach(function (item) {
                    item.style.display = '';
                });
            }
        });

        if (search) {
            search.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                let shown = 0;
                Array.from(list.querySelectorAll('button')).forEach(function (item) {
                    const match = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
                    item.style.display = match ? '' : 'none';
                    if (match) shown++;
                });
                let empty = list.querySelector('.empty');
                if (!shown) {
                    if (!empty) {
                        empty = document.createElement('div');
                        empty.className = 'empty';
                        list.appendChild(empty);
                    }
                    empty.textContent = 'No matches';
                    empty.style.display = '';
                } else if (empty && empty.textContent === 'No matches') {
                    empty.style.display = 'none';
                }
            });
        }

        list.addEventListener('click', function (e) {
            const item = e.target.closest('button');
            if (!item) return;
            hidden.value = kind === 'phone-code' ? item.dataset.code : item.textContent;
            const selectedLabel = kind === 'phone-code' ? '+' + item.dataset.code : item.textContent;
            setSelectLabel(btn, selectedLabel, btn.textContent);
            if (kind === 'phone-code') {
                list.querySelectorAll('button').forEach(function (option) {
                    option.classList.toggle('active', option === item);
                });
            }
            wrap.classList.remove('open');
            if (kind === 'country') {
                refreshStates();
            } else if (kind === 'state') {
                refreshCities();
            }
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.loc-select')) {
            document.querySelectorAll('.loc-select.open').forEach(function (wrap) {
                wrap.classList.remove('open');
            });
        }
    });

    function setSectionDisabled(root, disabled) {
        if (!root) return;
        root.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !!disabled;
        });
    }

    function toggleBusinessType() {
        const selected = document.querySelector('input[name="business_type"]:checked');
        const isExisting = selected && selected.value === 'existing';
        const vendorWrap = document.getElementById('existingVendorWrap');
        if (vendorWrap) {
            vendorWrap.style.display = isExisting ? 'block' : 'none';
            vendorWrap.classList.toggle('is-open', isExisting);
            setSectionDisabled(vendorWrap, !isExisting);
        }
    }

    document.querySelectorAll('input[name="business_type"]').forEach(function (radio) {
        radio.addEventListener('change', toggleBusinessType);
    });
    toggleBusinessType();

    const addPlanBtn = document.getElementById('addPlanBtn');
    const plansWrapper = document.getElementById('plansWrapper');

    const domainType = document.getElementById('domainType');
    const domainNameWrap = document.querySelector('.domain-name-wrap');
    const domainNewWraps = document.querySelectorAll('.domain-new-wrap');
    const domainHaveWraps = document.querySelectorAll('.domain-have-wrap');
    const checkDomainBtn = document.getElementById('checkDomainBtn');
    const domainCheckStatus = document.getElementById('domainCheckStatus');
    const domainHaveHint = document.getElementById('domainHaveHint');

    function toggleDomainFields() {
        const type = domainType ? domainType.value : '';
        if (domainNameWrap) {
            domainNameWrap.style.display = type ? 'block' : 'none';
        }
        domainNewWraps.forEach(function (el) {
            el.style.display = type === 'new' ? 'block' : 'none';
        });
        domainHaveWraps.forEach(function (el) {
            el.style.display = type === 'existing' ? 'block' : 'none';
        });
        if (type !== 'new' && domainCheckStatus) {
            domainCheckStatus.textContent = '';
        }
        if (type !== 'existing' && domainHaveHint) {
            domainHaveHint.textContent = '';
        }
    }

    if (domainType) {
        domainType.addEventListener('change', toggleDomainFields);
        toggleDomainFields();
    }

    if (checkDomainBtn) {
        checkDomainBtn.addEventListener('click', function () {
            if (!domainType || domainType.value !== 'new') {
                return;
            }
            const domain = ((document.getElementById('domainName') || {}).value || '').trim();
            if (!domainCheckStatus) {
                return;
            }
            domainCheckStatus.style.color = '#6c757d';
            domainCheckStatus.textContent = 'Checking...';
            fetch("{{ route('crm.leads.check-domain') }}?domain=" + encodeURIComponent(domain))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.invalid) {
                        domainCheckStatus.style.color = '#b91c1c';
                        domainCheckStatus.textContent = data.message || 'Enter a valid domain (example.com)';
                        return;
                    }
                    if (data.available) {
                        domainCheckStatus.style.color = '#15803d';
                        domainCheckStatus.textContent = data.message || 'This domain is available to register.';
                        return;
                    }
                    if (!data.registered) {
                        domainCheckStatus.style.color = '#b91c1c';
                        domainCheckStatus.textContent = data.message || 'Could not check domain.';
                        return;
                    }
                    domainType.value = 'existing';
                    toggleDomainFields();
                    if (domainHaveHint) {
                        domainHaveHint.style.color = '#b45309';
                        domainHaveHint.textContent = 'This domain is already registered, so Already have domain was selected. You can save the lead.';
                    }
                })
                .catch(function () {
                    domainCheckStatus.style.color = '#b91c1c';
                    domainCheckStatus.textContent = 'Could not check domain.';
                });
        });
    }

    const addContactBtn = document.getElementById('addContactBtn');
    const contactsWrapper = document.getElementById('contactsWrapper');

    const providerPlans = @json($providerPlans);

    let planCount = 1;
    let contactCount = 1;

    function refreshIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function loadPlans(providerId, planSelect, skuInput, rateInput) { 
        planSelect.innerHTML = '<option value="">Select Plan</option>'; 
        skuInput.value = ''; 
        rateInput.value = ''; 

        if (!providerId || !providerPlans[providerId]) { 
            return; 
        } 

        providerPlans[providerId].forEach(function (plan) { 
            const option = document.createElement('option'); 
            option.value = plan.id; 
            option.textContent = plan.name; 
            option.dataset.sku = plan.sku || plan.slug || ''; 
            option.dataset.actualPrice = plan.actual_price || plan.rate || 0; 
            planSelect.appendChild(option); 
        }); 

        if (planSelect.options.length > 1) {
            planSelect.selectedIndex = 1;
            const row = planSelect.closest('.plan-box');
            if (row) {
                const event = new Event('change', { bubbles: true });
                planSelect.dispatchEvent(event);
            }
        }
    }

    function updatePlanRate(row) {
        const planSelect = row.querySelector('.plan-select');
        const periodSelect = row.querySelector('.quote-period-select');
        const rateInput = row.querySelector('.rate-input');

        if (!planSelect || !periodSelect || !rateInput) {
            return;
        }

        const selectedOption = planSelect.options[planSelect.selectedIndex];

        if (!selectedOption || !selectedOption.value) {
            rateInput.value = '';
            return;
        }

        const actualPrice = parseFloat(selectedOption.dataset.actualPrice) || 0;

        if (actualPrice <= 0) {
            rateInput.value = '';
            return;
        }

        let calculatedRate = 0;

        switch (periodSelect.value) {
            case 'Monthly':
                calculatedRate = actualPrice / 12;
                break;
            case 'Quarterly':
                calculatedRate = actualPrice / 4;
                break;
            case 'Half Yearly':
                calculatedRate = actualPrice / 2;
                break;
            case 'Yearly':
                calculatedRate = actualPrice;
                break;
            default:
                calculatedRate = 0;
        }

        rateInput.value = calculatedRate > 0 ? calculatedRate.toFixed(2) : '';
    }

    function initializePlanRow(row) {
        const providerSelect = row.querySelector('.provider-select');
        const planSelect = row.querySelector('.plan-select');
        const skuInput = row.querySelector('.sku-input');
        const rateInput = row.querySelector('.rate-input');
        
        if (providerSelect && providerSelect.value) {
            loadPlans(providerSelect.value, planSelect, skuInput, rateInput);
        }
        
        if (planSelect && planSelect.value) {
            const selected = planSelect.options[planSelect.selectedIndex];
            if (selected && selected.dataset.sku) {
                skuInput.value = selected.dataset.sku;
            }
            updatePlanRate(row);
        }
    }

    plansWrapper.addEventListener('change', function (e) {
        const row = e.target.closest('.plan-box');
        if (!row) return;

        if (e.target.classList.contains('provider-select')) {
            const planSelect = row.querySelector('.plan-select');
            const skuInput = row.querySelector('.sku-input');
            const rateInput = row.querySelector('.rate-input');

            loadPlans(e.target.value, planSelect, skuInput, rateInput);
            var name = e.target.options[e.target.selectedIndex] ? e.target.options[e.target.selectedIndex].text : '';
            var same = false;
            plansWrapper.querySelectorAll('.provider-select').forEach(function (sel) {
                if (sel === e.target) return;
                if (sel.value && sel.value === e.target.value) same = true;
            });
            if (same && !/google/i.test(name)) {
                alert('Same provider can only be added once.');
                e.target.value = '';
                loadPlans('', planSelect, skuInput, rateInput);
                return;
            }
            return;
        }

        if (e.target.classList.contains('plan-select')) {
            const selected = e.target.options[e.target.selectedIndex];
            const skuInput = row.querySelector('.sku-input');
            
            if (skuInput) {
                skuInput.value = selected.dataset.sku || '';
            }
            
            updatePlanRate(row);
            var providerSel = row.querySelector('.provider-select');
            var planSel = row.querySelector('.plan-select');
            if (providerSel && planSel && providerSel.value && planSel.value) {
                var pair = providerSel.value + ':' + planSel.value;
                var dup = false;
                plansWrapper.querySelectorAll('.plan-box').forEach(function (box) {
                    if (box === row) return;
                    var a = box.querySelector('.provider-select');
                    var b = box.querySelector('.plan-select');
                    if (a && b && a.value && b.value && (a.value + ':' + b.value) === pair) dup = true;
                });
                if (dup) {
                    alert('This plan is already added.');
                    planSel.value = '';
                    if (skuInput) skuInput.value = '';
                    row.querySelector('.rate-input').value = '';
                }
            }
            return;
        }

        if (e.target.classList.contains('quote-period-select')) {
            updatePlanRate(row);
        }
    });

    addPlanBtn.addEventListener('click', function (e) {
        e.preventDefault();

        const index = planCount;
        planCount++;

        const plan = document.createElement('div');
        plan.className = 'plan-box';

        plan.innerHTML = `
            <div class="plan-header">
                <strong class="plan-number">Sr. No. ${planCount}</strong>
                <button type="button" class="remove-plan" aria-label="Remove Plan">
                    <i data-lucide="x"></i>
            </button>
            </div>
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Provider</label>
                    <select name="plans[${index}][provider_id]" class="form-control provider-select">
                        <option value="">Select Provider</option>
                        @foreach($providers as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Plan</label>
                    <select name="plans[${index}][plan_id]" class="form-control plan-select">
                        <option value="">Select Service</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quote Period</label>
                    <select name="plans[${index}][quote_period]" class="form-control quote-period-select">
                        <option value="">Select Period</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Half Yearly">Half Yearly</option>
                        <option value="Yearly" selected>Yearly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="plans[${index}][sku]" class="sku-input" readonly>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="plans[${index}][quantity]" min="1" value="1">
                </div>
                <div class="form-group">
                    <label>Rate</label>
                    <input type="text" name="plans[${index}][rate]" class="rate-input">
                </div>
            </div>
        `;

        plansWrapper.appendChild(plan);
        initializePlanRow(plan);
        updatePlanNumbers();
        updateRemoveButtons();
        refreshIcons();
    });

    plansWrapper.addEventListener('click', function (e) {
        if (!e.target.closest('.remove-plan')) {
            return;
        }

        const plan = e.target.closest('.plan-box');
        plan.remove();
        updatePlanNumbers();
        updateRemoveButtons();
    });

    function updatePlanNumbers() {
        const plans = plansWrapper.querySelectorAll('.plan-box');
        plans.forEach(function (plan, index) {
            plan.querySelector('.plan-number').textContent = 'Sr. No. ' + (index + 1);
        });
    }

    function updateRemoveButtons() {
        const plans = plansWrapper.querySelectorAll('.plan-box');
        plans.forEach(function (plan) {
            plan.querySelector('.remove-plan').style.display = plans.length > 1 ? 'flex' : 'none';
        });
    }

    const existingPlanRows = plansWrapper.querySelectorAll('.plan-box');
    existingPlanRows.forEach(function(row) {
        initializePlanRow(row);
    });
    updateRemoveButtons();

    const vendorWrapEl = document.getElementById('vendorServicesWrapper');
    const addVendorServiceBtn = document.getElementById('addVendorServiceBtn');
    let vendorCount = 1;

    function fillVendorPlans(row) {
        const providerSelect = row.querySelector('.provider-select');
        const planSelect = row.querySelector('.plan-select');
        if (!planSelect) {
            return;
        }
        planSelect.innerHTML = '<option value="">Plan Name</option>';
        const providerId = providerSelect ? providerSelect.value : '';
        if (!providerId || !providerPlans[providerId]) {
            return;
        }
        providerPlans[providerId].forEach(function (plan) {
            const option = document.createElement('option');
            option.value = plan.id;
            option.textContent = plan.name;
            planSelect.appendChild(option);
        });
    }

    function updateVendorNumbers() {
        if (!vendorWrapEl) {
            return;
        }
        const rows = vendorWrapEl.querySelectorAll('.vendor-service-box');
        rows.forEach(function (row, i) {
            const title = row.querySelector('.plan-header strong');
            if (title) {
                title.textContent = 'Service ' + (i + 1);
            }
            const remove = row.querySelector('.remove-vendor-service');
            if (remove) {
                remove.style.display = rows.length > 1 ? 'flex' : 'none';
            }
        });
    }

    if (vendorWrapEl) {
        vendorWrapEl.addEventListener('change', function (e) {
            const row = e.target.closest('.vendor-service-box');
            if (!row || !e.target.classList.contains('provider-select')) {
                return;
            }
            fillVendorPlans(row);
        });
        vendorWrapEl.addEventListener('click', function (e) {
            if (!e.target.closest('.remove-vendor-service')) {
                return;
            }
            const row = e.target.closest('.vendor-service-box');
            const rows = vendorWrapEl.querySelectorAll('.vendor-service-box');
            if (row && rows.length > 1) {
                row.remove();
                updateVendorNumbers();
            }
        });
        updateVendorNumbers();
    }

    if (addVendorServiceBtn && vendorWrapEl) {
        addVendorServiceBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const index = vendorCount;
            vendorCount++;
            const box = document.createElement('div');
            box.className = 'plan-box vendor-service-box';
            box.innerHTML = `
                <div class="plan-header">
                    <strong>Service ${vendorCount}</strong>
                    <button type="button" class="remove-plan remove-vendor-service" aria-label="Remove Service">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="form-grid form-grid-3 vendor-service-grid">
                    <div class="form-group">
                        <label>Domain Name</label>
                        <input type="text" name="vendor_services[${index}][domain]" placeholder="Domain Name">
                    </div>
                    <div class="form-group">
                        <label> Service</label>
                        <select name="vendor_services[${index}][provider_id]" class="form-control provider-select">
                            <option value="">Select Service</option>
                            @foreach($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Plan Name</label>
                        <select name="vendor_services[${index}][plan_id]" class="form-control plan-select plan-name-select">
                            <option value="">Plan Name</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>No of Users</label>
                        <input type="number" name="vendor_services[${index}][quantity]" min="1" placeholder="Quantitys">
                    </div>
                    <div class="form-group">
                        <label>Current Price</label>
                        <input type="number" name="vendor_services[${index}][rate]" min="0" step="0.01" placeholder="Price">
                    </div>
                    <div class="form-group">
                        <label>Renewal Date</label>
                        <div class="date-input">
                            <input type="date" name="vendor_services[${index}][renewal_date]">
                        </div>
                    </div>
                </div>
            `;
            vendorWrapEl.appendChild(box);
            updateVendorNumbers();
            refreshIcons();
        });
    }

    addContactBtn.addEventListener('click', function (e) {
        e.preventDefault();

        const index = contactCount;
        contactCount++;

        const contact = document.createElement('div');
        contact.className = 'contact-person';

        contact.innerHTML = `
            <div class="plan-header">
                <strong class="contact-person-title">Contact Person ${contactCount}</strong>
                <button
                    type="button"
                    class="remove-plan remove-contact"
                    aria-label="Remove Contact">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="form-grid additional-contact-grid">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="contacts[${index}][name]">
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    @if (!empty($designations))
                    <select name="contacts[${index}][designation]">
                        <option value="">Select Designation</option>
                        @foreach ($designations as $desig)
                        <option value="{{ $desig }}">{{ $desig }}</option>
                        @endforeach
                    </select>
                    @else
                    <input type="text" name="contacts[${index}][designation]">
                    @endif
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="contacts[${index}][email]">
                </div>
                <div class="form-group">
                    <label>Mobile No.</label>
                    <input type="tel" name="contacts[${index}][mobile]" inputmode="numeric" maxlength="15" pattern="[0-9]{6,15}" class="js-mobile-digits">
                </div>
            </div>
        `;

        contactsWrapper.appendChild(contact);
        updateContactNumbers();
        refreshIcons();
    });

    contactsWrapper.addEventListener('click', function (e) {
        if (!e.target.closest('.remove-contact')) {
            return;
        }

        const contact = e.target.closest('.contact-person');
        if (contact) {
        contact.remove();
        updateContactNumbers();
        }
    });

    function updateContactNumbers() {
        const contacts = contactsWrapper.querySelectorAll('.contact-person');

        contacts.forEach(function (contact, index) {
            const title = contact.querySelector('.contact-person-title');
            if (title) {
                title.textContent = 'Contact Person ' + (index + 1);
            }

            const removeBtn = contact.querySelector('.remove-contact');
            if (removeBtn) {
                removeBtn.style.display = contacts.length > 1 ? 'flex' : 'none';
            }
        });
    }

    updateContactNumbers();

    const documentInput = document.getElementById('documentInput');
    const fileList = document.getElementById('fileList');
    const uploadingStatus = document.getElementById('uploadingStatus');
    const uploadingTitle = document.getElementById('uploadingTitle');
    const uploadingFileName = document.getElementById('uploadingFileName');
    const cancelUploadBtn = document.getElementById('cancelUploadBtn');
    const previewModal = document.getElementById('previewModal');
    const previewBody = document.getElementById('previewBody');
    const previewTitle = document.getElementById('previewTitle');
    const previewClose = document.getElementById('previewClose');

    let selectedFiles = [];
    const documentTypeSelect = document.getElementById('documentTypeSelect');
    const documentTypeFields = document.getElementById('documentTypeFields');
    const maxDocBytes = 10 * 1024 * 1024;
    const allowedDocExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    if (documentInput) {
        documentInput.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    if (cancelUploadBtn) {
        cancelUploadBtn.addEventListener('click', function () {
            selectedFiles = [];
            updateFileInput();
            renderFileList();
        });
    }

    function addSelectedFiles(fileListLike) {
        const newFiles = Array.from(fileListLike || []);
        const docType = documentTypeSelect ? documentTypeSelect.value : 'Other';

        newFiles.forEach(function (file) {
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (allowedDocExt.indexOf(ext) === -1) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Unsupported file: ' + file.name, text: 'Use PDF, JPG, PNG, DOC or DOCX.' });
                }
                return;
            }
            if (file.size > maxDocBytes) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: file.name + ' is larger than 10 MB.' });
                }
                return;
            }
            const exists = selectedFiles.some(function (existingFile) {
                return existingFile.file.name === file.name && existingFile.file.size === file.size;
            });
            if (!exists) {
                selectedFiles.push({ file: file, type: docType });
            }
        });

        updateFileInput();
        renderFileList();
    }

    documentInput.addEventListener('change', function () {
        addSelectedFiles(this.files);
        this.value = '';
    });

    const uploadBox = document.getElementById('leadUploadBox');
    if (uploadBox) {
        var dragCount = 0;
        uploadBox.addEventListener('dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragCount++;
            uploadBox.classList.add('is-dragover');
        });
        uploadBox.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'copy';
            }
        });
        uploadBox.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragCount--;
            if (dragCount <= 0) {
                dragCount = 0;
                uploadBox.classList.remove('is-dragover');
            }
        });
        uploadBox.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragCount = 0;
            uploadBox.classList.remove('is-dragover');
            addSelectedFiles(e.dataTransfer ? e.dataTransfer.files : []);
        });
    }

    function updateFileInput() {
        const dataTransfer = new DataTransfer();
        if (documentTypeFields) {
            documentTypeFields.innerHTML = '';
        }

        selectedFiles.forEach(function (item) {
            dataTransfer.items.add(item.file);
            if (documentTypeFields) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'document_types[]';
                hidden.value = item.type;
                documentTypeFields.appendChild(hidden);
            }
        });

        documentInput.files = dataTransfer.files;
    }

    function fileSizeLabel(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function fileKind(name) {
        const ext = (name.split('.').pop() || '').toLowerCase();
        if (ext === 'pdf') return { label: 'PDF', cls: 'is-pdf' };
        if (ext === 'doc' || ext === 'docx') return { label: 'DOC', cls: 'is-doc' };
        if (ext === 'jpg' || ext === 'jpeg' || ext === 'png') return { label: 'IMG', cls: 'is-img' };
        return { label: ext.toUpperCase(), cls: 'is-file' };
    }

    function renderFileList() {
        fileList.querySelectorAll('.doc-file-card').forEach(function (item) {
            item.remove();
        });

        if (!selectedFiles.length) {
            uploadingStatus.hidden = true;
            fileList.hidden = true;
            return;
        }

        uploadingStatus.hidden = true;
        fileList.hidden = false;

        selectedFiles.forEach(function (item, index) {
            const kind = fileKind(item.file.name);
            const card = document.createElement('div');
            card.className = 'doc-file-card';
            card.innerHTML =
                '<div class="doc-file-icon ' + kind.cls + '">' + kind.label + '</div>' +
                '<div class="doc-file-meta">' +
                    '<strong></strong>' +
                    '<span></span>' +
                '</div>' +
                '<em class="doc-file-badge">Ready</em>' +
                '<button type="button" class="doc-file-remove file-remove-btn" data-index="' + index + '" aria-label="Delete File">&times;</button>';
            card.querySelector('strong').textContent = item.file.name;
            card.querySelector('span').textContent = [item.type, fileSizeLabel(item.file.size)].filter(Boolean).join(' · ');
            fileList.appendChild(card);
        });
    }

    fileList.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.file-remove-btn');
        if (!removeBtn) {
            return;
        }

        const index = parseInt(removeBtn.dataset.index);
            selectedFiles.splice(index, 1);
            updateFileInput();
            renderFileList();
    });

    function previewFile(file) {
        previewBody.innerHTML = '';
        previewTitle.textContent = file.name;

        const fileUrl = URL.createObjectURL(file);
        const fileType = file.type.toLowerCase();
        const extension = file.name.split('.').pop().toLowerCase();

        if (fileType.startsWith('image/') || ['jpg', 'jpeg', 'png'].includes(extension)) {
            const img = document.createElement('img');
            img.src = fileUrl;
            img.alt = file.name;
            previewBody.appendChild(img);
        } else if (fileType === 'application/pdf' || extension === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = fileUrl;
            previewBody.appendChild(iframe);
        } else {
            const message = document.createElement('div');
            message.textContent = 'Preview is not available for this file type.';
            previewBody.appendChild(message);
        }

        previewModal.classList.add('active');
    }

    function closePreview() {
        previewModal.classList.remove('active');
        previewBody.innerHTML = '';
    }

    previewClose.addEventListener('click', closePreview);

    previewModal.addEventListener('click', function (e) {
        if (e.target === previewModal) {
            closePreview();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePreview();
        }
    });

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    const gstinInput = document.querySelector('input[name="gstin"]');
    const gstStatus = document.getElementById('gstStatus');

    if (gstinInput) {
        let lastFetchedGstin = '';

        gstinInput.addEventListener('input', function () {
            let val = gstinInput.value.toUpperCase().replace(/\s+/g, '');

            if (val.length > 15) {
                val = val.substring(0, 15);
            }

            gstinInput.value = val;

            const gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

            if (!val) {
                gstStatus.innerHTML = '';
                gstStatus.style.color = '';
            } else if (val.length < 15) {
                gstStatus.style.color = '#6B7280';
                gstStatus.innerHTML = 'Enter 15-character GSTIN';
            } else if (!gstinPattern.test(val)) {
                gstStatus.style.color = '#DC2626';
                gstStatus.innerHTML = '✕ Invalid GSTIN';
            } else {
                processGstinSearch();
            }
        });

        gstinInput.addEventListener('blur', processGstinSearch);

        function processGstinSearch() {
            const gstin = gstinInput.value.trim();

            if (!gstin) {
                gstStatus.innerHTML = '';
                gstStatus.style.color = '';
                return;
            }

            const gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

            if (!gstinPattern.test(gstin)) {
                gstStatus.style.color = '#DC2626';
                gstStatus.innerHTML = '✕ Invalid GSTIN';
                return;
            }

            if (gstin === lastFetchedGstin) {
                return;
            }

            gstStatus.style.color = '#6B7280';
            gstStatus.innerHTML = 'Fetching GST details...';

            const csrfTokenInput = document.querySelector('input[name="_token"]');
            const csrfToken = csrfTokenInput ? csrfTokenInput.value : '';
            const saveBtn = document.getElementById('saveLeadBtn');

            if (saveBtn) {
                saveBtn.disabled = true;
            }

            fetch("{{ route('search-gstin') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ gstin: gstin })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw err; });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    gstStatus.style.color = '#DC2626';
                    gstStatus.innerHTML = '✕ ' + escapeHtml(data.message || 'Invalid GSTIN');
                    lastFetchedGstin = '';
                    return;
                }

                gstStatus.style.color = '#16A34A';
                gstStatus.innerHTML = '✓ GSTIN details verified!';
                lastFetchedGstin = gstin;

                const companyNameInput = document.querySelector('input[name="company_name"]');
                const mailingAddressInput = document.querySelector('input[name="mailing_address"]');
                const stateInput = document.querySelector('input[name="state"]');
                const cityInput = document.querySelector('input[name="city"]');
                const pincodeInput = document.querySelector('input[name="pincode"]');
                const countryInput = document.querySelector('input[name="country"]');

                if (companyNameInput && data.company_name) {
                    companyNameInput.value = data.company_name;
                }

                if (mailingAddressInput && data.company_address) {
                    mailingAddressInput.value = data.company_address;
                }

                if (data.country) {
                    if (countryInput) countryInput.value = data.country;
                    setSelectLabel(countryBtn, data.country, 'Select Country');
                    refreshStates();
                }
                if (data.state) {
                    if (stateInput) stateInput.value = data.state;
                    setSelectLabel(stateBtn, data.state, 'Select State');
                    refreshCities();
                }
                if (cityInput && data.city) {
                    cityInput.value = data.city;
                    setSelectLabel(cityBtn, data.city, 'Select City');
                }

                if (pincodeInput && data.pincode) {
                    pincodeInput.value = data.pincode;
                }

                const industryInput = document.querySelector('input[name="industry"]');
                if (industryInput) {
                    let industry = data.constitution || '';
                    if (!industry && data.nature_of_business) {
                        industry = Array.isArray(data.nature_of_business)
                            ? data.nature_of_business.join(', ')
                            : data.nature_of_business;
                    }
                    if (industry) {
                        industryInput.value = industry;
                    }
                }
            })
            .catch(error => {
                const errorMsg = error.message || 'Could not fetch GSTIN details.';
                gstStatus.style.color = '#DC2626';
                gstStatus.innerHTML = '✕ ' + escapeHtml(errorMsg);
                lastFetchedGstin = '';
            })
            .finally(() => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
            });
        }
    }

    function clearFieldError(el) {
        if (!el) return;
        el.classList.remove('is-invalid');
        const wrap = el.closest('.form-group') || el.parentElement;
        if (!wrap) return;
        wrap.querySelectorAll('.js-field-error[data-for="' + el.name + '"]').forEach(function (err) { err.remove(); });
    }

    function showFieldError(el, msg) {
        if (!el) return;
        clearFieldError(el);
        el.classList.add('is-invalid');
        const wrap = el.closest('.form-group') || el.parentElement;
        if (!wrap) return;
        const err = document.createElement('div');
        err.className = 'form-error js-field-error';
        err.setAttribute('data-for', el.name);
        err.textContent = msg;
        wrap.appendChild(err);
    }

    function goToField(el) {
        if (!el) return;
        const section = el.closest('.form-left, .form-right, .side-card, .common-form, .form-group') || el;
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { el.focus(); }, 250);
    }

    const leadForm = document.getElementById('leadForm');
    if (leadForm) {
        leadForm.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            }
        });
        leadForm.addEventListener('input', function (e) {
            if (e.target && e.target.name) {
                clearFieldError(e.target);
            }
        });
        leadForm.addEventListener('change', function (e) {
            if (e.target && e.target.name) {
                clearFieldError(e.target);
            }
        });
        leadForm.addEventListener('submit', function (e) {
            leadForm.querySelectorAll('.js-field-error').forEach(function (err) { err.remove(); });
            leadForm.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

            const checks = [
                { name: 'salutation', msg: 'Title is required.' },
                { name: 'name', msg: 'Name is required.' },
                { name: 'email', msg: 'Email is required.' },
                { name: 'mobile', msg: 'Mobile No. is required.' },
                { name: 'lead_status', msg: 'Lead Status is required.' }
            ];
            let firstInvalid = null;
            checks.forEach(function (item) {
                const el = leadForm.querySelector('[name="' + item.name + '"]');
                if (!el || !String(el.value || '').trim()) {
                    showFieldError(el, item.msg);
                    if (!firstInvalid && el) firstInvalid = el;
                }
            });
            const emailEl = leadForm.querySelector('[name="email"]');
            if (emailEl && String(emailEl.value || '').trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(emailEl.value).trim())) {
                showFieldError(emailEl, 'Enter a valid email.');
                if (!firstInvalid) firstInvalid = emailEl;
            }
            const mobileEl = leadForm.querySelector('[name="mobile"]');
            if (mobileEl && String(mobileEl.value || '').trim() && !/^[0-9]{6,15}$/.test(String(mobileEl.value || '').trim())) {
                showFieldError(mobileEl, 'Enter a valid mobile number.');
                if (!firstInvalid) firstInvalid = mobileEl;
            }
            if (firstInvalid) {
                e.preventDefault();
                goToField(firstInvalid);
                return false;
            }
        });
    }

    document.addEventListener('input', function (e) {
        if (!e.target.classList.contains('js-mobile-digits')) {
            return;
        }
        e.target.value = String(e.target.value || '').replace(/\D/g, '').slice(0, 15);
    });

});
</script>

</div>
@endsection
