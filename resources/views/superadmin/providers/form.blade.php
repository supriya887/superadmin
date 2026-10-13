@extends('superadmin.layouts.app')

@section('content')
<div class="main-container container-fluid">

    <div class="breadcrumb-header justify-content-between">
        <div>
            <br>
            <br>
            <h4 class="content-title mb-2">{{ $title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Super Admin</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('superadmin.providers.index') }}">Providers</a>
                    </li>
                    <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex my-auto">
            <a href="{{ route('superadmin.providers.index') }}" class="btn btn-primary">
                <i class="mdi mdi-arrow-left me-1"></i> Back
            </a>
           
        </div>
    </div>

    <div class="card mt-2">
        
        <div class="card-body">
            <form id="form" >
                @csrf
                <input type="hidden" name="id" id="id"
                    value="{{ isset($update) ? $update->id : '' }}">

                <div class="row">

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="name" id="name"
                                value="{{ isset($update) ? $update->name : '' }}"
                                placeholder="e.g. Google Workspace">
                            <div class="text-danger" id="error-name"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Slug <span class="text-muted small">(auto-generated if blank)</span></label>
                            <input class="form-control" type="text" name="slug" id="slug"
                                value="{{ isset($update) ? $update->slug : '' }}"
                                placeholder="e.g. google-workspace">
                            <div class="text-danger" id="error-slug"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Type <span class="text-danger">*</span></label>
                            <select class="form-control" name="type" id="type">
                                <option value="">-- Select Type --</option>
                                <option value="email"
                                    {{ isset($update) && $update->type == 'email' ? 'selected' : '' }}>EMAIL</option>
                                {{-- <option value="cloud"
                                    {{ isset($update) && $update->type == 'cloud' ? 'selected' : '' }}>CLOUD</option>
                                <option value="saas"
                                    {{ isset($update) && $update->type == 'saas' ? 'selected' : '' }}>SAAS</option> --}}
                            </select>
                            <div class="text-danger" id="error-type"></div>
                        </div>
                    </div>

                    

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select class="form-control" name="status2" id="status2">
                                <option value="active"
                                    {{ !isset($update) || $update->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive"
                                    {{ isset($update) && $update->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            <div class="text-danger" id="error-status2"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Has Variant <span class="text-danger">*</span></label>
                            <select class="form-control" name="has_variant" id="has_variant">
                                <option value="no"
                                    {{ isset($update) && $update->has_variant == 'no' ? 'selected' : '' }}>No</option>
                                <option value="yes"
                                    {{ isset($update) && $update->has_variant == 'yes' ? 'selected' : '' }}>Yes</option>
                            </select>
                            <div class="text-danger" id="error-has_variant"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Transfer Type <span class="text-danger">*</span></label>
                            <select class="form-control" name="transfer_type" id="transfer_type">
                                <option value="">-- Select Transfer Type --</option>
                                <option value="code"
                                    {{ isset($update) && $update->transfer_type == 'code' ? 'selected' : '' }}>Code</option>
                                <option value="link"
                                    {{ isset($update) && $update->transfer_type == 'link' ? 'selected' : '' }}>Link</option>
                            </select>
                            <div class="text-danger" id="error-transfer_type"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3" id="transfer_detail_wrapper" style="{{ isset($update) && in_array($update->transfer_type ?? '', ['code', 'link'], true) ? '' : 'display:none;' }}">
                        <div class="form-group">
                            <label><span id="transfer_detail_label">{{ isset($update) ? ($update->transfer_type === 'code' ? 'Transfer Code' : ($update->transfer_type === 'link' ? 'Transfer Link' : 'Transfer Detail')) : 'Transfer Detail' }}</span> <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="transfer_detail" id="transfer_detail"
                                value="{{ isset($update) ? $update->transfer_detail : '' }}"
                               >
                            <div class="text-danger" id="error-transfer_detail"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Website URL</label>
                            <input class="form-control" type="url" name="website_url" id="website_url"
                                value="{{ isset($update) ? $update->website_url : '' }}"
                                placeholder="https://example.com">
                            <div class="text-danger" id="error-website_url"></div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label>Logo</label>
                            @if(isset($update) && $update->logo)
                                <div class="mb-2">
                                    <img src="{{ asset('storage/app/public/' . $update->logo) }}"
                                         alt="Logo"
                                         style="height: 48px; border-radius: 4px; border: 1px solid #ddd;">
                                    <div class="text-muted small mt-1">Upload a new file to replace.</div>
                                </div>
                            @endif
                            <input class="form-control" type="file" name="logo" id="logo" accept="image/*">
                            <div class="text-danger" id="error-logo"></div>
                        </div>
                    </div>
                    <div class="col-lg-12 mb-3">
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="description"
                                rows="3" placeholder="Short description of this provider">{{ isset($update) ? $update->description : '' }}</textarea>
                            <div class="text-danger" id="error-description"></div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="form-group text-center">
                            <button class="btn btn-success w-md" type="submit" id="submit">
                                <div class="spinner-border spinner-border-sm float-left mt-1"
                                     id="spinner" style="display:none" role="status"><span class="sr-only">Loading...</span>
                                </div>
                                {{ isset($update) ? 'Update' : 'Submit' }}
                            </button>
                            <a href="{{ route('superadmin.providers.index') }}" class="btn btn-primary w-md">Back</a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script type="text/javascript">
    $(document).ready(function () {
       formSubmit('{{ route('superadmin.providers.save') }}', '{{ route('superadmin.providers.index') }}', 'form');
        

        @if(!isset($update))
        var slugManual = false;
        $('#name').on('input', function () {
            if (!slugManual) {
                $('#slug').val($(this).val().toLowerCase().trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-'));
            }
        });
        $('#slug').on('input', function () {
            slugManual = $(this).val().length > 0;
        });
        @endif

        select2NoSearch('type',      '-- Select Type --');
        select2NoSearch('auth_type', '-- Select Auth Type --');
        select2NoSearch('status',    'Status');
        select2NoSearch('transfer_type', '-- Select Transfer Type --');

        function syncTransferDetailLabel() {
            var v = $('#transfer_type').val();
            if (v === 'code') {
                $('#transfer_detail_label').text('Transfer Code');
            } else if (v === 'link') {
                $('#transfer_detail_label').text('Transfer Link');
            } else {
                $('#transfer_detail_label').text('Transfer Detail');
            }
        }

        // Toggle transfer_detail visibility and label based on transfer_type
        $('#transfer_type').on('change', function () {
            var v = $(this).val();
            syncTransferDetailLabel();
            if (v === 'code' || v === 'link') {
                $('#transfer_detail_wrapper').slideDown();
            } else {
                $('#transfer_detail_wrapper').slideUp();
                $('#transfer_detail').val('');
            }
        });
        syncTransferDetailLabel();
       
    });
</script>
@endpush
