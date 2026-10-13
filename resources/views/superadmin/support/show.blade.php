@extends('superadmin.layouts.app')

@section('content')
<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="page-header mt-4 mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Ticket Details</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('support.index') }}">Support Tickets</a></li>
                        <li class="breadcrumb-item active">{{ $ticket->ticket_no }}</li>
                    </ol>
                </nav>
            </div>
            <div class="col-auto">
                @if($ticket->status != 'closed')
                <form method="POST" action="{{ route('superadmin.support.close', $ticket->id) }}" class="d-inline" onsubmit="return confirm('Close this ticket?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-danger">
                        <i class="ti ti-circle-x"></i> Close Ticket
                    </button>
                </form>
                @endif
                <a href="{{ route('support.index') }}" class="btn btn-secondary">
                    <i class="ti ti-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Ticket Header Card -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="card-title mb-1">{{ $ticket->subject }}</h4>
                            <p class="text-muted small mb-0">
                                <i class="ti ti-ticket"></i> {{ $ticket->ticket_no }}
                            </p>
                        </div>
                        <div>
                            <span class="badge fs-6 p-2 
                                @if($ticket->status == 'open') bg-success
                                @elseif($ticket->status == 'pending') bg-warning text-dark
                                @elseif($ticket->status == 'closed') bg-danger
                                @else bg-secondary @endif">
                                <i class="ti ti-circle"></i> {{ ucfirst($ticket->status) }}
                            </span>
                            <span class="badge fs-6 p-2 ms-1
                                @if($ticket->priority == 'High' || $ticket->priority == 'Urgent') bg-danger
                                @elseif($ticket->priority == 'Medium') bg-warning text-dark
                                @else bg-info @endif">
                                <i class="ti ti-flag"></i> {{ $ticket->priority }}
                            </span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-light rounded-circle p-2 me-2">
                                    <i class="ti ti-user text-primary"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Requester</small>
                                    <strong>{{ $ticket->requester ?: 'N/A' }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-light rounded-circle p-2 me-2">
                                    <i class="ti ti-mail text-primary"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Email</small>
                                    <strong>{{ $ticket->email ?: 'N/A' }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($ticket->tags)
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Tags</small>
                        @foreach(explode(',', $ticket->tags) as $tag)
                            <span class="badge bg-light text-dark me-1 p-2">
                                <i class="ti ti-tag"></i> {{ trim($tag) }}
                            </span>
                        @endforeach
                    </div>
                    @endif

                    @if($ticket->domain || $ticket->service)
                    <hr>
                    <div class="row g-3">
                        @if($ticket->domain)
                        <div class="col-md-6">
                            <small class="text-muted d-block">Domain</small>
                            <strong>{{ $ticket->domain }}</strong>
                        </div>
                        @endif
                        @if($ticket->service)
                        <div class="col-md-6">
                            <small class="text-muted d-block">Service</small>
                            <strong>{{ $ticket->service }}</strong>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <!-- Description Card - Get from first activity with type 'created' -->
            @php
                $descriptionActivity = $ticket->activities->where('type', 'created')->first();
            @endphp
            
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="ti ti-description"></i> Description
                    </h5>
                </div>
                <div class="card-body">
                    @if($descriptionActivity && $descriptionActivity->message)
                        <div class="p-3 bg-light rounded">
                            <p class="mb-0">{{ nl2br(e($descriptionActivity->message)) }}</p>
                        </div>
                    @elseif($ticket->description)
                        <div class="p-3 bg-light rounded">
                            <p class="mb-0">{{ nl2br(e($ticket->description)) }}</p>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="ti ti-message-circle fs-1"></i>
                            <p class="mb-0 mt-2">No description provided</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Attachments Card -->
            @if($ticket->attachments && $ticket->attachments->count() > 0)
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="ti ti-paperclip"></i> Attachments 
                        ({{ $ticket->attachments->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($ticket->attachments as $attachment)
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="mb-2">
                                            <i class="ti ti-file fs-4 text-primary"></i>
                                        </div>
                                        <strong>{{ $attachment->filename }}</strong>
                                        <div>
                                            <small class="text-muted">
                                                {{ round($attachment->file_size / 1024, 2) }} KB
                                            </small>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="{{ route('superadmin.support.attachment.download', $attachment->id) }}" 
                                           class="btn btn-sm btn-outline-primary" title="Download">
                                            <i class="ti ti-download"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger delete-attachment" 
                                                data-id="{{ $attachment->id }}"
                                                data-filename="{{ $attachment->filename }}"
                                                title="Delete">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Activities Timeline -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="ti ti-history"></i> Activity Timeline
                    </h5>
                </div>
                <div class="card-body">
                    @if($ticket->activities && $ticket->activities->count() > 0)
                        @foreach($ticket->activities as $activity)
                        <div class="d-flex mb-4">
                            <div class="flex-shrink-0">
                                <div class="avatar rounded-circle 
                                    @if($activity->user_type == 'superadmin') bg-danger
                                    @elseif($activity->user_type == 'partner') bg-info
                                    @else bg-primary @endif
                                    text-white p-2">
                                    <i class="ti ti-user"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong>
                                            @if($activity->user_type == 'superadmin') Super Admin
                                            @elseif($activity->user_type == 'partner') Partner
                                            @else Admin
                                            @endif
                                        </strong>
                                        @if($activity->type == 'system')
                                            <span class="badge bg-light text-dark ms-1">System</span>
                                        @elseif($activity->type == 'created')
                                            <span class="badge bg-success ms-1">Created</span>
                                        @elseif($activity->type == 'closed')
                                            <span class="badge bg-danger ms-1">Closed</span>
                                        @elseif($activity->type == 'reply')
                                            <span class="badge bg-info ms-1">Reply</span>
                                        @endif
                                    </div>
                                    <small class="text-muted">
                                        {{ $activity->created_at->format('d M Y, h:i A') }}
                                    </small>
                                </div>
                                <p class="mb-0">{{ nl2br(e($activity->message)) }}</p>
                                
                                @if($activity->attachments && $activity->attachments->count() > 0)
                                <div class="mt-2">
                                    @foreach($activity->attachments as $att)
                                        <a href="{{ route('superadmin.support.attachment.download', $att->id) }}" 
                                           class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="ti ti-paperclip"></i> {{ $att->filename }}
                                        </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                        @if(!$loop->last)
                            <hr class="my-3">
                        @endif
                        @endforeach
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="ti ti-message-circle fs-1"></i>
                            <p class="mb-0 mt-2">No activities yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Reply Form -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="ti ti-send"></i> Reply to Ticket
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('superadmin.support.activity', $ticket->id) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message</label>
                            <textarea name="message" rows="5" 
                                class="form-control @error('message') is-invalid @enderror" 
                                placeholder="Type your reply here..." required>{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="ti ti-paperclip"></i> Attachments
                            </label>
                            <input type="file" name="attachments[]" id="attachments" 
                                   class="form-control @error('attachments') is-invalid @enderror" 
                                   multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                            <small class="text-muted">Max 5 files, 5 MB each</small>
                            <div id="attachmentPreview" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Update Status</label>
                            <select name="status" class="form-select">
                                <option value="">— No change —</option>
                                @foreach(['open', 'pending', 'in_progress', 'resolved', 'closed'] as $s)
                                    <option value="{{ $s }}" @selected(old('status') === $s)>
                                        {{ ucfirst(str_replace('_', ' ', $s)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-send"></i> Submit Reply
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Ticket Information Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="ti ti-info-circle"></i> Ticket Info
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td class="ps-0"><strong>Created:</strong></td>
                            <td class="pe-0">{{ $ticket->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                        <tr>
                            <td class="ps-0"><strong>Last Updated:</strong></td>
                            <td class="pe-0">{{ $ticket->updated_at->format('d M Y, h:i A') }}</td>
                        </tr>
                        <tr>
                            <td class="ps-0"><strong>Priority:</strong></td>
                            <td class="pe-0">
                                <span class="badge 
                                    @if($ticket->priority == 'High') bg-danger
                                    @elseif($ticket->priority == 'Medium') bg-warning
                                    @else bg-info @endif">
                                    {{ $ticket->priority }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-0"><strong>Status:</strong></td>
                            <td class="pe-0">{{ ucfirst($ticket->status) }}</td>
                        </tr>
                        @if($ticket->partner)
                        <tr>
                            <td class="ps-0"><strong>Partner:</strong></td>
                            <td class="pe-0">{{ $ticket->partner->name }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Attachment preview
    $('#attachments').on('change', function() {
        var preview = $('#attachmentPreview');
        preview.empty();
        
        if (this.files.length > 0 && this.files.length <= 5) {
            var list = $('<div class="list-group"></div>');
            for (var i = 0; i < this.files.length; i++) {
                var file = this.files[i];
                var fileSize = (file.size / 1024).toFixed(2) + ' KB';
                list.append(`
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="ti ti-file me-2"></i>
                            <strong>${file.name}</strong>
                            <small class="text-muted ms-2">(${fileSize})</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-attachment" data-index="${i}">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                `);
            }
            preview.append(list);
            
            $('.remove-attachment').on('click', function() {
                var dt = new DataTransfer();
                var files = $('#attachments')[0].files;
                var index = $(this).data('index');
                
                for (var i = 0; i < files.length; i++) {
                    if (i !== index) dt.items.add(files[i]);
                }
                
                $('#attachments')[0].files = dt.files;
                $(this).closest('.list-group-item').remove();
                
                if ($('#attachments')[0].files.length === 0) {
                    preview.empty();
                }
            });
        } else if (this.files.length > 5) {
            alert('Maximum 5 files allowed');
            $(this).val('');
            preview.empty();
        }
    });
    
    // Delete attachment
    $('.delete-attachment').on('click', function() {
        var id = $(this).data('id');
        var filename = $(this).data('filename');
        
        if (confirm('Delete "' + filename + '"?')) {
            $.ajax({
                url: '{{ route("superadmin.support.attachment.delete", "") }}/' + id,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error deleting attachment');
                    }
                },
                error: function() {
                    alert('Error deleting attachment');
                }
            });
        }
    });
});
</script>
@endpush
@endsection