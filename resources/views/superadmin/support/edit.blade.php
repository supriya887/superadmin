@extends('superadmin.layouts.app')

@section('content')
    <div class="container-fluid px-0">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Edit Ticket</h4>
                    <ol class="breadcrumb m-0 py-0">
                        <li class="breadcrumb-item"><a href="{{ route('superadmin.support.index') }}">Support</a></li>
                        <li class="breadcrumb-item active">Edit Ticket</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <form action="{{ route('superadmin.support.update', $ticket->id) }}" method="POST" autocomplete="off">
                        @csrf
                        @method('PUT')
                        <div class="card-body py-4 px-4">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="form-group mb-3">
                                <label for="requester" class="form-label">Requester Name <span class="text-danger">*</span></label>
                                <input type="text" name="requester" id="requester" 
                                    class="form-control @error('requester') is-invalid @enderror" 
                                    value="{{ old('requester', $ticket->requester) }}" required>
                                @error('requester')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="email" 
                                    class="form-control @error('email') is-invalid @enderror" 
                                    value="{{ old('email', $ticket->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="subject" class="form-label">Ticket Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" id="subject" 
                                    class="form-control @error('subject') is-invalid @enderror" 
                                    value="{{ old('subject', $ticket->subject) }}" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                @php
                                    $descriptionText = '';
                                    if ($ticket->activities) {
                                        $createdActivity = $ticket->activities->firstWhere('type', 'created');
                                        if ($createdActivity && !empty($createdActivity->message)) {
                                            $descriptionText = $createdActivity->message;
                                        }
                                    }
                                @endphp
                                <textarea name="description" id="description" 
                                    class="form-control @error('description') is-invalid @enderror" 
                                    rows="5" required>{{ old('description', $descriptionText) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Use DIV wrappers to protect dropdowns -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <div class="priority-wrapper" style="position: relative;">
                                        <select name="priority" id="priority" class="form-control" required style="display: block !important; width: 100% !important; padding: 8px 12px !important;">
                                            <option value="Low" {{ ($ticket->priority == 'Low') ? 'selected' : '' }}>Low</option>
                                            <option value="Medium" {{ ($ticket->priority == 'Medium') ? 'selected' : '' }}>Medium</option>
                                            <option value="High" {{ ($ticket->priority == 'High') ? 'selected' : '' }}>High</option>
                                            <option value="Urgent" {{ ($ticket->priority == 'Urgent') ? 'selected' : '' }}>Urgent</option>
                                        </select>
                                    </div>
                                    @error('priority')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <div class="status-wrapper" style="position: relative;">
                                        <select name="status" id="status" class="form-control" required style="display: block !important; width: 100% !important; padding: 8px 12px !important;">
                                            <option value="Pending" {{ ($ticket->status == 'Pending') ? 'selected' : '' }}>Pending</option>
                                            <option value="In Progress" {{ ($ticket->status == 'In Progress') ? 'selected' : '' }}>In Progress</option>
                                            <option value="Resolved" {{ ($ticket->status == 'Resolved') ? 'selected' : '' }}>Resolved</option>
                                            <option value="Closed" {{ ($ticket->status == 'Closed') ? 'selected' : '' }}>Closed</option>
                                        </select>
                                    </div>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="tags" class="form-label">Tags (comma-separated)</label>
                                <input type="text" name="tags" id="tags" 
                                    class="form-control @error('tags') is-invalid @enderror" 
                                    value="{{ old('tags', $ticket->tags) }}">
                                @error('tags')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            @if(isset($ticket->partner_id) && $ticket->partner_id)
                            <div class="form-group mb-3">
                                <label class="form-label">Partner</label>
                                <input type="text" class="form-control" value="{{ $ticket->partner->name ?? 'N/A' }}" disabled>
                                <small class="text-muted">Partner cannot be changed after ticket creation</small>
                            </div>
                            @endif

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i> Update Ticket
                                </button>
                                <a href="{{ route('superadmin.support.details', $ticket->id) }}" class="btn btn-outline-secondary ms-2">
                                    <i class="ti ti-arrow-left me-1"></i> Cancel
                                </a>
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
// Aggressive fix - runs continuously to keep dropdowns visible
(function() {
    function fixDropdowns() {
        var selects = document.querySelectorAll('select');
        selects.forEach(function(select) {
            // Skip if already fixed
            if (select.hasAttribute('data-fixed')) return;
            
            // Check if hidden by Select2
            if (select.classList.contains('select2-hidden-accessible') || 
                select.style.display === 'none' ||
                select.offsetParent === null) {
                
                // Restore the select
                select.classList.remove('select2-hidden-accessible');
                select.style.display = 'block';
                select.style.visibility = 'visible';
                select.style.opacity = '1';
                select.style.position = 'relative';
                select.style.zIndex = 'auto';
                
                // Remove any Select2 container
                var nextSibling = select.nextElementSibling;
                if (nextSibling && nextSibling.classList && nextSibling.classList.contains('select2-container')) {
                    nextSibling.remove();
                }
                
                // Mark as fixed
                select.setAttribute('data-fixed', 'true');
                console.log('Fixed dropdown:', select.id || select.name, 'Value:', select.value);
            }
        });
    }
    
    // Run immediately
    fixDropdowns();
    
    // Run after a short delay
    setTimeout(fixDropdowns, 100);
    setTimeout(fixDropdowns, 500);
    setTimeout(fixDropdowns, 1000);
    
    // Watch for DOM changes
    var observer = new MutationObserver(function(mutations) {
        fixDropdowns();
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style']
    });
    
    // Also run on window load
    window.addEventListener('load', function() {
        setTimeout(fixDropdowns, 100);
    });
})();
</script>
@endpush