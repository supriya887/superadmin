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
                <a href="{{ route('partner.partneradmin.customers.create') }}" class="btn btn-submit py-2 px-4 rounded-pill m-0"
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
                <tbody id="customers-table-body">
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
                                <label class="form-label">Mobile <span>*</span></label>
                                <input type="text" class="form-control" name="mobile" id="mobile" value="{{ old('mobile') }}">
                                <div class="text-danger" id="error-mobile"></div>
                            </div>
                        
                            <div class="col-md-6">
                                <label class="form-label">Customer status (Active / Inactive) <span>*</span></label>
                                <select class="form-control" name="status" id="status">
                                    <option value="1" {{ old('status', '1') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                <div class="text-danger" id="error-status"></div>
                            </div>
                        </div>

                        <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">GSTIN <span>*</span></label>
                            <input type="text"
                                class="form-control bg-light" name="gstin" id="gstin" value="{{ old('gstin') }}" readonly>
                            <div class="text-danger" id="error-gstin"></div>
                        </div>

                        </div>

                        <input type="hidden" name="zip_code" id="zip_code" value="{{ old('zip_code') }}">
                        <div id="company-section" >
                            {{-- <hr class="my-4" style="display:none;"> --}}
                            <p class="text-muted mb-3">Company & address (from GSTIN lookup)</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">PAN No. <span>*</span></label>
                                    <input type="text" class="form-control" name="pan_no" id="pan_no" value="{{ old('pan_no') }}" readonly>
                                    <div class="text-danger" id="error-pan_no"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Company Name <span>*</span></label>
                                    <input type="text" class="form-control bg-light" name="company_name" id="company_name" value="{{ old('company_name') }}" readonly>
                                    <div class="text-danger" id="error-company_name"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Company Address <span>*</span></label>
                                    <textarea class="form-control bg-light" name="company_address" id="company_address" rows="2" value="{{ old('company_address') }}"readonly></textarea>
                                    <div class="text-danger" id="error-company_address"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Country <span>*</span></label>
                                    <input type="text" class="form-control bg-light" name="country" id="country" placeholder="Country" value="{{ old('country') }}" readonly>
                                    <div class="text-danger fs-xs" id="error-country"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">State <span>*</span></label>
                                    <input type="text" class="form-control bg-light" name="state" id="state" placeholder="State" value="{{ old('state') }}" readonly>
                                    <div class="text-danger fs-xs" id="error-state"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">City <span>*</span></label>
                                    <input type="text" class="form-control bg-light" name="city" id="city" placeholder="City" value="{{ old('city') }}" readonly>
                                    <div class="text-danger fs-xs" id="error-city"></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Pincode <span>*</span></label>
                                    <input type="text" class="form-control bg-light" name="postcode" id="postcode" placeholder="Pincode" value="{{ old('postcode') }}">
                                    <div class="text-danger fs-xs" id="error-postcode"></div>
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

            function escapeHtml(value) {
                return $('<div>').text(value ?? '').html();
            }

            function buildCustomerRow(customer, index) {
                var avatarClasses = ['bg-blue', 'bg-yellow', 'bg-purple', 'bg-pink'];
                var avatarClass = avatarClasses[index % avatarClasses.length];
                var name = escapeHtml(customer.name || '');
                var companyName = escapeHtml(customer.company_name || '');
                var email = escapeHtml(customer.email || '');
                var initial = name ? name.charAt(0).toUpperCase() : 'C';
                var isActive = Number(customer.status) === 1;
                var statusClass = isActive ? 'status-active' : 'status-inactive';
                var statusLabel = isActive ? 'Active' : 'Inactive';
                var id = customer.id;

                return [
                    `<div class="user-td"><div class="avatar-initial ${avatarClass}">${initial}</div><span>${name}</span></div>`,
                    companyName,
                    email,
                    '-',
                    '-',
                    `<span class="status-badge ${statusClass}">${statusLabel}</span>`,
                    `<div style="display: flex; gap: 5px;"><a href="javascript:void(0)" class="action-btn edit-btn" data-id="${id}"><i class="fas fa-pencil-alt"></i></a></div>`
                ];
            }

            function fetchCustomers() {
                $.ajax({
                    url: "{{ route('partner.partneradmin.customers.index') }}",
                    type: "GET",
                    dataType: "json",
                    success: function(res) {
                        var customers = (res && res.customers) ? res.customers : [];
                        table.clear();
                        $.each(customers, function(index, customer) {
                            table.row.add(buildCustomerRow(customer, index));
                        });
                        table.draw();
                    },
                    error: function() {
                        alertify.error('Failed to fetch customers list');
                    }
                });
            }

            fetchCustomers();

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
                if (info.recordsDisplay === 0) {
                    $('.table-info').html('Showing 0 customers');
                } else {
                    $('.table-info').html(`Showing ${info.start + 1} to ${info.end} of ${info.recordsDisplay} customers`);
                }
                
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
            
            // Fetch and show customer details in modal
            $(document).on('click', '.edit-btn', function() {
                var id = $(this).data('id');
                // Show loading state or just clear potential old data
                $('#form')[0].reset();
                $('.form-control').removeClass('border-danger');
                $('.text-danger').html('');

                $.ajax({
                    url: "{{ route('partner.partneradmin.customers.edit', ['id' => ':id']) }}".replace(':id', id),
                    type: "GET",
                    success: function(data) {
                        $('#edit-modal').find('#id').val(data.id);
                        $('#edit-modal').find('#partner_name').val(data.partner_name || data.partner?.name || '{{ auth()->user()->name ?? '' }}');
                        $('#edit-modal').find('#gstin').val(data.gstin);
                        $('#edit-modal').find('#name').val(data.name);
                        $('#edit-modal').find('#email').val(data.email);
                        $('#edit-modal').find('#mobile').val(data.mobile);
                        $('#edit-modal').find('#zip_code').val(data.zip_code || '');
                        $('#edit-modal').find('#country').val(data.country || '');
                        $('#edit-modal').find('#state').val(data.state || '');
                        $('#edit-modal').find('#city').val(data.city || '');
                        $('#edit-modal').find('#postcode').val(data.postcode || data.zip_code || '');
                        $('#edit-modal').find('#company_name').val(data.company_name).prop('readonly', true);
                        $('#edit-modal').find('#company_address').val(data.company_address || '');
                        $('#edit-modal').find('#pan_no').val(data.pan_no).prop('readonly', true);
                        $('#edit-modal').find('#status').val(data.status);
                        
                        if (data.gstin && data.gstin.trim() !== '') {
                            $('#company-section').show();
                        } else {
                            $('#company-section').hide();
                        }

                        $('#edit-modal').modal('show');
                    },
                    error: function() {
                        alertify.error('Failed to fetch customer details');
                    }
                });
            });

            // GSTIN Dynamic UI
            $('#gstin').on('input', function() {
                if ($(this).val().trim() !== "") {
                    $('#company-section').slideDown();
                } else {
                    $('#company-section').slideUp();
                }
            });

            $('#postcode').on('input', function() {
                $('#zip_code').val($(this).val().trim());
            });

            $('#form').on('submit', function(e) {
                e.preventDefault();

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

                $.ajax({
                    url: "{{ route('partner.partneradmin.customers.update') }}",
                    type: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    beforeSend: function() {
                        $("#spinner").show();
                        $("#submit").attr("disabled", true);
                    },
                    success: function(data) {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);

                        if (data.error) {
                            $.each(data, function(key, value) {
                                if (value) {
                                    $("#error-" + key).html(value);
                                    $("#" + key).addClass("border-danger");
                                } else {
                                    $("#error-" + key).html(" ");
                                    $("#" + key).removeClass("border-danger");
                                }
                            });
                            return;
                        }

                        if (data.success) {
                            $("#form .form-control").removeClass("border-danger");
                            $("#form .text-danger").html(" ");
                            alertify.success(data.success);
                            $('#edit-modal').modal('hide');
                            fetchCustomers();
                        }
                    },
                    error: function() {
                        $("#spinner").hide();
                        $("#submit").attr("disabled", false);
                        alertify.error('Failed to update customer');
                    }
                });
            });
            function searchGstin() {
                if ($('#gstin').prop('readonly')) {
                    return;
                }
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
                                $('#company_address').val(response.company_address || '');
                                var pin = response.pincode || response.zip_code || '';
                                $('#zip_code').val(pin);
                                $('#postcode').val(pin);
                                $('#country').val(response.country || 'India');
                                $('#state').val(response.state || '');
                                $('#city').val(response.city || response.district || response.location || '');
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
                        url: "{{ route('partner.partneradmin.customers.delete', ['id' => ':id']) }}".replace(':id', id),
                        type: "DELETE",
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            if (response.success) {
                                alertify.success(response.message);
                                fetchCustomers();
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

            // For float/numeric fields restriction
            $('.only-float').on('input', function() {
                this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
            });
        });
    </script>
@endsection
