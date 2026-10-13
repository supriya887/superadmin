@extends('admin.layouts.app')

@section('title', 'Customers')

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            display: none;
        }
        table.dataTable {
            border-bottom: none !important;
        }
        table.dataTable thead th {
            border-bottom: 1px solid #eee !important;
        }
    </style>
@endsection

@section('content')
    <div class="page-header" data-aos="fade-up" data-aos-duration="600">
        <h1 class="page-title">Customers Management</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Customers</li>
            </ol>
        </nav>
    </div>

    <div class="table-card" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
        <div class="table-toolbar">
            <div style="display: flex; gap: 10px;">

                <div class="toolbar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="cust-search" placeholder="Search Customer">
                </div>
                <a href="{{ route('partner-admin.customers.create') }}" class="btn btn-submit py-2 px-4 rounded-pill m-0"
                    style="font-size: 13px;">Add New Customer</a>
            </div>

            <div class="toolbar-actions">
                <span>Filter By:</span>
                <select id="status-filter" style="width: 120px;">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>

                <select id="page-length" style="width: 70px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>

            </div>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="customers-table">
                <thead>
                    <tr>
                        <th style="width: 22%">NAME <i class="fas fa-arrows-alt-v"></i></th>
                        <th style="width: 20%">COMPANY <i class="fas fa-arrows-alt-v"></i></th>
                        <th style="width: 20%">EMAIL <i class="fas fa-arrows-alt-v"></i></th>
                        <th style="width: 15%">SUBSCRIPTION <i class="fas fa-arrows-alt-v"></i></th>
                        <th style="width: 9%">SPEND <i class="fas fa-arrows-alt-v"></i></th>
                        <th style="width: 10%">STATUS <i class="fas fa-arrows-alt-v"></i></th>
                        <th style="width: 4%" class="text-center">...</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td>
                                <div class="user-td">
                                    <div
                                        class="avatar-initial {{ ['bg-blue', 'bg-yellow', 'bg-purple', 'bg-pink'][$loop->index % 4] }}">
                                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                                    </div>
                                    <span>{{ $customer->name }}</span>
                                </div>
                            </td>
                            <td>{{ $customer->company_name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>
                                <span
                                    class="status-badge {{ $customer->status == 1 ? 'status-active' : 'status-inactive' }}">
                                    {{ $customer->status == 1 ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: 5px;">
                                    <a href="javascript:void(0)" class="action-btn edit-btn" data-id="{{ $customer->id }}"><i class="fas fa-pencil-alt"></i></a>
                                    <a href="javascript:void(0)" class="action-btn delete-btn" data-id="{{ $customer->id }}"><i class="far fa-trash-alt"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer / Pagination -->
        <div class="table-footer">
            <div class="table-info">
                Showing {{ $customers->count() }} customers
            </div>
            <div class="pagination">
                <a href="#" class="page-item"><i class="fas fa-chevron-left"></i></a>
                <a href="#" class="page-item active">1</a>
                <a href="#" class="page-item">2</a>
                <a href="#" class="page-item"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Edit Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="form">
                        @csrf
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
                                    <option value="">Select State</option>
                                </select>
                                <div class="text-danger" id="error-state"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City <span>*</span></label>
                                <select class="form-select" name="city" id="city">
                                    <option value="">Select City</option>
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
                                    <input type="text" class="form-control only-float" name="pan_no" id="pan_no" value="{{ old('pan_no') }}" readonly>
                                    <div class="text-danger" id="error-pan_no"></div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Company Address <span>*</span></label>
                                    <input type="text" class="form-control" name="company_address" id="company_address" value="{{ old('company_address') }}" readonly>
                                    <div class="text-danger" id="error-company_address"></div>
                                </div>
                            </div>
                        </div>
                        <!-- Submit Button -->
                        <div class="submit-btn-container">
                            <input type="hidden" name="id" id="id" value="">
                            <button type="submit" id="submit" class="btn btn-submit">
                                <span id="spinner" class="spinner-border spinner-border-sm" style="display: none;"></span> Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('js')
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#customers-table').DataTable({
                paging: true,
                searching: true,
                info: false,
                lengthChange: false,
                pageLength: 10,
                dom: 't', // Table only
                order: [] // Disable initial sorting
            });

            // Custom Search
            $('#cust-search').on('keyup', function() {
                table.search($(this).val()).draw();
            });

            // Status Filter
            $('#status-filter').on('change', function() {
                table.column(5).search($(this).val()).draw();
            });

            // Page Length
            $('#page-length').on('change', function() {
                table.page.len($(this).val()).draw();
            });

            // Pagination Sync
            $(document).on('click', '.pagination .page-item.num', function(e) {
                e.preventDefault();
                table.page($(this).data('page')).draw('page');
            });
            $(document).on('click', '.pagination .page-item.prev:not(.disabled)', function(e) {
                e.preventDefault();
                table.page('previous').draw('page');
            });
            $(document).on('click', '.pagination .page-item.next:not(.disabled)', function(e) {
                e.preventDefault();
                table.page('next').draw('page');
            });

            // Update pagination UI on draw
            table.on('draw', function() {
                var info = table.page.info();
                $('.table-info').html(`Showing ${info.start + 1} to ${info.end} of ${info.recordsDisplay} customers`);
                
                var paginationHtml = `<a href="#" class="page-item prev ${info.page === 0 ? 'disabled' : ''}"><i class="fas fa-chevron-left"></i></a>`;
                for (var i = 0; i < info.pages; i++) {
                    // Show first 3, last 1, and 1 around current
                    if (i < 3 || i > info.pages - 2 || (i >= info.page - 1 && i <= info.page + 1)) {
                        paginationHtml += `<a href="#" class="page-item num ${i === info.page ? 'active' : ''}" data-page="${i}">${i + 1}</a>`;
                    } else if (i === 3 || i === info.pages - 2) {
                        paginationHtml += `<span class="page-dots">...</span>`;
                    }
                }
                paginationHtml += `<a href="#" class="page-item next ${info.page === info.pages - 1 ? 'disabled' : ''}"><i class="fas fa-chevron-right"></i></a>`;
                $('.pagination').html(paginationHtml);
            });
            
            // Initial trigger
            table.draw();

            // Fetch and show customer details in modal
            $(document).on('click', '.edit-btn', function() {
                var id = $(this).data('id');
                // Show loading state or just clear potential old data
                $('#form')[0].reset();
                $('.form-control').removeClass('border-danger');
                $('.text-danger').html('');

                $.ajax({
                    url: "{{ url('partner-admin/customers/edit') }}/" + id,
                    type: "GET",
                    success: function(data) {
                        $('#edit-modal').find('#id').val(data.id);
                        $('#edit-modal').find('#gstin').val(data.gstin);
                        $('#edit-modal').find('#name').val(data.name);
                        $('#edit-modal').find('#email').val(data.email);
                        $('#edit-modal').find('#mobile').val(data.mobile);
                        $('#edit-modal').find('#country').val(data.country).trigger('change');
                        $('#edit-modal').find('#zip_code').val(data.zip_code);
                        $('#edit-modal').find('#company_name').val(data.company_name).prop('readonly', true);
                        $('#edit-modal').find('#company_address').val(data.company_address).prop('readonly', true);
                        $('#edit-modal').find('#pan_no').val(data.pan_no).prop('readonly', true);
                        $('#edit-modal').find('#status').val(data.status);
                        
                        if (data.gstin && data.gstin.trim() !== '') {
                            $('#company-section').show();
                        } else {
                            $('#company-section').hide();
                        }

                        if (data.country === 'India') {
                            loadIndiaStates(data.state, data.city);
                        }
                        
                        $('#edit-modal').modal('show');
                    },
                    error: function() {
                        alertify.error('Failed to fetch customer details');
                    }
                });
            });

            // Use global formSubmit for updating
            formSubmit("{{ route('partner-admin.customers.update') }}",
                "{{ route('partner-admin.customers.index') }}", "form");

            // GSTIN Dynamic UI
            $('#gstin').on('input', function() {
                if ($(this).val().trim() !== "") {
                    $('#company-section').slideDown();
                } else {
                    $('#company-section').slideUp();
                }
            });

            // Form Validation
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
            function searchGstin() {
                var gstin = $('#gstin').val().toUpperCase();
                $('#gstin').val(gstin); // Ensure uppercase
                
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
                                $('#company-section').slideDown();
                                $('#company_name').val(response.company_name).prop('readonly', true);
                                $('#company_address').val(response.company_address).prop('readonly', true);
                                $('#zip_code').val(response.pincode);
                                
                                if (response.country === 'India') {
                                    $('#country').val('India').trigger('change');
                                    // Wait for states to load then set state and city
                                    setTimeout(function() {
                                        $('#state').val(response.state).trigger('change');
                                        setTimeout(function() {
                                            $('#city').val(response.city);
                                        }, 500);
                                    }, 500);
                                }
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
            }

            $('#gstin').on('input focusout', function(e) {
                if (e.type === 'input' && $(this).val().length === 15) {
                    searchGstin();
                } else if (e.type === 'focusout') {
                    searchGstin();
                }
            });
            $(document).on('click', '.delete-btn', function() {
                var id = $(this).data('id');
                if (confirm("Are you sure you want to delete this customer?")) {
                    $.ajax({
                        url: "{{ url('partner-admin/customers/delete') }}/" + id,
                        type: "DELETE",
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            if (response.success) {
                                alertify.success(response.message);
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                alertify.error(response.message);
                            }
                        },
                        error: function() {
                            alertify.error('Failed to delete customer');
                        }
                    });
                }
            });

            function loadIndiaStates(selectedState = null, selectedCity = null) {
                $('#state').html('<option value="">Loading...</option>');
                $.get('{{ url('api/get-states') }}', function(res) {
                    if (res && res.data && res.data.states) {
                        let h = '<option value="">Select State</option>';
                        res.data.states.forEach(s => h += `<option value="${s.name}">${s.name}</option>`);
                        $('#state').html(h);
                        if (selectedState) {
                            $('#state').val(selectedState);
                            loadIndiaCities(selectedState, selectedCity);
                        }
                    } else {
                        alertify.error('Unable to load states');
                    }
                });
            }

            function loadIndiaCities(state, selectedCity = null) {
                if (!state) return;
                $('#city').html('<option value="">Loading...</option>');
                $.post('{{ url('api/get-cities') }}', {
                    _token: '{{ csrf_token() }}',
                    state: state
                }, function(res) {
                    if (res && res.data && res.data.length > 0) {
                        let h = '<option value="">Select City</option>';
                        res.data.forEach(c => h += `<option value="${c}">${c}</option>`);
                        $('#city').html(h);
                        if (selectedCity) {
                            $('#city').val(selectedCity);
                        }
                    } else {
                        $('#city').html('<option value="">No data found</option>');
                    }
                });
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
        });
    </script>
@endsection
