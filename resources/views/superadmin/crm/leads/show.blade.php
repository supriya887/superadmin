@extends('superadmin.layouts.crm')
@php
    $documents = $lead->document ?? [];
    if (!is_array($documents)) {
        $documents = [$documents];
    }
    $documents = array_filter($documents);

    $categoryClass = strtolower($lead->lead_category ?? '');
    $assignedInitial = strtoupper(substr($lead->assigned_to ?: 'N', 0, 1));

    $addressParts = array_filter([
        $lead->mailing_address,
        $lead->city,
        $lead->state && $lead->pincode ? $lead->state . '-' . $lead->pincode : ($lead->state ?: $lead->pincode),
        $lead->country,
    ]);
    $isExistingBusiness = ($lead->business_type ?? '') === 'existing';
    $infoServices = $lead->services->filter(fn ($s) => $s->isCompetitorInfo())->values();
    $quoteServices = $lead->services->reject(fn ($s) => $s->isCompetitorInfo())->values();

    $statusLabel = $lead->lead_status ?: '-';
    $statusKey = strtolower((string) $statusLabel);
    if (in_array($statusKey, ['quote sent', 'quotation sent', 'quote send'], true)) {
        $statusLabel = 'Quote Send';
        $statusKey = 'quote send';
    }
    $statusBadgeClass = match ($statusKey) {
        'new' => 'new',
        'contacted' => 'contacted',
        'qualified' => 'qualified',
        'negotiation', 'in process' => 'negotiation',
        'quotation sent', 'quote sent', 'quote send', 'quote approved' => 'quotation-sent',
        'won', 'deal done' => 'won',
        'lost', 'not interested' => 'lost',
        'nurturing' => 'nurturing',
        default => 'new',
    };
    $dialCodes = \App\Models\Lead::orderedDialCodes();
@endphp
@section('content')
<div class="crm-leads-page crm-leads-show">

<div class="page-header">

    <div>

        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a>
                <span>/</span>
                <a href="{{ route('crm.leads') }}">Leads</a>
                <span>/</span>
            </p>
        </div>

        <h2>Lead Detail</h2>

    </div>

    <div class="back">

        <a href="{{ route('crm.leads') }}" class="back-btn">
            <i data-lucide="arrow-left"></i>
            <span>Back</span>
        </a>

    </div>

</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="customer-dashboard">

    <div class="order-dashboard-grid">


        <main class="customer-content">


            <section class="common-table-section lead-show-primary">

                <div class="common-table-card p-16">

                    <div class="lead-top-meta">

                        <span>
                            Status :
                            <span class="badge {{ $statusBadgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </span>

                        <span>
                            Priority :
                            <div class="{{ $categoryClass === 'hot' ? 'status-hot' : 'status-interested' }}">
                                {{ $lead->lead_category ?: '-' }}
                            </div>
                        </span>

                        <span>
                            Bifurcation :
                            <div class="status-interested">
                                {{ $lead->lead_bifurcation ?: '-' }}
                            </div>
                        </span>

                        <span>
                            Business :
                            <div class="status-interested">
                                @if(($lead->business_type ?? '') === 'existing')
                                    Existing 
                                @elseif(($lead->business_type ?? '') === 'new')
                                    New 
                                @else
                                    -
                                @endif
                            </div>
                        </span>

                        <span>
                            Created On :
                            <div>
                                {{ $lead->created_at ? $lead->created_at->format('d M, Y') : '-' }}
                            </div>
                        </span>

                    </div>


                    <div class="lead-company-header">

                        <h3>
                            {{ $lead->company_name ?: '-' }}
                        </h3>

                        <button type="button" class="edit-lead-btn" id="openLeadEdit">
                            <i data-lucide="pencil"></i>
                            Add / Edit Details
                        </button>

                    </div>


                    <div class="lead-info-grid">

                        <div class="info-item">
                            <label>Full Name</label>
                            <p>{{ trim(($lead->salutation ? $lead->salutation . '. ' : '') . ($lead->name ?: '')) ?: '-' }}</p>
                        </div>

                        <div class="info-item">
                            <label>Contact No.</label>
                            <p>{{ $lead->mobile ? ('+' . ($lead->mobile_code ?: '91') . ' ' . $lead->mobile) : '-' }}</p>
                        </div>

                        <div class="info-item">
                            <label>Email</label>
                            <p>{{ $lead->email ?: '-' }}</p>
                        </div>

                        @if($lead->designation)
                        <div class="info-item">
                            <label>Designation</label>
                            <p>{{ $lead->designation }}</p>
                        </div>
                        @endif

                        <div class="info-item">
                            <label>Lead Bifurcation</label>
                            <p>{{ $lead->lead_bifurcation ?: '-' }}</p>
                        </div>

                        @if($lead->industry)
                        <div class="info-item">
                            <label>Industry</label>
                            <p>{{ $lead->industry }}</p>
                        </div>
                        @endif

                        @if($lead->no_of_employees)
                        <div class="info-item">
                            <label>Number of Employee</label>
                            <p>{{ $lead->no_of_employees }}</p>
                        </div>
                        @endif

                        @if($lead->gstin)
                        <div class="info-item">
                            <label>GSTIN</label>
                            <p>{{ $lead->gstin }}</p>
                        </div>
                        @endif

                        @if(count($addressParts))
                        <div class="info-item">
                            <label>Address</label>
                            <p>{{ implode(', ', $addressParts) }}</p>
                        </div>
                        @endif

                        @if($lead->domain || $isExistingBusiness)
                        <div class="info-item">
                            <label>Domain</label>
                            <p>
                                @if($lead->domain)
                                    {{ $lead->domain }}
                                    @if($lead->domain_type === 'existing')
                                        (already have)
                                    @elseif($lead->domain_type === 'new')
                                        (new)
                                        @if($lead->domain_period)
                                            — {{ $lead->domain_period }}
                                        @endif
                                        @if($lead->domain_rate)
                                            — ₹ {{ number_format((float) $lead->domain_rate, 2) }}
                                        @endif
                                    @endif
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        @endif

                        @if($lead->domain_type === 'new' && $lead->sac)
                        <div class="info-item">
                            <label>SAC</label>
                            <p>{{ $lead->sac }}</p>
                        </div>
                        @endif

                        @if(($lead->domain_type ?? '') === 'existing')
                        <div class="info-item">
                            <label>Renewal Date</label>
                            <p>{{ $lead->domain_renewal_date ? $lead->domain_renewal_date->format('d-m-Y') : '-' }}</p>
                        </div>
                        @endif

                        @if($lead->domain_type === 'new' && $lead->domain_start)
                        <div class="info-item">
                            <label>Domain Start</label>
                            <p>{{ $lead->domain_start->format('d-m-Y') }}</p>
                        </div>
                        @endif

                        @if($lead->domain_type === 'new' && $lead->domain_end)
                        <div class="info-item">
                            <label>Domain End</label>
                            <p>{{ $lead->domain_end->format('d-m-Y') }}</p>
                        </div>
                        @endif

                    </div>

                </div>

            </section>


            <section class="common-table-section lead-show-primary">

                <div class="common-table-card p-16">

                    <div class="card-header">

                        <h3>
                            CONTACT INFORMATION
                        </h3>

                        <a href="#" class="add-credit-btn" id="openAddContact">
                            <i data-lucide="plus"></i>
                            Add Contact
                        </a>

                    </div>

                    <div class="contact-information-section">
                        @forelse($lead->contacts as $index => $contact)
                            <div class="lead-contact-card">

                                <h4>Contact Person {{ $index + 1 }}</h4>

                                <div class="contact-info-grid">

                                    <div>
                                        <label>Full Name</label>
                                        <p>{{ $contact->name ?: '-' }}</p>
                                    </div>

                                    @if($contact->designation)
                                    <div>
                                        <label>Designation</label>
                                        <p>{{ $contact->designation }}</p>
                                    </div>
                                    @endif

                                    @if($contact->email)
                                    <div>
                                        <label>Email</label>
                                        <p>{{ $contact->email }}</p>
                                    </div>
                                    @endif

                                    @if($contact->mobile)
                                    <div>
                                        <label>Contact No.</label>
                                        <p>{{ $contact->mobile }}</p>
                                    </div>
                                    @endif

                                </div>
                                <div class="service-actions action-absolute">
                                    <button type="button" class="edit-contact-btn"
                                        data-action="{{ route('crm.leads.contacts.update', [$lead, $contact]) }}"
                                        data-name="{{ $contact->name }}"
                                        data-designation="{{ $contact->designation }}"
                                        data-email="{{ $contact->email }}"
                                        data-mobile="{{ $contact->mobile }}">
                                        <i data-lucide="pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('crm.leads.contacts.destroy', [$lead, $contact]) }}" onsubmit="return confirm('Remove this contact?');">
                                        @csrf
                                        <button type="submit" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </form>
                                </div>

                            </div>
                        @empty
                            <p class="lead-empty-note">No additional contacts.</p>
                        @endforelse
                    </div>
                </div>

            </section>


            <section class="common-table-section lead-show-full">
                <div class="common-table-card p-16">
                    <div class="card-header">
                        <h3>CURRENT SERVICE (INFO)</h3>
                        <a href="#" class="add-credit-btn" id="openAddInfoService">
                            <i data-lucide="plus"></i>
                            Add Current Service
                        </a>
                    </div>
                    @forelse($infoServices as $service)
                        @php
                            $providerName = optional($service->provider)->name ?? $service->getAttributes()['provider'] ?? '-';
                            $planName = optional($service->plan)->name ?? optional($service->plan)->title ?? ($service->getAttributes()['plan'] ?? '-');
                            $displayRate = $service->rate;
                        @endphp
                        <div class="service-required-card">
                            <div class="service-item">
                                <label>Domain</label>
                                <p>{{ $service->domain ?: '-' }}</p>
                            </div>
                            <div class="service-item">
                                <label>Service</label>
                                <p>{{ $providerName }}</p>
                            </div>
                            <div class="service-item">
                                <label>Plan Name</label>
                                <p>{{ $planName }}</p>
                            </div>
                            <div class="service-item">
                                <label>Current Price</label>
                                <p>{{ $displayRate !== null && $displayRate !== '' ? '₹ ' . number_format((float) $displayRate, 2) : '-' }}</p>
                            </div>
                            <div class="service-item">
                                <label>Renewal Date</label>
                                <p>{{ $service->renewal_date ? $service->renewal_date->format('d-m-Y') : '-' }}</p>
                            </div>
                            <div class="service-item">
                                <label>No of Users</label>
                                <p>{{ $service->quantity ?? '-' }}</p>
                            </div>
                            <div class="service-actions action-absolute">
                                <button type="button" onclick="if(confirm('Remove this info?')){ document.getElementById('delete-service-{{ $service->id }}').submit(); }">
                                    <i data-lucide="trash-2"></i>
                                </button>
                                <button type="button"
                                    class="edit-info-service-btn"
                                    data-action="{{ route('crm.leads.services.update', [$lead, $service]) }}"
                                    data-provider="{{ $service->provider_id }}"
                                    data-plan="{{ $service->plan_id }}"
                                    data-sku="{{ $service->sku }}"
                                    data-quantity="{{ $service->quantity }}"
                                    data-rate="{{ $service->rate }}"
                                    data-renewal="{{ $service->renewal_date ? $service->renewal_date->format('Y-m-d') : '' }}"
                                    data-domain="{{ $service->domain }}">
                                    <i data-lucide="pencil"></i>
                                </button>
                            </div>
                            <form id="delete-service-{{ $service->id }}" method="POST" action="{{ route('crm.leads.services.destroy', [$lead, $service]) }}">
                                @csrf
                            </form>
                        </div>
                    @empty
                        <p class="lead-empty-note">No current vendor service yet. Add it here for Existing Business.</p>
                    @endforelse
                </div>
            </section>

            <section class="common-table-section lead-show-full">

                <div class="common-table-card p-16">

                    <div class="card-header">

                        <h3>
                            OUR SERVICE
                        </h3>

                        <a href="#" class="add-credit-btn" id="openAddService">
                            <i data-lucide="plus"></i>
                            Add Service
                        </a>

                    </div>

                    @forelse($quoteServices as $service)
                        @php
                            $providerName = optional($service->provider)->name ?? $service->getAttributes()['provider'] ?? '-';
                            $planName = optional($service->plan)->name ?? optional($service->plan)->title ?? ($service->getAttributes()['plan'] ?? '-');
                            $displayRate = $service->rate;
                        @endphp
                        <div class="service-required-card">

                            <div class="service-item">

                                <label>Service</label>
                                <p>{{ $providerName }}</p>

                            </div>


                            <div class="service-item">

                                <label>Plan Name</label>
                                <p>{{ $planName }}</p>

                            </div>


                            <div class="service-item">

                                <label>Quot Period</label>
                                <p>{{ $service->quote_period ?: '-' }}</p>

                            </div>


                            <div class="service-item">

                                <label>Rate</label>
                                <p>{{ $displayRate !== null && $displayRate !== '' ? '₹ ' . number_format((float) $displayRate, 2) : '-' }}</p>

                            </div>


                            <div class="service-item">

                                <label>No of Users</label>
                                <p>{{ $service->quantity ?? '-' }}</p>

                            </div>


                            <div class="service-actions action-absolute">

                                <button type="button" onclick="if(confirm('Remove this service?')){ document.getElementById('delete-service-{{ $service->id }}').submit(); }">
                                    <i data-lucide="trash-2"></i>
                                </button>

                                <button type="button"
                                    class="edit-service-btn"
                                    data-action="{{ route('crm.leads.services.update', [$lead, $service]) }}"
                                    data-provider="{{ $service->provider_id }}"
                                    data-plan="{{ $service->plan_id }}"
                                    data-period="{{ $service->quote_period }}"
                                    data-sku="{{ $service->sku }}"
                                    data-quantity="{{ $service->quantity }}"
                                    data-rate="{{ $service->rate }}">
                                    <i data-lucide="pencil"></i>
                                </button>

                            </div>
                            <form id="delete-service-{{ $service->id }}" method="POST" action="{{ route('crm.leads.services.destroy', [$lead, $service]) }}">
                                @csrf
                            </form>

                        </div>
                    @empty
                        <p class="lead-empty-note">No Tizzy service added yet. Use Add Service to quote.</p>
                    @endforelse


                    <div class="submit-wrap">

                        <a href="{{ route('crm.quotations.create', ['lead_id' => $lead->id]) }}" class="submit-btn">
                            Create Quotation
                        </a>

                    </div>

                </div>

            </section>

            <section class="common-table-section lead-show-full" id="documentsCard">

                <div class="common-table-card p-16">

                    <div class="card-header">

                        <h3>
                            DOCUMENTS
                        </h3>

                        <a href="#" class="add-credit-btn" id="openAddDocument">
                            <i data-lucide="plus"></i>
                            Add Document
                        </a>

                    </div>


                    <div class="download-list">

                        <div class=" download-header ">

                            <div>
                                File Name
                            </div>

                            <div>
                                Attached By
                            </div>

                            <div>
                                Date Added
                            </div>

                            <div class="status-col">
                                Action
                            </div>

                        </div>

                        @forelse($documents as $index => $document)
                            @php
                                $docPath = is_array($document) ? ($document['path'] ?? '') : $document;
                                $docType = is_array($document) ? ($document['type'] ?? '') : '';
                                $documentName = is_array($document) ? ($document['name'] ?? basename($docPath)) : basename((string) $docPath);
                                $documentUrl = $docPath ? asset('storage/' . $docPath) : '#';
                                $extension = strtolower(pathinfo($docPath, PATHINFO_EXTENSION));
                            @endphp
                            <div class="download-row">

                                <div>

                                    <a href="{{ $documentUrl }}" class="download-file" target="_blank" style="white-space:normal; word-break:break-word;">

                                        @if(in_array($extension, ['jpg', 'jpeg', 'png']))
                                            <i data-lucide="file-image"></i>
                                        @else
                                            <i data-lucide="file-text"></i>
                                        @endif

                                        @if($docType)
                                            <strong>{{ $docType }}</strong> —
                                        @endif
                                        {{ $documentName }}

                                    </a>

                                </div>


                                <div>
                                    {{ $lead->assigned_to ?: '-' }}
                                </div>


                                <div>
                                    {{ $lead->updated_at ? $lead->updated_at->format('d/m/Y h:i A') : '-' }}
                                </div>


                                <div class="service-actions">

                                    <button type="button" title="Preview" onclick="window.open('{{ $documentUrl }}', '_blank')">
                                        <i data-lucide="eye"></i>
                                    </button>

                                    <a href="{{ $documentUrl }}" download title="Download">
                                        <i data-lucide="download"></i>
                                    </a>

                                    <form method="POST" action="{{ route('crm.leads.documents.destroy', $lead) }}" onsubmit="return confirm('Remove this document?');">
                                        @csrf
                                        <input type="hidden" name="index" value="{{ $index }}">
                                        <button type="submit" title="Delete">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>

                                </div>

                            </div>
                        @empty
                            <div class="download-row">
                                <div>No documents uploaded.</div>
                            </div>
                        @endforelse

                    </div>

                </div>

            </section>

        </main>


        <aside class="customer-info">


            <section class="common-table-section">

                <div class="common-table-card p-16">

                    <div class="card-header">

                        <h3>
                            SCHEDULE FOLLOW UP
                        </h3>

                    </div>


                    <form method="POST" action="{{ route('crm.leads.followup', $lead) }}" class="followup-form" id="followUpForm" novalidate>
                        @csrf

                        <div class="form-group">
                            <label>Lead Priority</label>
                            <select name="lead_category">
                                <option value="">Select Priority</option>
                                @foreach (($leadPriorities ?? $leadCategories ?? []) as $cat)
                                    <option value="{{ $cat }}" {{ strcasecmp((string) $lead->lead_category, $cat) === 0 ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lead Bifurcation</label>
                            <select name="lead_bifurcation">
                                <option value="">Select Bifurcation</option>
                                @foreach (($leadBifurcations ?? []) as $bif)
                                    <option value="{{ $bif }}" {{ (string) ($lead->lead_bifurcation ?? '') === $bif ? 'selected' : '' }}>{{ $bif }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lead Source</label>
                            <select name="lead_source">
                                <option value="">Select Source</option>
                                @foreach (($leadSources ?? []) as $src)
                                    <option value="{{ $src }}" {{ (string) $lead->lead_source === $src ? 'selected' : '' }}>{{ $src }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Lead Status <span>*</span></label>
                            <select name="lead_status" required>
                                <option value="">Select Status</option>
                                @foreach (($leadStatuses ?? ['New', 'Contacted', 'Qualified', 'Negotiation', 'Quote Send', 'Won', 'Lost', 'Nurturing']) as $st)
                                    <option value="{{ $st }}" {{ in_array((string) $lead->lead_status, $st === 'Quote Send' ? ['Quote Send', 'Quote Sent', 'Quotation Sent'] : [$st], true) ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </select>
                            @error('lead_status')
                                <div style="color:#EF4444; font-size:13px; margin-top:6px;">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Assigned To</label>
                            <select name="assigned_to">
                                <option value="">Select Employee</option>
                                @foreach(($assignees ?? collect()) as $assignee)
                                    <option value="{{ $assignee }}" {{ (string) $lead->assigned_to === (string) $assignee ? 'selected' : '' }}>{{ $assignee }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Follow Up Date & Time</label>
                            <div class="time-input">
                                <input type="datetime-local" name="follow_up_date" value="{{ $lead->follow_up_date ? $lead->follow_up_date->format('Y-m-d\\TH:i') : '' }}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Note</label>
                            <textarea name="follow_up_note">{{ $lead->follow_up_note }}</textarea>
                        </div>

                        <div class="submit-wrap">
                            <button type="submit" class="submit-btn">Save</button>
                        </div>
                    </form>

                </div>

            </section>


            <div class="activity-column" style="width:100%">

                <div class="common-table-header">
                    <h3>Recent Activities</h3>
                    <a href="#" class="view-all">View All</a>
                </div>

                <div class="activity-card">

                    @forelse($history ?? [] as $item)
                        @php
                            $text = $item['text'] ?? '';
                            $icon = 'clock-3';
                            $color = 'blue';
                            if (str_contains($text, 'Quotation')) {
                                $icon = 'file-text';
                                $color = 'orange';
                            } elseif (str_contains($text, 'Follow-up') || str_contains($text, 'Follow-up')) {
                                $icon = 'phone-call';
                                $color = 'blue';
                            } elseif (str_contains($text, 'Service')) {
                                $icon = 'check';
                                $color = 'teal';
                            } elseif (str_contains($text, 'Contact') || str_contains($text, 'Lead created')) {
                                $icon = 'user-round-plus';
                                $color = 'purple';
                            }

                            $label = $text;
                            $highlight = '';
                            if (preg_match('/^(.*?):\s*(.+)$/', $text, $m)) {
                                $label = $m[1];
                                $highlight = $m[2];
                            } elseif (preg_match('/^(Lead created from)\s+(.+)$/i', $text, $m)) {
                                $label = $m[1];
                                $highlight = $m[2];
                            } elseif (preg_match('/^(Follow-up set for)\s+(.+)$/i', $text, $m)) {
                                $label = $m[1];
                                $highlight = $m[2];
                            }
                        @endphp

                        <div class="activity-item">

                            <div class="activity-icon {{ $color }}">
                                <i data-lucide="{{ $icon }}"></i>
                            </div>

                            <div class="activity-content">
                                <p>
                                    {{ $label }}
                                    @if($highlight !== '')
                                        <span>{{ $highlight }}</span>
                                    @endif
                                </p>
                                <small>{{ optional($item['at'])->diffForHumans() }}</small>
                            </div>

                        </div>
                    @empty
                        <div class="activity-item">
                            <div class="activity-icon blue">
                                <i data-lucide="clock-3"></i>
                            </div>
                            <div class="activity-content">
                                <p>No activity yet.</p>
                            </div>
                        </div>
                    @endforelse

                </div>

            </div>


        </aside>

    </div>

</div>

<div class="credit-modal" id="leadDocumentModal">
    <div class="credit-modal-box">
        <button type="button" class="credit-close close-modal">&times;</button>
        <h2>Add Document</h2>
        <form method="POST" action="{{ route('crm.leads.documents.store', $lead) }}" enctype="multipart/form-data" id="leadDocumentForm" class="">
            @csrf
            <div class="lead-doc-card">
                <p class="form-hint doc-help">PDF, JPG, PNG, DOC or DOCX. Max 10 MB each.</p>
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="document_type">
                        <option value="PAN Card">PAN Card</option>
                        <option value="GST Certificate">GST Certificate</option>
                        <option value="Address Proof">Address Proof</option>
                        <option value="Company Registration">Company Registration</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <label class="upload-box" id="leadShowUploadBox">
                    <div class="upload-icon">
                        <i data-lucide="cloud-upload"></i>
                    </div>
                    <p>Click to upload or drag files here</p>
                    <span class="upload-formats">PDF · JPG · PNG · DOC · DOCX</span>
                    <input type="file" name="documents[]" id="leadShowDocumentInput" class="document-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple required>
                </label>
                <div class="doc-file-list" id="leadShowPickedFiles" hidden></div>
            </div>
            <div class="submit-wrap">
                <button type="submit" class="submit-btn">Upload</button>
            </div>
        </form>
    </div>
</div>

<div class="credit-modal" id="leadModal">
    <div class="credit-modal-box credit-modal-wide">
        <button type="button" class="credit-close close-modal">&times;</button>
        <h2>Add / Edit Lead Details</h2>
        <form method="POST" action="{{ route('crm.leads.update', $lead) }}" class="common-form" id="leadDetailsForm" novalidate>
            @csrf
            <div class="form-grid">
                <div class="form-group"><label>Name <span>*</span></label>
                    <div class="phone-group name-title-row">
                        <select name="salutation" class="form-control phone-code-select title-select" required>
                            <option value="">Title</option>
                            @foreach(['Mr', 'Mrs', 'Ms'] as $title)
                                <option value="{{ $title }}" {{ ($lead->salutation ?? '') === $title ? 'selected' : '' }}>{{ $title }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="name" value="{{ $lead->name }}" required>
                    </div>
                </div>
                <div class="form-group"><label>Designation</label>
                    @if (!empty($designations))
                        <select name="designation">
                            <option value="">Select Designation</option>
                            @foreach ($designations as $desig)
                                <option value="{{ $desig }}" {{ (string) $lead->designation === (string) $desig ? 'selected' : '' }}>{{ $desig }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="designation" value="{{ $lead->designation }}">
                    @endif
                </div>
                <div class="form-group"><label>Email <span>*</span></label><input type="email" name="email" value="{{ $lead->email }}" required></div>
                <div class="form-group"><label>Mobile <span>*</span></label>
                    <div class="phone-group">
                        <select name="mobile_code" class="form-control phone-code-select">
                            @foreach($dialCodes as $code => $country)
                                <option value="{{ $code }}" {{ (string) ($lead->mobile_code ?: '91') === (string) $code ? 'selected' : '' }}>+{{ $code }} {{ $country }}</option>
                            @endforeach
                        </select>
                        <input type="tel" name="mobile" value="{{ $lead->mobile }}" required inputmode="numeric" maxlength="15" pattern="[0-9]{6,15}" class="js-mobile-digits">
                    </div>
                </div>
                <div class="form-group"><label>Company Name</label><input type="text" name="company_name" value="{{ $lead->company_name }}"></div>
                <div class="form-group">
                    <label>Business</label>
                    <select name="business_type">
                        <option value="new" {{ ($lead->business_type ?? 'new') === 'new' ? 'selected' : '' }}>New Business</option>
                        <option value="existing" {{ ($lead->business_type ?? '') === 'existing' ? 'selected' : '' }}>Existing Business</option>
                    </select>
                </div>
                <div class="form-group"><label>Industry</label><input type="text" name="industry" value="{{ $lead->industry }}" placeholder="Filled from GSTIN"></div>
                <div class="form-group"><label>No. of Employees</label><input type="number" name="no_of_employees" min="0" value="{{ $lead->no_of_employees }}"></div>
                <div class="form-group"><label>GSTIN</label>
                    <input type="text" name="gstin" id="editGstin" value="{{ $lead->gstin }}" maxlength="15">
                    <div id="editGstStatus" style="font-size:13px; margin-top:6px; font-weight:600;"></div>
                </div>
                <div class="form-group"><label>Assigned To</label>
                    <select name="assigned_to">
                        <option value="">Select Employee</option>
                        @foreach(($assignees ?? collect()) as $assignee)
                            <option value="{{ $assignee }}" {{ (string) $lead->assigned_to === (string) $assignee ? 'selected' : '' }}>{{ $assignee }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Lead Priority</label>
                    <select name="lead_category">
                        <option value="">Select Priority</option>
                        @foreach (($leadPriorities ?? $leadCategories ?? []) as $cat)
                            <option value="{{ $cat }}" {{ strcasecmp((string) $lead->lead_category, $cat) === 0 ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Lead Status <span>*</span></label>
                    <select name="lead_status" required>
                        <option value="">Select Status</option>
                        @foreach (($leadStatuses ?? ['New', 'Contacted', 'Qualified', 'Negotiation', 'Quote Send', 'Won', 'Lost', 'Nurturing']) as $st)
                            <option value="{{ $st }}" {{ in_array((string) $lead->lead_status, $st === 'Quote Send' ? ['Quote Send', 'Quote Sent', 'Quotation Sent'] : [$st], true) ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Lead Bifurcation</label>
                    <select name="lead_bifurcation">
                        <option value="">Select Bifurcation</option>
                        @foreach (($leadBifurcations ?? []) as $bif)
                            <option value="{{ $bif }}" {{ (string) ($lead->lead_bifurcation ?? '') === $bif ? 'selected' : '' }}>{{ $bif }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Lead Source</label>
                    <select name="lead_source">
                        <option value="">Select Source</option>
                        @foreach (($leadSources ?? []) as $src)
                            <option value="{{ $src }}" {{ (string) $lead->lead_source === $src ? 'selected' : '' }}>{{ $src }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group full-width"><label>Mailing Address</label><input type="text" name="mailing_address" value="{{ $lead->mailing_address }}"></div>
                <div class="form-group">
                    <label>Country</label>
                    <select name="country">
                        <option value="">Select Country</option>
                        @foreach (['India', 'United States', 'United Kingdom', 'United Arab Emirates'] as $country)
                            <option value="{{ $country }}" {{ $lead->country === $country ? 'selected' : '' }}>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state" value="{{ $lead->state }}">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="{{ $lead->city }}">
                </div>
                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" name="pincode" value="{{ $lead->pincode }}">
                </div>
                <div class="form-group">
                    <label>Domain option</label>
                    <select name="domain_type" id="editDomainType">
                        <option value="">No domain</option>
                        <option value="existing" {{ $lead->domain_type === 'existing' ? 'selected' : '' }}>Already have domain</option>
                        <option value="new" {{ $lead->domain_type === 'new' ? 'selected' : '' }}>Need new domain</option>
                    </select>
                </div>
                <div class="form-group edit-domain-name domain-check-group" style="{{ $lead->domain_type ? '' : 'display:none;' }}">
                    <label>Domain Name</label>
                    <div class="domain-check-row">
                        <input type="text" name="domain" id="editDomainName" value="{{ old('domain', $lead->domain) }}" placeholder="example.com">
                        <button type="button" class="add-plan-btn domain-check-btn edit-domain-check" id="editCheckDomainBtn" style="{{ $lead->domain_type === 'new' ? '' : 'display:none;' }}">Check Availability</button>
                    </div>
                    @error('domain')<div class="form-error">{{ $message }}</div>@enderror
                    <div id="editDomainCheckStatus" class="form-hint domain-check-status"></div>
                    <div id="editDomainHaveHint" class="form-hint"></div>
                </div>
                <div class="form-group edit-domain-have" style="{{ ($lead->domain_type ?? '') === 'existing' ? '' : 'display:none;' }}">
                    <label>Renewal Date</label>
                    <div class="date-input">
                        <input type="date" name="domain_renewal_date" id="editDomainRenewal" value="{{ old('domain_renewal_date', optional($lead->domain_renewal_date)->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="form-group edit-domain-new" style="{{ $lead->domain_type === 'new' ? '' : 'display:none;' }}">
                    <label>Period</label>
                    <select name="domain_period">
                        <option value="">Select Period</option>
                        @foreach (['Monthly', 'Quarterly', 'Half Yearly', 'Yearly'] as $period)
                            <option value="{{ $period }}" {{ $lead->domain_period === $period ? 'selected' : '' }}>{{ $period }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group edit-domain-new" style="{{ $lead->domain_type === 'new' ? '' : 'display:none;' }}">
                    <label>Rate</label>
                    <input type="number" name="domain_rate" min="0" step="0.01" value="{{ $lead->domain_rate }}">
                </div>
                <div class="form-group edit-domain-new" style="{{ $lead->domain_type === 'new' ? '' : 'display:none;' }}">
                    <label>SAC</label>
                    <input type="text" name="sac" value="{{ $lead->sac }}">
                </div>
                <div class="form-group edit-domain-new" style="{{ $lead->domain_type === 'new' ? '' : 'display:none;' }}">
                    <label>Domain Start</label>
                    <input type="date" name="domain_start" value="{{ optional($lead->domain_start)->format('Y-m-d') }}">
                </div>
                <div class="form-group edit-domain-new" style="{{ $lead->domain_type === 'new' ? '' : 'display:none;' }}">
                    <label>Domain End</label>
                    <input type="date" name="domain_end" value="{{ optional($lead->domain_end)->format('Y-m-d') }}">
                </div>
            </div>
            <div class="submit-wrap"><button type="submit" class="submit-btn">Save Details</button></div>
        </form>
    </div>
</div>

<div class="credit-modal" id="contactModal">
    <div class="credit-modal-box">
        <button type="button" class="credit-close close-modal">&times;</button>
        <h2 id="contactModalTitle">Add Contact</h2>
        <form method="POST" action="{{ route('crm.leads.contacts.store', $lead) }}" id="contactForm" class=" form-grid common-form">
            @csrf
            <div class="form-group"><label>Full Name <span>*</span></label><input type="text" name="name" id="contactName" required></div>
            <div class="form-group"><label>Designation</label><input type="text" name="designation" id="contactDesignation"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="contactEmail"></div>
            <div class="form-group"><label>Contact No.</label><input type="tel" name="mobile" id="contactMobile" inputmode="numeric" maxlength="15" pattern="[0-9]{6,15}" class="js-mobile-digits"></div>
            <div class="submit-wrap"><button type="submit" class="submit-btn">Save</button></div>
        </form>
    </div>
</div>

<div class="credit-modal" id="serviceModal">
    <div class="credit-modal-box credit-modal-wide">
        <button type="button" class="credit-close close-modal">&times;</button>
        <h2 id="serviceModalTitle">Add Service</h2>
        <form method="POST" action="{{ route('crm.leads.services.store', $lead) }}" id="leadServiceForm" class="common-form">
            @csrf
            <input type="hidden" name="is_info" id="serviceIsInfo" value="0">
            <div class="form-grid form-grid-3">
                <div class="form-group">
                    <label>Provider</label>
                    <select name="provider_id" id="serviceProvider" class="provider-select" required>
                        <option value="">Select Provider</option>
                        @foreach ($providers ?? [] as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Plan</label>
                    <select name="plan_id" id="servicePlan" class="plan-select" required>
                        <option value="">Select Plan</option>
                    </select>
                </div>
                <div class="form-group" id="servicePeriodWrap">
                    <label>Quot Period</label>
                    <select name="quote_period" id="servicePeriod" class="quote-period-select">
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Half Yearly">Half Yearly</option>
                        <option value="Yearly" selected>Yearly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" id="serviceSku" class="sku-input" readonly>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="serviceQty" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label id="serviceRateLabel">Rate</label>
                    <input type="number" name="rate" id="serviceRate" class="rate-input" min="0" step="0.01">
                </div>
                <div class="form-group" id="serviceDomainWrap" style="display:none;">
                    <label>Domain Name</label>
                    <input type="text" name="domain" id="serviceDomain" placeholder="example.com">
                </div>
                <div class="form-group" id="serviceRenewalWrap" style="display:none;">
                    <label>Renewal Date</label>
                    <div class="date-input">
                        <input type="date" name="renewal_date" id="serviceRenewal">
                    </div>
                </div>
            </div>
            <div class="submit-wrap"><button type="submit" class="submit-btn">Save</button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const providerPlans = @json($providerPlans ?? []);
    var hasGoogleQuote = {{ $quoteServices->contains(fn ($s) => stripos(optional($s->provider)->name ?? '', 'google') !== false) ? 'true' : 'false' }};

    function openModal(id) {
        document.getElementById(id).classList.add('active');
        if (window.lucide) lucide.createIcons();
    }
    function closeModals() {
        document.querySelectorAll('.credit-modal').forEach(function (m) { m.classList.remove('active'); });
    }
    document.querySelectorAll('.close-modal').forEach(function (btn) {
        btn.addEventListener('click', closeModals);
    });
    document.querySelectorAll('.credit-modal').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) closeModals();
        });
    });

    const leadEdit = document.getElementById('openLeadEdit');
    if (leadEdit) leadEdit.addEventListener('click', function () { openModal('leadModal'); });

    const openAddDocument = document.getElementById('openAddDocument');
    if (openAddDocument) {
        openAddDocument.addEventListener('click', function (e) {
            e.preventDefault();
            openModal('leadDocumentModal');
        });
    }

    const editDomainType = document.getElementById('editDomainType');
    const editDomainHaveHint = document.getElementById('editDomainHaveHint');
    const editDomainCheckStatus = document.getElementById('editDomainCheckStatus');
    const editCheckDomainBtn = document.getElementById('editCheckDomainBtn');
    const editDomainName = document.getElementById('editDomainName');

    function toggleEditDomain() {
        const type = editDomainType ? editDomainType.value : '';
        document.querySelectorAll('.edit-domain-name').forEach(function (el) {
            el.style.display = type ? '' : 'none';
        });
        document.querySelectorAll('.edit-domain-check').forEach(function (el) {
            el.style.display = type === 'new' ? '' : 'none';
        });
        document.querySelectorAll('.edit-domain-new').forEach(function (el) {
            el.style.display = type === 'new' ? '' : 'none';
        });
        document.querySelectorAll('.edit-domain-have').forEach(function (el) {
            el.style.display = type === 'existing' ? 'flex' : 'none';
        });
        if (type !== 'new' && editDomainCheckStatus) {
            editDomainCheckStatus.textContent = '';
        }
        if (type !== 'existing' && editDomainHaveHint) {
            editDomainHaveHint.textContent = '';
        }
    }
    if (editDomainType) {
        editDomainType.addEventListener('change', toggleEditDomain);
        toggleEditDomain();
    }

    if (editCheckDomainBtn) {
        editCheckDomainBtn.addEventListener('click', function () {
            if (!editDomainType || editDomainType.value !== 'new') {
                return;
            }
            const domain = ((editDomainName || {}).value || '').trim();
            if (!editDomainCheckStatus) {
                return;
            }
            editDomainCheckStatus.style.color = '#6c757d';
            editDomainCheckStatus.textContent = 'Checking...';
            fetch("{{ route('crm.leads.check-domain') }}?domain=" + encodeURIComponent(domain) + "&ignore_id={{ $lead->id }}")
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.invalid) {
                        editDomainCheckStatus.style.color = '#b91c1c';
                        editDomainCheckStatus.textContent = data.message || 'Enter a valid domain (example.com)';
                        return;
                    }
                    if (data.available) {
                        editDomainCheckStatus.style.color = '#15803d';
                        editDomainCheckStatus.textContent = data.message || 'This domain is available to register.';
                        return;
                    }
                    if (!data.registered) {
                        editDomainCheckStatus.style.color = '#b91c1c';
                        editDomainCheckStatus.textContent = data.message || 'Could not check domain.';
                        return;
                    }
                    editDomainType.value = 'existing';
                    toggleEditDomain();
                    if (editDomainHaveHint) {
                        editDomainHaveHint.style.color = '#b45309';
                        editDomainHaveHint.textContent = 'This domain is already registered, so Already have domain was selected. You can save the lead.';
                    }
                })
                .catch(function () {
                    editDomainCheckStatus.style.color = '#b91c1c';
                    editDomainCheckStatus.textContent = 'Could not check domain.';
                });
        });
    }

    const leadDetailsForm = document.getElementById('leadDetailsForm');
    const editGstin = document.getElementById('editGstin');
    const editGstStatus = document.getElementById('editGstStatus');

    function fillEditFromGst(data) {
        if (!leadDetailsForm) {
            return;
        }
        const setVal = function (name, value) {
            if (!value) {
                return;
            }
            const el = leadDetailsForm.querySelector('[name="' + name + '"]');
            if (!el) {
                return;
            }
            el.value = value;
        };
        setVal('company_name', data.company_name);
        setVal('mailing_address', data.company_address);
        setVal('state', data.state);
        setVal('city', data.city);
        setVal('pincode', data.pincode);
        setVal('country', data.country);
        let industry = data.constitution || '';
        if (!industry && data.nature_of_business) {
            industry = Array.isArray(data.nature_of_business)
                ? data.nature_of_business.join(', ')
                : data.nature_of_business;
        }
        setVal('industry', industry);
    }

    if (editGstin) {
        let lastFetchedGstin = '';
        const gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

        function processEditGstinSearch() {
            const gstin = String(editGstin.value || '').trim().toUpperCase();
            editGstin.value = gstin;
            if (!editGstStatus) {
                return;
            }
            if (!gstin) {
                editGstStatus.textContent = '';
                editGstStatus.style.color = '';
                return;
            }
            if (!gstinPattern.test(gstin)) {
                editGstStatus.style.color = '#DC2626';
                editGstStatus.textContent = '✕ Invalid GSTIN';
                return;
            }
            if (gstin === lastFetchedGstin) {
                return;
            }
            editGstStatus.style.color = '#6B7280';
            editGstStatus.textContent = 'Fetching GST details...';
            const csrf = (leadDetailsForm.querySelector('input[name="_token"]') || {}).value || '';
            fetch("{{ route('search-gstin') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ gstin: gstin })
            })
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok) {
                        throw body;
                    }
                    return body;
                });
            })
            .then(function (data) {
                if (data.error) {
                    editGstStatus.style.color = '#DC2626';
                    editGstStatus.textContent = '✕ ' + (data.message || 'Invalid GSTIN');
                    lastFetchedGstin = '';
                    return;
                }
                editGstStatus.style.color = '#16A34A';
                editGstStatus.textContent = '✓ GSTIN details verified!';
                lastFetchedGstin = gstin;
                fillEditFromGst(data);
            })
            .catch(function (error) {
                editGstStatus.style.color = '#DC2626';
                editGstStatus.textContent = '✕ ' + ((error && error.message) ? error.message : 'Could not fetch GSTIN details.');
                lastFetchedGstin = '';
            });
        }

        editGstin.addEventListener('input', function () {
            editGstin.value = editGstin.value.toUpperCase().replace(/\s+/g, '').substring(0, 15);
            if (!editGstin.value) {
                editGstStatus.textContent = '';
            } else if (editGstin.value.length < 15) {
                editGstStatus.style.color = '#6B7280';
                editGstStatus.textContent = 'Enter 15-character GSTIN';
            } else if (!gstinPattern.test(editGstin.value)) {
                editGstStatus.style.color = '#DC2626';
                editGstStatus.textContent = '✕ Invalid GSTIN';
            } else {
                processEditGstinSearch();
            }
        });
        editGstin.addEventListener('blur', processEditGstinSearch);
    }

    const contactForm = document.getElementById('contactForm');
    document.getElementById('openAddContact').addEventListener('click', function (e) {
        e.preventDefault();
        contactForm.action = "{{ route('crm.leads.contacts.store', $lead) }}";
        document.getElementById('contactModalTitle').textContent = 'Add Contact';
        document.getElementById('contactName').value = '';
        document.getElementById('contactDesignation').value = '';
        document.getElementById('contactEmail').value = '';
        document.getElementById('contactMobile').value = '';
        openModal('contactModal');
    });
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.edit-contact-btn');
        if (!btn) return;
        contactForm.action = btn.dataset.action;
        document.getElementById('contactModalTitle').textContent = 'Edit Contact';
        document.getElementById('contactName').value = btn.dataset.name || '';
        document.getElementById('contactDesignation').value = btn.dataset.designation || '';
        document.getElementById('contactEmail').value = btn.dataset.email || '';
        document.getElementById('contactMobile').value = btn.dataset.mobile || '';
        openModal('contactModal');
    });

    const serviceProvider = document.getElementById('serviceProvider');
    const servicePlan = document.getElementById('servicePlan');
    const servicePeriod = document.getElementById('servicePeriod');
    const serviceSku = document.getElementById('serviceSku');
    const serviceRate = document.getElementById('serviceRate');

    function loadPlans(providerId, selectedPlanId) {
        servicePlan.innerHTML = '<option value="">Select Plan</option>';
        serviceSku.value = '';
        serviceRate.value = '';
        if (!providerId || !providerPlans[providerId]) return;
        providerPlans[providerId].forEach(function (plan) {
            const opt = document.createElement('option');
            opt.value = plan.id;
            opt.textContent = plan.name;
            opt.dataset.sku = plan.sku || '';
            opt.dataset.actualPrice = plan.actual_price || plan.rate || 0;
            servicePlan.appendChild(opt);
        });
        if (selectedPlanId) servicePlan.value = selectedPlanId;
        if (servicePlan.value) servicePlan.dispatchEvent(new Event('change'));
    }

    function updateRate() {
        const selected = servicePlan.options[servicePlan.selectedIndex];
        if (!selected || !selected.value) {
            if (!serviceIsInfo || serviceIsInfo.value !== '1') {
                serviceRate.value = '';
            }
            return;
        }
        serviceSku.value = selected.dataset.sku || '';
        if (serviceIsInfo && serviceIsInfo.value === '1') {
            if (!serviceRate.value) {
                serviceRate.value = selected.dataset.actualPrice || '';
            }
            return;
        }
        const actual = parseFloat(selected.dataset.actualPrice) || 0;
        let rate = actual;
        if (servicePeriod.value === 'Monthly') rate = actual / 12;
        if (servicePeriod.value === 'Quarterly') rate = actual / 4;
        if (servicePeriod.value === 'Half Yearly') rate = actual / 2;
        serviceRate.value = rate > 0 ? rate.toFixed(2) : '';
    }

    const serviceIsInfo = document.getElementById('serviceIsInfo');
    const servicePeriodWrap = document.getElementById('servicePeriodWrap');
    const serviceRenewalWrap = document.getElementById('serviceRenewalWrap');
    const serviceDomainWrap = document.getElementById('serviceDomainWrap');
    const serviceRateLabel = document.getElementById('serviceRateLabel');
    const serviceRenewal = document.getElementById('serviceRenewal');
    const serviceDomain = document.getElementById('serviceDomain');

    function setServiceMode(isInfo) {
        serviceIsInfo.value = isInfo ? '1' : '0';
        servicePeriodWrap.style.display = isInfo ? 'none' : '';
        serviceRenewalWrap.style.display = isInfo ? '' : 'none';
        if (serviceDomainWrap) {
            serviceDomainWrap.style.display = isInfo ? '' : 'none';
        }
        serviceRateLabel.textContent = isInfo ? 'Current Price' : 'Rate';
        serviceRate.readOnly = false;
        if (!isInfo) {
            serviceRenewal.value = '';
            if (serviceDomain) {
                serviceDomain.value = '';
            }
        }
    }

    function resetServiceForm(isInfo, title) {
        serviceForm.action = "{{ route('crm.leads.services.store', $lead) }}";
        document.getElementById('serviceModalTitle').textContent = title;
        setServiceMode(isInfo);
        serviceProvider.value = '';
        servicePlan.innerHTML = '<option value="">Select Plan</option>';
        servicePeriod.value = 'Yearly';
        serviceSku.value = '';
        document.getElementById('serviceQty').value = 1;
        serviceRate.value = '';
        serviceRenewal.value = '';
        if (serviceDomain) {
            serviceDomain.value = '';
        }
        openModal('serviceModal');
    }

    serviceProvider.addEventListener('change', function () { loadPlans(this.value); });
    servicePlan.addEventListener('change', updateRate);
    servicePeriod.addEventListener('change', updateRate);

    const serviceForm = document.getElementById('leadServiceForm');
    document.getElementById('openAddService').addEventListener('click', function (e) {
        e.preventDefault();
        resetServiceForm(false, 'Add Our Service');
    });
    const openAddInfoService = document.getElementById('openAddInfoService');
    if (openAddInfoService) {
        openAddInfoService.addEventListener('click', function (e) {
            e.preventDefault();
            resetServiceForm(true, 'Add Current Service');
        });
    }
    document.addEventListener('click', function (e) {
        const infoBtn = e.target.closest('.edit-info-service-btn');
        if (infoBtn) {
            serviceForm.action = infoBtn.dataset.action;
            document.getElementById('serviceModalTitle').textContent = 'Edit Current Service';
            setServiceMode(true);
            serviceProvider.value = infoBtn.dataset.provider || '';
            document.getElementById('serviceQty').value = infoBtn.dataset.quantity || 1;
            serviceRenewal.value = infoBtn.dataset.renewal || '';
            if (serviceDomain) {
                serviceDomain.value = infoBtn.dataset.domain || '';
            }
            loadPlans(serviceProvider.value, infoBtn.dataset.plan);
            serviceRate.value = infoBtn.dataset.rate || '';
            openModal('serviceModal');
            return;
        }
        const btn = e.target.closest('.edit-service-btn');
        if (!btn) return;
        serviceForm.action = btn.dataset.action;
        document.getElementById('serviceModalTitle').textContent = 'Edit Our Service';
        setServiceMode(false);
        serviceProvider.value = btn.dataset.provider || '';
        servicePeriod.value = btn.dataset.period || 'Yearly';
        document.getElementById('serviceQty').value = btn.dataset.quantity || 1;
        loadPlans(serviceProvider.value, btn.dataset.plan);
        openModal('serviceModal');
    });

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
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { el.focus(); }, 200);
    }

    function bindRequiredForm(form, checks) {
        if (!form) return;
        form.addEventListener('input', function (e) {
            if (e.target && e.target.name) clearFieldError(e.target);
        });
        form.addEventListener('change', function (e) {
            if (e.target && e.target.name) clearFieldError(e.target);
        });
        form.addEventListener('submit', function (e) {
            form.querySelectorAll('.js-field-error').forEach(function (err) { err.remove(); });
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            let firstInvalid = null;
            checks.forEach(function (item) {
                const el = form.querySelector('[name="' + item.name + '"]');
                if (!el || !String(el.value || '').trim()) {
                    showFieldError(el, item.msg);
                    if (!firstInvalid && el) firstInvalid = el;
                }
            });
            const emailEl = form.querySelector('[name="email"]');
            if (emailEl && String(emailEl.value || '').trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(emailEl.value).trim())) {
                showFieldError(emailEl, 'Enter a valid email.');
                if (!firstInvalid) firstInvalid = emailEl;
            }
            const mobileEl = form.querySelector('[name="mobile"]');
            if (mobileEl && mobileEl.hasAttribute('required') && String(mobileEl.value || '').trim() && !/^[0-9]{6,15}$/.test(String(mobileEl.value || '').trim())) {
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

    bindRequiredForm(leadDetailsForm, [
        { name: 'salutation', msg: 'Title is required.' },
        { name: 'name', msg: 'Name is required.' },
        { name: 'email', msg: 'Email is required.' },
        { name: 'mobile', msg: 'Mobile No. is required.' },
        { name: 'lead_status', msg: 'Lead Status is required.' }
    ]);

    const followUpForm = document.getElementById('followUpForm');
    bindRequiredForm(followUpForm, [
        { name: 'lead_status', msg: 'Lead Status is required.' }
    ]);

    if (window.lucide) lucide.createIcons();

    (function () {
        const uploadBox = document.getElementById('leadShowUploadBox');
        const fileInput = document.getElementById('leadShowDocumentInput');
        const picked = document.getElementById('leadShowPickedFiles');
        if (!uploadBox || !fileInput || !picked) {
            return;
        }
        const allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        const maxBytes = 10 * 1024 * 1024;

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

        function showPicked(files) {
            if (!picked) {
                return;
            }
            picked.innerHTML = '';
            if (!files || !files.length) {
                picked.hidden = true;
                return;
            }
            picked.hidden = false;
            const typeSelect = document.querySelector('#leadDocumentForm [name="document_type"]');
            const docType = typeSelect ? typeSelect.value : '';
            Array.from(files).forEach(function (file, index) {
                const kind = fileKind(file.name);
                const card = document.createElement('div');
                card.className = 'doc-file-card';
                card.innerHTML =
                    '<div class="doc-file-icon ' + kind.cls + '">' + kind.label + '</div>' +
                    '<div class="doc-file-meta">' +
                        '<strong></strong>' +
                        '<span></span>' +
                    '</div>' +
                    '<em class="doc-file-badge">Ready</em>' +
                    '<button type="button" class="doc-file-remove" data-remove="' + index + '" aria-label="Remove">&times;</button>';
                card.querySelector('strong').textContent = file.name;
                card.querySelector('span').textContent = [docType, fileSizeLabel(file.size)].filter(Boolean).join(' · ');
                picked.appendChild(card);
            });
        }

        picked.addEventListener('click', function (e) {
            const btn = e.target.closest('.doc-file-remove');
            if (!btn) {
                return;
            }
            const index = parseInt(btn.getAttribute('data-remove'), 10);
            const dataTransfer = new DataTransfer();
            Array.from(fileInput.files || []).forEach(function (file, i) {
                if (i !== index) {
                    dataTransfer.items.add(file);
                }
            });
            fileInput.files = dataTransfer.files;
            showPicked(fileInput.files);
        });

        function setFiles(fileListLike) {
            const next = [];
            Array.from(fileListLike || []).forEach(function (file) {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (allowed.indexOf(ext) === -1) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Unsupported file: ' + file.name, text: 'Use PDF, JPG, PNG, DOC or DOCX.' });
                    }
                    return;
                }
                if (file.size > maxBytes) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: file.name + ' is larger than 10 MB.' });
                    }
                    return;
                }
                next.push(file);
            });
            const dataTransfer = new DataTransfer();
            next.forEach(function (file) { dataTransfer.items.add(file); });
            fileInput.files = dataTransfer.files;
            showPicked(fileInput.files);
        }

        fileInput.addEventListener('change', function () {
            setFiles(this.files);
        });

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
            setFiles(e.dataTransfer ? e.dataTransfer.files : []);
        });
    })();

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
