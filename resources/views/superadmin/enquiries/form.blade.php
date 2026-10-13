@extends('superadmin.layouts.app')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('superadmin/enquiry-table') }}">Enquiries</a></li>
                    <li class="breadcrumb-item active">{{ $title }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Enquiry Details</h5>
            </div>
            <div class="card-body">
                <form id="form">
                    @csrf
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="mb-3">
                                <label class="form-label">Partner <span class="text-danger">*</span></label>
                                <select class="form-select" name="partner_id" id="partner_id">
                                    <option value="">— Select partner —</option>
                                    @foreach($partners as $partner)
                                        <option value="{{ $partner->id }}"
                                            {{ (string) ($update->partner_id ?? '') === (string) $partner->id ? 'selected' : '' }}>
                                            {{ $partner->name }} @if($partner->email) ({{ $partner->email }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                <div class="text-danger fs-xs" id="error-partner_id"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="message" id="message" rows="5">{{ $update->message ?? '' }}</textarea>
                                <div class="text-danger fs-xs" id="error-message"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" id="status">
                                    @foreach($statuses as $st)
                                        <option value="{{ $st }}"
                                            {{ strtolower((string) ($update->status ?? 'new')) === $st ? 'selected' : '' }}>
                                            {{ ucwords(str_replace('_', ' ', $st)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="text-danger fs-xs" id="error-status"></div>
                            </div>

                            <div class="text-center mt-4">
                                <input type="hidden" name="id" id="id" value="{{ $update->id ?? '' }}">
                                <a href="{{ url('superadmin/enquiry-table') }}" class="btn btn-soft-danger me-2">Back</a>
                                <button type="submit" class="btn btn-primary" id="submit">
                                    <span class="spinner-border spinner-border-sm me-1" id="spinner" style="display:none;"></span>
                                    Save Enquiry
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            if (typeof $.fn.select2 === 'function') {
                $('#partner_id').select2({ placeholder: '— Select partner —', allowClear: true, width: '100%' });
            }

            $('#form').on('submit', function(e) {
                e.preventDefault();
                $('#submit').attr('disabled', true);
                $('#spinner').show();
                $('.text-danger.fs-xs').text('');
                $('.form-control, .form-select').removeClass('is-invalid');

                $.ajax({
                    url: "{{ url('superadmin/enquiry-validation') }}",
                    type: "POST",
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        $('#submit').attr('disabled', false);
                        $('#spinner').hide();
                        if (data.error) {
                            if (data.partner_id) {
                                $('#error-partner_id').text(data.partner_id);
                                $('#partner_id').addClass('is-invalid');
                            }
                            if (data.message) {
                                $('#error-message').text(data.message);
                                $('#message').addClass('is-invalid');
                            }
                            if (data.status) {
                                $('#error-status').text(data.status);
                                $('#status').addClass('is-invalid');
                            }
                        } else {
                            alertify.success(data.success);
                            setTimeout(function() {
                                window.location.href = "{{ url('superadmin/enquiry-table') }}";
                            }, 1000);
                        }
                    },
                    error: function() {
                        $('#submit').attr('disabled', false);
                        $('#spinner').hide();
                        alertify.error('Something went wrong.');
                    }
                });
            });
        });
    </script>
@endpush
