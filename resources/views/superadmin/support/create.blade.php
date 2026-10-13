@extends('superadmin.layouts.app')

@php
    $isEdit = !empty($isEdit) && !empty($ticket);
    $pageTitle = $isEdit ? 'Edit Ticket' : 'Create New Ticket';
    $formAction = $isEdit
        ? route('superadmin.support.update', $ticket->id)
        : route('superadmin.support.store');
    $cancelUrl = $isEdit
        ? route('superadmin.support.details', $ticket->id)
        : route('superadmin.support.index');
    $workflowStatuses = $workflowStatuses ?? ['Open', 'In Progress', 'Waiting for Customer', 'Resolved', 'Closed', 'Reopened'];
    $currentStatus = $currentStatus ?? ($isEdit ? ($ticket->status ?? 'Open') : null);

    $descriptionText = '';
    if ($isEdit && isset($ticket->activities)) {
        $createdActivity = $ticket->activities->firstWhere('type', 'created');
        if ($createdActivity && !empty($createdActivity->message)) {
            $descriptionText = $createdActivity->message;
        }
    }

    $tagsValue = '';
    if ($isEdit) {
        $tagsRaw = $ticket->tags ?? null;
        if (is_array($tagsRaw)) {
            $tagsValue = implode(', ', $tagsRaw);
        } elseif (is_string($tagsRaw)) {
            $tagsValue = $tagsRaw;
        }
    }
@endphp

@section('content')
    <div class="container-fluid px-0">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">{{ $pageTitle }}</h4>
                    <ol class="breadcrumb m-0 py-0">
                        <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('superadmin.support.index') }}">Support</a></li>
                        <li class="breadcrumb-item active">{{ $pageTitle }}</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <form id="ticketForm" action="{{ $formAction }}" method="POST" enctype="multipart/form-data" autocomplete="off">
                        @csrf
                        @if($isEdit)
                            @method('PUT')
                        @endif
                        <div class="card-body py-4 px-4">

                            @if(!$isEdit)
                                <div class="form-group mb-3">
                                    <label for="partner_id" class="form-label">Partner <span class="text-danger">*</span></label>
                                    <select name="partner_id" id="partner_id" class="form-select">
                                        <option value="">— Select partner —</option>
                                        @foreach ($partners as $partner)
                                            <option value="{{ $partner->id }}"
                                                data-name="{{ $partner->name }}"
                                                data-email="{{ $partner->email }}"
                                                {{ old('partner_id') == $partner->id ? 'selected' : '' }}>
                                                {{ $partner->name }} ({{ $partner->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-danger fs-xs" id="error-partner_id" style="display:none;"></span>
                                </div>
                            @else
                                <div class="form-group mb-3">
                                    <label class="form-label">Partner</label>
                                    <input type="text" class="form-control" value="{{ $partnerName ?? ('#' . ($ticket->partner_id ?? 'N/A')) }}" disabled>
                                    <small class="text-muted">Partner cannot be changed after ticket creation</small>
                                </div>
                            @endif

                            <div class="form-group mb-3">
                                <label for="requester" class="form-label">Requester Name <span class="text-danger">*</span></label>
                                <input type="text" name="requester" id="requester" class="form-control"
                                    placeholder="Enter requester name"
                                    value="{{ old('requester', $isEdit ? $ticket->requester : '') }}">
                                <span class="text-danger fs-xs" id="error-requester" style="display:none;"></span>
                            </div>

                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" class="form-control"
                                    placeholder="you&#64;example.com"
                                    value="{{ old('email', $isEdit ? $ticket->email : '') }}">
                                <span class="text-danger fs-xs" id="error-email" style="display:none;"></span>
                            </div>

                            <div class="form-group mb-3">
                                <label for="subject" class="form-label">Ticket Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" id="subject" class="form-control"
                                    placeholder="Brief summary of the issue"
                                    value="{{ old('subject', $isEdit ? $ticket->subject : '') }}">
                                <span class="text-danger fs-xs" id="error-subject" style="display:none;"></span>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select name="priority" id="priority" class="form-select">
                                        <option value="" disabled @selected(!old('priority', $isEdit ? $ticket->priority : null))>Choose...</option>
                                        @foreach (['Low', 'Medium', 'High', 'Urgent'] as $priority)
                                            <option value="{{ $priority }}"
                                                @selected(old('priority', $isEdit ? $ticket->priority : null) === $priority)>
                                                {{ $priority }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-danger fs-xs" id="error-priority" style="display:none;"></span>
                                </div>
                                <div class="col-md-6">
                                    <label for="tickit_status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select name="tickit_status" id="tickit_status" class="form-select">
                                        <option value="" disabled @selected(!old('tickit_status', $currentStatus))>Choose...</option>
                                        @foreach ($workflowStatuses as $status)
                                            <option value="{{ $status }}"
                                                @selected(old('tickit_status', $currentStatus) === $status)>
                                                {{ $status }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-danger fs-xs" id="error-tickit_status" style="display:none;"></span>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="tags" class="form-label">Tags (comma-separated)</label>
                                <input type="text" name="tags" id="tags" class="form-control"
                                    placeholder="e.g. login, error, payment"
                                    value="{{ old('tags', $tagsValue) }}">
                                <span class="text-danger fs-xs" id="error-tags" style="display:none;"></span>
                            </div>

                            <div class="form-group mb-3">
                                <label for="description" class="form-label">
                                    Description
                                    @if(!$isEdit)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <textarea name="description" id="description" class="form-control" rows="5"
                                    placeholder="Describe the issue in detail...">{{ old('description', $descriptionText) }}</textarea>
                                <span class="text-danger fs-xs" id="error-description" style="display:none;"></span>
                            </div>

                            @if(!$isEdit)
                                <div class="form-group mb-4">
                                    <label for="attachments" class="form-label">Attachments</label>
                                    <input type="file" name="attachments[]" id="attachments" class="form-control" multiple
                                        accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                                    <span class="text-danger fs-xs" id="error-attachments" style="display:none;"></span>
                                    <small class="text-muted">Up to 5 files, max 5 MB each.</small>
                                    <div id="attachmentPreview" class="mt-3"></div>
                                </div>
                            @endif

                            <div class="text-center">
                                <button type="submit" id="submitBtn" class="btn btn-primary">
                                    <span id="spinner" class="spinner-border spinner-border-sm me-1" style="display:none;" role="status"></span>
                                    {{ $isEdit ? 'Update Ticket' : 'Submit Ticket' }}
                                </button>
                                <a href="{{ $cancelUrl }}" class="btn btn-outline-dark ms-2">Cancel</a>
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
$(function () {
    @if(!$isEdit)
    if (typeof $.fn.select2 === 'function') {
        $('#partner_id').select2({ placeholder: '— Select partner —', allowClear: true, width: '100%' });
    }

    $('#partner_id').on('change', function () {
        var $opt = $(this).find('option:selected');
        if ($opt.val()) {
            $('#requester').val($opt.data('name') || '');
            $('#email').val($opt.data('email') || '');
        }
    });

    $('#attachments').on('change', function () {
        var preview = $('#attachmentPreview').empty();
        if (!this.files.length) return;
        var list = $('<div class="list-group"></div>');
        for (var i = 0; i < this.files.length; i++) {
            var f = this.files[i];
            list.append(
                '<div class="list-group-item"><strong>' + f.name + '</strong> ' +
                '<small class="text-muted">(' + (f.size / 1024).toFixed(2) + ' KB)</small></div>'
            );
        }
        preview.append(list);
    });
    @endif

    $('#ticketForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#submitBtn');
        $btn.attr('disabled', true);
        $('#spinner').show();
        $('.text-danger.fs-xs').text('').hide();
        $('.form-control, .form-select').removeClass('is-invalid');

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                $btn.attr('disabled', false);
                $('#spinner').hide();
                if (res.error) {
                    if (res.messages) {
                        $.each(res.messages, function (key, value) {
                            var msg = Array.isArray(value) ? value[0] : value;
                            var k = String(key).split('.')[0];
                            $('#error-' + k).text(msg).show();
                            $('#' + k).addClass('is-invalid');
                        });
                    } else if (res.message) {
                        alertify.error(res.message);
                    }
                    return;
                }
                if (res.success) {
                    alertify.success(res.success);
                    if (res.redirect) {
                        setTimeout(function () { window.location.href = res.redirect; }, 1000);
                    }
                }
            },
            error: function (xhr) {
                $btn.attr('disabled', false);
                $('#spinner').hide();
                alertify.error('An error occurred: ' + xhr.statusText);
            }
        });
    });

    $('#ticketForm input, #ticketForm select, #ticketForm textarea').on('input change', function () {
        var name = $(this).attr('name');
        if (!name) return;
        $('#error-' + name.replace('[]', '')).text('').hide();
        $(this).removeClass('is-invalid');
    });
});
</script>
@endpush
