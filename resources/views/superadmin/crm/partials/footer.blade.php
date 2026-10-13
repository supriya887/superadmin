<!-- Add Credit -->
<div class="credit-modal" id="creditModal">
    <div class="credit-modal-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Add Account Credit</h2>

        <p class="credit-info">
            Current allocation: ₹150,691.76 · Available: ₹50,000.00
        </p>

        <form>
            <div class="form-group">
                <label>
                    Amount to add (₹) <span>*</span>
                </label>

                <input type="number" placeholder="Eg. 50,000">
            </div>

            <div class="form-group">
                <label>
                    Description <span>*</span>
                </label>

                <textarea
                    rows="5"
                    placeholder="Reasoning for increase Credit"></textarea>
            </div>

            <div class="text-center">
                <button type="submit" class="submit-btn">
                    Submit
                </button>
            </div>
        </form>

    </div>
</div>


<!-- Approve Credit Allocation -->
<!-- Approve Credit Allocation -->
<div class="credit-modal" id="approveCreditModal">
    <div class="credit-modal-box approve-credit-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Approve credit allocation</h2>

        <p class="credit-request-info">
            Request #10 · Mittal Solanki
        </p>

        <p class="credit-request-info">
            Current allocation: ₹150,691.76 · Available: ₹50,000.00
        </p>

        <form>

            <div class="form-group">
                <label>
                    Amount to add (₹) <span>*</span>
                </label>

                <input
                    type="number"
                    placeholder="Eg. 50,000">

                <small class="form-help">
                    You can approve a different amount than requested.
                    This value is added to credit allocation.
                </small>
            </div>


            <div class="form-group">
                <label>
                    Description <span>*</span>
                </label>

                <textarea
                    rows="5"
                    placeholder="Reasoning for increase Credit"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="submit-btn">Approve</button>
            </div>

        </form>

    </div>
</div>
<!-- Reject Credit Request Modal -->
<div class="credit-modal" id="rejectCreditModal">
    <div class="credit-modal-box reject-credit-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Reject this credit request?</h2>

        <p class="credit-request-info">
            Request #10 · Mittal Solanki
        </p>

        <p class="credit-request-info">
            Reject allocation: ₹150,691.76 · Available: ₹50,000.00
        </p>

        <form class="form-group reject-reason">
            <div class="form-group">
                <label>
                    rejection reason <span>*</span>
                </label>

                <textarea rows="5" placeholder="Reasoning for Reject this credit request"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="submit-btn">Reject</button>
            </div>
        </form>



    </div>
</div>
<div class="quotation-popup">
    <div class="popup-title">Quotation</div>
    <div class="popup-actions">
        <a href="#" id="quotationPopupView" class="popup-action">
            <i data-lucide="eye"></i>
            <span>View</span>
        </a>
        <a href="#" id="quotationPopupHistory" class="popup-action">
            <i data-lucide="history"></i>
            <span>History</span>
        </a>
        <a href="#" id="quotationPopupEdit" class="popup-action">
            <i data-lucide="pencil"></i>
            <span>Edit</span>
        </a>
        <a href="#" id="quotationPopupDownload" class="popup-action">
            <i data-lucide="download"></i>
            <span>Download</span>
        </a>
        <button type="button" class="popup-action" id="quotationPopupSend" data-modal-open="sendQuotationModal" onclick="var f=document.getElementById('sendQuotationForm'); if(f){f.action='{{ route('crm.quotations.send') }}';} var h=document.querySelector('#sendQuotationModal h2'); if(h){h.textContent='Send Quotation';} if (window.closeQuotationPopup) { closeQuotationPopup(); }">
            <i data-lucide="mail"></i>
            <span>Send</span>
        </button>
    </div>
    <button type="button" class="discount-btn" id="quotationPopupDiscount" data-modal-open="sendDiscountModal" onclick="if (window.closeQuotationPopup) { closeQuotationPopup(); }">
        <i data-lucide="corner-up-right"></i>
        Send for more discount
    </button>
    <div id="quotationPopupInvoice" hidden>
        <div class="popup-title invoice-title">Invoice</div>
        <div class="popup-actions invoice-actions">
            <a href="#" class="popup-action" id="quotationPopupInvoiceDownload">
                <i data-lucide="download"></i>
                <span>Download</span>
            </a>
            <button type="button" class="popup-action" id="quotationPopupInvoiceEmail" data-modal-open="sendQuotationModal" onclick="var f=document.getElementById('sendQuotationForm'); if(f){f.action='{{ route('crm.quotations.invoice.send') }}';} var h=document.querySelector('#sendQuotationModal h2'); if(h){h.textContent='Send Invoice';} if (window.closeQuotationPopup) { closeQuotationPopup(); }">
                <i data-lucide="mail"></i>
                <span>Email</span>
            </button>
        </div>
    </div>
</div>

<div class="credit-modal" id="sendDiscountModal">
    <div class="credit-modal-box send-quote-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>SEND FOR MORE DISCOUNT</h2>

        <form onsubmit="this.closest('.credit-modal').classList.remove('active'); return false;">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee">
                        <option selected>Admin</option>
                        <option>Pranali Jadhav</option>
                        <option>Meera Patil</option>
                        <option>Sonia Gupta</option>
                        <option>Aditya Bansal</option>
                        <option>Karan Verma</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Requested Discount (%)</label>
                    <input type="number" name="requested_discount" min="0" max="100" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label>Remark</label>
                <textarea name="remark" rows="3"></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="submit-btn">Submit</button>
            </div>
        </form>

    </div>
</div>

@php
    $gmailContacts = collect();
    $internalTeam = collect();
    $teamRoleLabels = [
        'admin' => 'Admin',
        'manager' => 'Team Manager',
        'executive' => 'Executive',
        'marketing_manager' => 'Marketing Manager',
        'product_designer' => 'Product Designer',
        'customer_support' => 'Customer Support',
        'user' => 'Team',
    ];
    try {
        $gmailContacts = \App\Models\Lead::query()
            ->orderBy('name')
            ->get(['name', 'email', 'company_name'])
            ->filter(fn ($row) => filled($row->email))
            ->map(fn ($row) => [
                'email' => strtolower(trim($row->email)),
                'name' => $row->name ?: $row->company_name,
                'meta' => $row->company_name,
            ])
            ->unique('email')
            ->values();
        $contactMails = \App\Models\LeadContact::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name')
            ->get(['name', 'email']);
        foreach ($contactMails as $contact) {
            $email = strtolower(trim($contact->email));
            if ($email === '' || $gmailContacts->contains(fn ($row) => $row['email'] === $email)) {
                continue;
            }
            $gmailContacts->push([
                'email' => $email,
                'name' => $contact->name ?: $email,
                'meta' => '',
            ]);
        }
        $internalTeam = \App\Models\CrmUser::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'status']);
        if ($internalTeam->contains(fn ($row) => filled($row->status))) {
            $internalTeam = $internalTeam->filter(fn ($row) => strtolower((string) $row->status) !== 'inactive')->values();
        }
        foreach ($internalTeam as $member) {
            $email = strtolower(trim((string) $member->email));
            $role = $teamRoleLabels[$member->role] ?? ucfirst(str_replace('_', ' ', (string) $member->role));
            if ($email === '' || $gmailContacts->contains(fn ($row) => $row['email'] === $email)) {
                continue;
            }
            $gmailContacts->push([
                'email' => $email,
                'name' => $member->name ?: $email,
                'meta' => $role,
            ]);
        }
    } catch (\Throwable $e) {
        $gmailContacts = collect();
        $internalTeam = collect();
    }
@endphp
<div class="credit-modal" id="sendQuotationModal">
    <div class="credit-modal-box send-quote-box gmail-send-box">

        <button class="credit-close" type="button" data-modal-close>
            &times;
        </button>

        <h2>Send Quotation</h2>

        <form method="POST" action="{{ route('crm.quotations.send') }}" id="sendQuotationForm">
            @csrf
            <input type="hidden" name="quotation_id" id="sendQuotationId" value="">
            <input type="hidden" id="sendQuotationPrimaryEmail" value="">
            <div class="gmail-compose">
                <div class="gmail-to-row">
                    <span class="gmail-to-label">To</span>
                    <div class="gmail-to-field" id="sendEmailChipBox">
                        <div class="email-chip-list" id="sendEmailChipList"></div>
                        <input type="text" id="sendEmailChipInput" placeholder="Add another email" autocomplete="off" spellcheck="false">
                        <div class="gmail-to-suggest" id="sendEmailSuggest" hidden></div>
                    </div>
                </div>
                <div class="gmail-to-row">
                    <span class="gmail-to-label">Cc</span>
                    <div class="gmail-to-field" id="sendCcChipBox">
                        <div class="email-chip-list" id="sendCcChipList"></div>
                        <input type="text" id="sendCcChipInput" placeholder="Add CC email" autocomplete="off" spellcheck="false">
                        <div class="gmail-to-suggest" id="sendCcSuggest" hidden></div>
                    </div>
                </div>
                @if($internalTeam->isNotEmpty())
                <div class="gmail-team-picks">
                    <span>Internal team</span>
                    <div>
                        @foreach ($internalTeam as $member)
                            @php
                                $memberEmail = strtolower(trim((string) $member->email));
                                $memberRole = $teamRoleLabels[$member->role] ?? ucfirst(str_replace('_', ' ', (string) $member->role));
                            @endphp
                            <button type="button" class="gmail-team-chip" data-email="{{ $memberEmail }}">
                                {{ $member->name }}
                                <small>{{ $memberRole }}</small>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif
                <p class="gmail-to-hint">
                    Type an email and press <kbd>Enter</kbd>. Use CC for team manager and internal staff.
                </p>
                <textarea name="emails" id="sendQuotationEmails" rows="1" class="email-chip-source"></textarea>
                <textarea name="cc" id="sendQuotationCc" rows="1" class="email-chip-source"></textarea>
            </div>
            <div class="gmail-send-actions">
                <button type="submit" class="submit-btn gmail-send-btn">Send</button>
            </div>
        </form>

    </div>
</div>

<!-- Scripts -->
<!-- =========================
     BULK ACTION BAR
========================== -->

<div class="bulk-action-bar" id="bulkActionBar" data-download-url="{{ route('crm.leads.download') }}" data-delete-url="{{ route('crm.leads.delete') }}">

    <button type="button" id="assignEmployeeBtn">
        Assign Employee
    </button>

    <button type="button" id="downloadListBtn">
        Download List
    </button>

    <button type="button" id="deleteLeadsBtn">
        Delete Leads
    </button>

</div>

<!-- employee -->
<!-- =========================
     ASSIGN EMPLOYEE POPUP
========================= -->

<div id="assignEmployeeModal" data-assign-url="{{ route('crm.leads.assign') }}">

    <div id="assignEmployeeModalBox">

        <button type="button" id="closeAssignEmployeeModal">
            <i data-lucide="x"></i>
        </button>

        <h3>
            ASSIGN EMPLOYEE TO SELECTED LEADS
        </h3>


        <div id="assignEmployeeList">
            @php
                $assignEmployees = collect();
                $assignLeadCounts = collect();
                try {
                    $assignEmployees = \App\Models\CrmUser::query()
                        ->where('role', 'executive')
                        ->orderBy('name')
                        ->get(['id', 'name']);
                    $assignLeadCounts = \App\Models\Lead::query()
                        ->selectRaw('assigned_to, count(*) as total')
                        ->whereNotNull('assigned_to')
                        ->where('assigned_to', '!=', '')
                        ->groupBy('assigned_to')
                        ->pluck('total', 'assigned_to');
                } catch (\Throwable $e) {
                    $assignEmployees = collect();
                }
            @endphp
            @forelse($assignEmployees as $assignEmp)
                @php
                    $assignCount = (int) $assignLeadCounts->get($assignEmp->name, 0) + (int) $assignLeadCounts->get((string) $assignEmp->id, 0);
                    $assignInitial = strtoupper(substr(trim($assignEmp->name), 0, 1)) ?: 'E';
                @endphp
                <label>
                    <input type="radio" name="assignEmployee" value="{{ $assignEmp->name }}">
                    <span>
                        <span class="assign-avatar">{{ $assignInitial }}</span>
                        {{ $assignEmp->name }}
                    </span>
                    <strong>{{ $assignCount }}</strong>
                </label>
            @empty
                <p>No employees found. Create an employee first.</p>
            @endforelse
        </div>

        <div class="submit-wrap">
            <button type="button" class="submit-btn" id="confirmAssignEmployee">
                Confirm
            </button>
        </div>
    </div>

</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
<script src="{{ asset('superadmin/crm/js/script.js') }}"></script>
<script src="{{ asset('superadmin/crm/js/crm.js') }}"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.lucide) {
            lucide.createIcons();
        }

        var successMsg = @json(session('success'));
        var errorMsg = @json(session('error'));
        var validationMsg = @json($errors->any() ? $errors->first() : null);

        if (typeof Swal !== 'undefined') {
            if (successMsg) {
                Swal.fire({
                    icon: 'success',
                    title: successMsg,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    showCloseButton: true,
                    timer: 8000,
                    timerProgressBar: true
                });
            }

            if (errorMsg || validationMsg) {
                Swal.fire({
                    icon: 'error',
                    title: errorMsg ? 'Error' : 'Check the form',
                    text: errorMsg || validationMsg,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#075db5'
                });
            }
        }

        var bulkBar = document.getElementById('bulkActionBar');
        var downloadBtn = document.getElementById('downloadListBtn');
        var deleteBtn = document.getElementById('deleteLeadsBtn');

        function selectedLeadBoxes() {
            return document.querySelectorAll('.crm-lead-table tbody input[type="checkbox"]:checked');
        }

        function postSelectedLeads(url) {
            var boxes = selectedLeadBoxes();
            if (!boxes.length) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Select at least one lead.' });
                } else {
                    alert('Select at least one lead.');
                }
                return;
            }
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            var token = document.querySelector('meta[name="csrf-token"]');
            if (token) {
                var csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = token.getAttribute('content');
                form.appendChild(csrf);
            }
            boxes.forEach(function (box) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'lead_ids[]';
                input.value = box.value;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }

        function replaceButton(button) {
            if (!button || !button.parentNode) {
                return null;
            }
            var clone = button.cloneNode(true);
            button.parentNode.replaceChild(clone, button);
            return clone;
        }

        downloadBtn = replaceButton(downloadBtn);
        deleteBtn = replaceButton(deleteBtn);

        if (downloadBtn && bulkBar) {
            downloadBtn.addEventListener('click', function () {
                postSelectedLeads(bulkBar.getAttribute('data-download-url'));
            });
        }

        if (deleteBtn && bulkBar) {
            deleteBtn.addEventListener('click', function () {
                var count = selectedLeadBoxes().length;
                if (!count) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'warning', title: 'Select at least one lead.' });
                    } else {
                        alert('Select at least one lead.');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Delete ' + count + ' lead(s)?',
                        showCancelButton: true,
                        confirmButtonText: 'Delete',
                        confirmButtonColor: '#dc2626'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            postSelectedLeads(bulkBar.getAttribute('data-delete-url'));
                        }
                    });
                    return;
                }
                if (confirm('Delete ' + count + ' lead(s)?')) {
                    postSelectedLeads(bulkBar.getAttribute('data-delete-url'));
                }
            });
        }

        var sendEmailSource = document.getElementById('sendQuotationEmails');
        var sendEmailList = document.getElementById('sendEmailChipList');
        var sendEmailInput = document.getElementById('sendEmailChipInput');
        var sendEmailBox = document.getElementById('sendEmailChipBox');
        var sendEmailSuggest = document.getElementById('sendEmailSuggest');
        var sendCcSource = document.getElementById('sendQuotationCc');
        var sendCcList = document.getElementById('sendCcChipList');
        var sendCcInput = document.getElementById('sendCcChipInput');
        var sendCcBox = document.getElementById('sendCcChipBox');
        var sendCcSuggest = document.getElementById('sendCcSuggest');
        var sendQuoteModal = document.getElementById('sendQuotationModal');
        var sendQuoteForm = document.getElementById('sendQuotationForm');
        var sendPrimaryEmail = document.getElementById('sendQuotationPrimaryEmail');
        var gmailContacts = @json($gmailContacts);
        var suggestIndex = -1;
        var ccSuggestIndex = -1;

        function lockedSendEmail() {
            return String(sendPrimaryEmail && sendPrimaryEmail.value ? sendPrimaryEmail.value : '').trim().toLowerCase();
        }

        function withLockedEmail(emails) {
            var locked = lockedSendEmail();
            emails = emails.filter(function (item) { return item && item !== locked; });
            if (locked && locked.indexOf('@') !== -1) {
                emails.unshift(locked);
            }
            return emails;
        }

        function parseSendEmails(raw) {
            return String(raw || '')
                .split(/[\s,;]+/)
                .map(function (item) { return item.trim().toLowerCase(); })
                .filter(function (item, index, arr) {
                    return item.indexOf('@') !== -1 && arr.indexOf(item) === index;
                });
        }

        function currentSendEmails() {
            return withLockedEmail(parseSendEmails(sendEmailSource ? sendEmailSource.value : ''));
        }

        function syncSendEmailSource(emails) {
            if (sendEmailSource) {
                sendEmailSource.value = withLockedEmail(emails).join('\n');
            }
        }

        function hideSuggest() {
            if (sendEmailSuggest) {
                sendEmailSuggest.hidden = true;
                sendEmailSuggest.innerHTML = '';
            }
            suggestIndex = -1;
        }

        function hideCcSuggest() {
            if (sendCcSuggest) {
                sendCcSuggest.hidden = true;
                sendCcSuggest.innerHTML = '';
            }
            ccSuggestIndex = -1;
        }

        function currentCcEmails() {
            return parseSendEmails(sendCcSource ? sendCcSource.value : '');
        }

        function syncCcSource(emails) {
            if (sendCcSource) {
                sendCcSource.value = emails.join('\n');
            }
        }

        function markTeamPicks() {
            var selected = currentCcEmails();
            document.querySelectorAll('.gmail-team-chip').forEach(function (btn) {
                btn.classList.toggle('is-added', selected.indexOf(btn.dataset.email) !== -1);
            });
        }

        function renderCcChips() {
            if (!sendCcList || !sendCcSource) {
                return;
            }
            var emails = currentCcEmails();
            syncCcSource(emails);
            sendCcList.innerHTML = '';
            emails.forEach(function (email) {
                var info = contactFor(email);
                var chip = document.createElement('span');
                chip.className = 'gmail-chip';
                chip.innerHTML = '<span class="gmail-chip-avatar"></span><span class="gmail-chip-text"></span><button type="button" aria-label="Remove">&times;</button>';
                chip.querySelector('.gmail-chip-avatar').textContent = String(info.name || email).charAt(0).toUpperCase();
                chip.querySelector('.gmail-chip-text').textContent = info.name && info.name !== email ? info.name : email;
                chip.title = email;
                chip.querySelector('button').addEventListener('click', function (e) {
                    e.stopPropagation();
                    syncCcSource(emails.filter(function (item) { return item !== email; }));
                    renderCcChips();
                    if (sendCcInput) sendCcInput.focus();
                });
                sendCcList.appendChild(chip);
            });
            markTeamPicks();
        }

        function addCcEmails(raw) {
            var next = parseSendEmails(currentCcEmails().join('\n') + '\n' + raw)
                .filter(function (email) { return currentSendEmails().indexOf(email) === -1; });
            syncCcSource(next);
            renderCcChips();
            hideCcSuggest();
        }

        function showCcSuggest(query) {
            if (!sendCcSuggest) {
                return;
            }
            query = String(query || '').trim().toLowerCase();
            var selected = currentCcEmails().concat(currentSendEmails());
            var matches = gmailContacts.filter(function (row) {
                if (selected.indexOf(row.email) !== -1) {
                    return false;
                }
                if (!query) {
                    return false;
                }
                return String(row.email).indexOf(query) !== -1
                    || String(row.name || '').toLowerCase().indexOf(query) !== -1
                    || String(row.meta || '').toLowerCase().indexOf(query) !== -1;
            }).slice(0, 8);
            sendCcSuggest.innerHTML = '';
            if (!matches.length) {
                hideCcSuggest();
                return;
            }
            matches.forEach(function (row, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.email = row.email;
                if (i === 0) btn.className = 'active';
                btn.innerHTML = '<span class="gmail-suggest-avatar"></span><span><strong></strong><small></small></span>';
                btn.querySelector('.gmail-suggest-avatar').textContent = String(row.name || row.email).charAt(0).toUpperCase();
                btn.querySelector('strong').textContent = row.name || row.email;
                btn.querySelector('small').textContent = (row.meta ? row.meta + ' · ' : '') + row.email;
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    addCcEmails(row.email);
                    sendCcInput.value = '';
                    sendCcInput.focus();
                });
                sendCcSuggest.appendChild(btn);
            });
            ccSuggestIndex = 0;
            sendCcSuggest.hidden = false;
        }

        function contactFor(email) {
            return gmailContacts.find(function (row) {
                return String(row.email).toLowerCase() === String(email).toLowerCase();
            }) || { email: email, name: email, meta: '' };
        }

        function renderSendEmailChips() {
            if (!sendEmailList || !sendEmailSource) {
                return;
            }
            var emails = currentSendEmails();
            syncSendEmailSource(emails);
            sendEmailList.innerHTML = '';
            emails.forEach(function (email) {
                var info = contactFor(email);
                var chip = document.createElement('span');
                var isLocked = email === lockedSendEmail();
                chip.className = isLocked ? 'gmail-chip gmail-chip-locked' : 'gmail-chip';
                chip.innerHTML = '<span class="gmail-chip-avatar"></span><span class="gmail-chip-text"></span>' + (isLocked ? '' : '<button type="button" aria-label="Remove">&times;</button>');
                chip.querySelector('.gmail-chip-avatar').textContent = String(info.name || email).charAt(0).toUpperCase();
                chip.querySelector('.gmail-chip-text').textContent = info.name && info.name !== email ? info.name : email;
                chip.title = isLocked ? email + ' (required)' : email;
                var removeBtn = chip.querySelector('button');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        syncSendEmailSource(emails.filter(function (item) { return item !== email; }));
                        renderSendEmailChips();
                        if (sendEmailInput) sendEmailInput.focus();
                    });
                }
                sendEmailList.appendChild(chip);
            });
        }

        function addSendEmails(raw) {
            var next = parseSendEmails(currentSendEmails().join('\n') + '\n' + raw);
            syncSendEmailSource(next);
            renderSendEmailChips();
            hideSuggest();
        }

        function showSuggest(query) {
            if (!sendEmailSuggest) {
                return;
            }
            query = String(query || '').trim().toLowerCase();
            var selected = currentSendEmails();
            var matches = gmailContacts.filter(function (row) {
                if (selected.indexOf(row.email) !== -1) {
                    return false;
                }
                if (!query) {
                    return false;
                }
                return String(row.email).indexOf(query) !== -1 || String(row.name || '').toLowerCase().indexOf(query) !== -1;
            }).slice(0, 8);
            sendEmailSuggest.innerHTML = '';
            if (!matches.length) {
                hideSuggest();
                return;
            }
            matches.forEach(function (row, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.email = row.email;
                if (i === 0) btn.className = 'active';
                btn.innerHTML = '<span class="gmail-suggest-avatar"></span><span><strong></strong><small></small></span>';
                btn.querySelector('.gmail-suggest-avatar').textContent = String(row.name || row.email).charAt(0).toUpperCase();
                btn.querySelector('strong').textContent = row.name || row.email;
                btn.querySelector('small').textContent = row.email;
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    addSendEmails(row.email);
                    sendEmailInput.value = '';
                    sendEmailInput.focus();
                });
                sendEmailSuggest.appendChild(btn);
            });
            suggestIndex = 0;
            sendEmailSuggest.hidden = false;
        }

        function pickSuggest(dir) {
            var items = sendEmailSuggest ? sendEmailSuggest.querySelectorAll('button') : [];
            if (!items.length) {
                return;
            }
            suggestIndex = (suggestIndex + dir + items.length) % items.length;
            items.forEach(function (item, i) {
                item.classList.toggle('active', i === suggestIndex);
            });
        }

        if (sendEmailInput) {
            sendEmailInput.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    pickSuggest(1);
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    pickSuggest(-1);
                    return;
                }
                if (e.key === 'Enter' || e.key === 'Tab' || e.key === ',') {
                    var active = sendEmailSuggest && !sendEmailSuggest.hidden
                        ? sendEmailSuggest.querySelector('button.active')
                        : null;
                    if (active) {
                        e.preventDefault();
                        addSendEmails(active.dataset.email);
                        sendEmailInput.value = '';
                        return;
                    }
                    if (sendEmailInput.value.trim()) {
                        e.preventDefault();
                        addSendEmails(sendEmailInput.value);
                        sendEmailInput.value = '';
                    }
                }
                if (e.key === 'Backspace' && !sendEmailInput.value) {
                    var emails = currentSendEmails();
                    if (emails.length && emails[emails.length - 1] !== lockedSendEmail()) {
                        emails.pop();
                        syncSendEmailSource(emails);
                        renderSendEmailChips();
                    }
                }
            });
            sendEmailInput.addEventListener('input', function () {
                showSuggest(sendEmailInput.value);
            });
            sendEmailInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (sendEmailInput.value.trim() && sendEmailInput.value.indexOf('@') !== -1) {
                        addSendEmails(sendEmailInput.value);
                        sendEmailInput.value = '';
                    }
                    hideSuggest();
                }, 120);
            });
            sendEmailInput.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text');
                if (text && /[@,;\s]/.test(text)) {
                    e.preventDefault();
                    addSendEmails(text);
                    sendEmailInput.value = '';
                }
            });
        }
        if (sendEmailBox && sendEmailInput) {
            sendEmailBox.addEventListener('click', function () {
                sendEmailInput.focus();
            });
        }
        if (sendCcInput) {
            sendCcInput.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var items = sendCcSuggest ? sendCcSuggest.querySelectorAll('button') : [];
                    if (!items.length) return;
                    ccSuggestIndex = (ccSuggestIndex + 1 + items.length) % items.length;
                    items.forEach(function (item, i) { item.classList.toggle('active', i === ccSuggestIndex); });
                    return;
                }
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var upItems = sendCcSuggest ? sendCcSuggest.querySelectorAll('button') : [];
                    if (!upItems.length) return;
                    ccSuggestIndex = (ccSuggestIndex - 1 + upItems.length) % upItems.length;
                    upItems.forEach(function (item, i) { item.classList.toggle('active', i === ccSuggestIndex); });
                    return;
                }
                if (e.key === 'Enter' || e.key === 'Tab' || e.key === ',') {
                    var active = sendCcSuggest && !sendCcSuggest.hidden
                        ? sendCcSuggest.querySelector('button.active')
                        : null;
                    if (active) {
                        e.preventDefault();
                        addCcEmails(active.dataset.email);
                        sendCcInput.value = '';
                        return;
                    }
                    if (sendCcInput.value.trim()) {
                        e.preventDefault();
                        addCcEmails(sendCcInput.value);
                        sendCcInput.value = '';
                    }
                }
                if (e.key === 'Backspace' && !sendCcInput.value) {
                    var emails = currentCcEmails();
                    if (emails.length) {
                        emails.pop();
                        syncCcSource(emails);
                        renderCcChips();
                    }
                }
            });
            sendCcInput.addEventListener('input', function () {
                showCcSuggest(sendCcInput.value);
            });
            sendCcInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (sendCcInput.value.trim() && sendCcInput.value.indexOf('@') !== -1) {
                        addCcEmails(sendCcInput.value);
                        sendCcInput.value = '';
                    }
                    hideCcSuggest();
                }, 120);
            });
            sendCcInput.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text');
                if (text && /[@,;\s]/.test(text)) {
                    e.preventDefault();
                    addCcEmails(text);
                    sendCcInput.value = '';
                }
            });
        }
        if (sendCcBox && sendCcInput) {
            sendCcBox.addEventListener('click', function () {
                sendCcInput.focus();
            });
        }
        document.querySelectorAll('.gmail-team-chip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var email = btn.dataset.email;
                if (currentCcEmails().indexOf(email) !== -1) {
                    syncCcSource(currentCcEmails().filter(function (item) { return item !== email; }));
                    renderCcChips();
                    return;
                }
                addCcEmails(email);
            });
        });
        if (sendQuoteForm) {
            sendQuoteForm.addEventListener('submit', function () {
                if (sendEmailInput && sendEmailInput.value.trim()) {
                    addSendEmails(sendEmailInput.value);
                    sendEmailInput.value = '';
                }
                if (sendCcInput && sendCcInput.value.trim()) {
                    addCcEmails(sendCcInput.value);
                    sendCcInput.value = '';
                }
            });
        }
        if (sendQuoteModal) {
            new MutationObserver(function () {
                if (sendQuoteModal.classList.contains('active')) {
                    if (sendCcSource) {
                        sendCcSource.value = '';
                    }
                    renderSendEmailChips();
                    renderCcChips();
                    hideSuggest();
                    hideCcSuggest();
                    if (sendEmailInput) {
                        sendEmailInput.focus();
                    }
                }
            }).observe(sendQuoteModal, { attributes: true, attributeFilter: ['class'] });
        }
    });
</script>

</body>

</html>
