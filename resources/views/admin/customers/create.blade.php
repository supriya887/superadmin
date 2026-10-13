@extends('admin.layouts.app')

@section('title', 'Add New Customer')

@section('content')
<div class="page-header" data-aos="fade-up" data-aos-duration="600">
    <h1 class="page-title">Add New Customer</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="#">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page">New Customer</li>
        </ol>
    </nav>
</div>

<div class="content-card" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
   

    <form id="form">
        @csrf
        <!-- Row 1 -->
        
        <!-- Row 2 -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Name <span>*</span></label>
                <input type="text" class="form-control" name="name" id="name" value="{{ old('name') }}">
                <div class="text-danger" id="error-name"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email <span>*</span></label>
                <input type="email" class="form-control" name="email" id="email" value="{{ old('email') }}">
                <div class="text-danger" id="error-email"></div>
            </div>
        </div>

        <!-- Row 3 -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Mobile No. <span>*</span></label>
                <input type="text" class="form-control" name="mobile" id="mobile" value="{{ old('mobile') }}">
                <div class="text-danger" id="error-mobile"></div>
            </div>
        
            
            <div class="col-md-6">
                <label class="form-label">Country <span>*</span></label>
                <select class="form-select" name="country" id="country">
                    <option value="">Select Country</option>
                    <option value="India" {{ old('country') == 'India' ? 'selected' : '' }}>India</option>
                </select>
                <div class="text-danger" id="error-country"></div>
            </div>
        </div>

        <!-- Row 5 -->
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">State <span>*</span></label>
                <select class="form-select" name="state" id="state">
                    <option value="" {{ old('state') == 'India' ? 'selected' : '' }}>Select State</option>
                </select>
                <div class="text-danger" id="error-state"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">City <span>*</span></label>
                 <select class="form-select" name="city" id="city">
                    <option value="" {{ old('city') == 'India' ? 'selected' : '' }} >Select City</option>
                 </select>
                <div class="text-danger" id="error-city"></div>
            </div>
        </div>

        <!-- Row 6 -->
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Zip Code <span>*</span></label>
                <input type="text" class="form-control" name="zip_code" id="zip_code" value="{{ old('zip_code') }}">
                <div class="text-danger" id="error-zip_code"></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">GSTIN <span>*</span></label>
                <input type="text" class="form-control" name="gstin" id="gstin" value="{{ old('gstin') }}" placeholder="AA00001234W548" maxlength="15">
                <div class="text-danger" id="error-gstin"></div>
            </div>
            
        </div>
        <div id="company-section" style="display: none;">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Company Name <span>*</span></label>
                    <input type="text" class="form-control" name="company_name" id="company_name" value="{{ old('company_name') }}" readonly>
                    <div class="text-danger" id="error-company_name"></div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Company PAN No. <span>*</span></label>
                    <input type="text" class="form-control only-float" name="pan_no" id="pan_no" value="{{ old('pan_no') }}">
                    <div class="text-danger" id="error-pan_no"></div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Company Address <span>*</span></label>
                    <input type="text" class="form-control" name="company_address" id="company_address" value="{{ old('company_address') }}" >
                    <div class="text-danger" id="error-company_address"></div>
                </div>
            </div>
        </div>


        <!-- Submit Button -->
        <div class="submit-btn-container">
            {{-- show loader  --}}
            <button type="submit" id="submit" class="btn btn-submit">
                <span id="spinner" class="spinner-border spinner-border-sm" style="display: none;"></span>    
                Submit
            </button>
        </div>
    </form>
</div>
@endsection
@section('js')
<script>
    $(document).ready(function() {
        // Standardized Form Submission
        formSubmit("{{ route('partner-admin.customers.store') }}", "{{ route('partner-admin.customers.index') }}");

        // GSTIN Search and Auto-fill on Focus Out
        $('#gstin').on('focusout', function() {
            var gstin = $(this).val();
            if (gstin.length === 15) {
                // Auto-extract PAN (Chars 3 to 12)
                var pan = gstin.substring(2, 12);
                $('#pan_no').val(pan).prop('readonly', true);

                $.ajax({
                    url: "{{ route('search-gstin') }}",
                    type: "POST",
                    data: {
                        gstin: gstin,
                        _token: "{{ csrf_token() }}"
                    },
                    beforeSend: function() {
                        $('#gstin').addClass('loading');
                    },
                    success: function(response) {
                        $('#gstin').removeClass('loading');
                        if (response.company_name) {
                            // give readonly
                            $('#company_name').val(response.company_name).prop('readonly', true);
                            $('#company_address').val(response.company_address).prop('readonly', true);
                            
                            
                            
                            alertify.success('GSTIN details fetched successfully!');
                        } else if (response.error) {
                            alertify.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        $('#gstin').removeClass('loading');
                        var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error fetching GSTIN details';
                        alertify.error(errorMsg);
                    }
                });
            }
        });

        function loadIndiaStates() {
            $('#state').html('<option value="">Loading...</option>');
            $.get('{{ url('api/get-states') }}', function(res) {
                if (res && res.data && res.data.states) {
                    let h = '<option value="">Select State</option>';
                    res.data.states.forEach(s => h += `<option value="${s.name}">${s.name}</option>`);
                    $('#state').html(h);
                } else {
                    alertify.error('Unable to load states');
                }
            }).fail(() => alertify.error('Unable to load states'));
        }

        function loadIndiaCities(state) {
            $('#city').html('<option value="">Loading...</option>');
            $.post('{{ url('api/get-cities') }}', {
                _token: '{{ csrf_token() }}',
                state: state
            }, function(res) {
                if (res && res.data && res.data.length > 0) {
                    let h = '<option value="">Select City</option>';
                    res.data.forEach(c => h += `<option value="${c}">${c}</option>`);
                    $('#city').html(h);
                } else {
                    $('#city').html('<option value="">No data found</option>');
                }
            }).fail(() => alertify.error('Unable to load cities'));
        }

        $('#country').change(function() {
            if ($(this).val() === 'India') {
                loadIndiaStates();
            } else {
                $('#state').html('<option value="">Select State</option>');
                $('#city').html('<option value="">Select City</option>');
            }
        });

        $('#state').change(function() {
            loadIndiaCities($(this).val());
        });

        // For float/numeric fields restriction
        $('.only-float').on('input', function() {
            this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
        });

        // GSTIN Dynamic UI
        $('#gstin').on('input', function() {
            if ($(this).val().trim() !== "") {
                $('#company-section').slideDown();
            } else {
                $('#company-section').slideUp();
            }
        });

        // Form Validation for Company Details
        $('#form').on('submit', function(e) {
            if ($('#gstin').val().trim() !== "") {
                var name = $('#company_name').val().trim();
                var pan = $('#pan_no').val().trim();
                var addr = $('#company_address').val().trim();
                if (name === "" || pan === "" || addr === "") {
                    alertify.error("Please fill all company details");
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
</script>
@endsection
