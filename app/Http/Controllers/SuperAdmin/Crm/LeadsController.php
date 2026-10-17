<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\CrmDesignation;
use App\Models\CrmLeadBifurcation;
use App\Models\CrmLeadPriority;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadService;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\PvSetting;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadsController extends Controller
{
    /**
     * Get only active records
     */
    protected function activeOnlyQuery($query, string $table)
    {
        if (Schema::hasColumn($table, 'is_active')) {
            return $query->where('is_active', 1);
        }

        if (Schema::hasColumn($table, 'status')) {
            return $query->whereIn(
                'status',
                ['active', 1, true, '1']
            );
        }

        return $query;
    }

    /**
     * Lead Listing
     */
    public function index(Request $request)
    {
        $query = Lead::query();

        if ($request->filled('search')) {

        $search = $request->search;
    
        $query->where(function ($q) use ($search) {
    
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('company_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('mobile', 'ilike', "%{$search}%")
                ->orWhere('gstin', 'ilike', "%{$search}%");
        });
    }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'Quote Send') {
                $query->whereIn('lead_status', ['Quote Send', 'Quote Sent', 'Quotation Sent']);
            } else {
                $query->where('lead_status', $status);
            }
        }

        if ($request->filled('source')) {
            $source = $request->source;
            if (in_array($source, ['JustDial', 'JD', 'Just Dial'], true)) {
                $query->whereIn('lead_source', ['JustDial', 'JD', 'Just Dial']);
            } else {
                $query->where('lead_source', $source);
            }
        }

        if ($request->filled('assigned_to')) {
            $assigned = $request->assigned_to;
            $employee = CrmUser::query()
                ->where('role', 'executive')
                ->where(function ($q) use ($assigned) {
                    $q->where('name', $assigned);
                    if (is_numeric($assigned)) {
                        $q->orWhere('id', (int) $assigned);
                    }
                })
                ->first();

            if ($employee) {
                $query->forEmployee($employee);
            } else {
                $query->where('assigned_to', $assigned);
            }
        }

        if ($request->filled('lead_priority') || $request->filled('lead_category')) {
            $query->where('lead_category', $request->input('lead_priority', $request->lead_category));
        }

        if ($request->filled('lead_bifurcation')) {
            $query->where('lead_bifurcation', $request->lead_bifurcation);
        }

        $dateColumn = match ($request->get('date_type')) {
            'follow_up' => 'follow_up_date',
            'updated' => 'updated_at',
            default => 'created_at',
        };

        if ($request->filled('from')) {
            $query->whereDate($dateColumn, '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate($dateColumn, '<=', $request->to);
        }

        if ($request->attributes->get('leads_show_all')) {
            $perPage = max((clone $query)->count(), 1);
        } else {
            $perPage = (int) $request->input('per_page', 10);
            $perPage = max(1, min($perPage, 100));
        }

        $page = max(1, (int) $request->input('page', 1));

        $leads = $query
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $employees = $this->crmAssigneeNames();

        $leadStats = [
            'total' => Lead::count(),
            'new' => Lead::where('lead_status', 'New')->count(),
            'qualified' => Lead::where('lead_status', 'Qualified')->count(),
            'won' => Lead::where('lead_status', 'Won')->count(),
            'lost' => Lead::where('lead_status', 'Lost')->count(),
        ];
        extract($this->crmLeadFormOptions());

        return view(
            'superadmin.crm.leads.index',
            compact('leads', 'employees', 'leadStats', 'leadSources', 'leadStatuses', 'leadPriorities', 'leadCategories')
        );
    }

    /**
     * Add Lead Page
     */
    public function create()
    {
        /*
     * Active Providers 
     */
        $providers = $this->activeOnlyQuery(
            Provider::query(),
            'providers'
        )
            ->orderBy('name')
            ->get([
                'id',
                'name'
            ]);

        /*
     * Active Plans 
     */
        $plans = $this->activeOnlyQuery(
            Plan::query(),
            'plans'
        )
            ->whereIn(
                'provider_id',
                $providers->pluck('id')
            )
            ->orderBy('name')
            ->get([
                'id',
                'provider_id',
                'name',
                'title',
                'slug',
                'actual_price'
            ]);

        /*
     * Group plans by provider
     */
        $providerPlans = $plans
            ->groupBy('provider_id')
            ->map(function ($plans) {
                return $plans->map(function ($plan) {

                    $rate = $plan->actual_price > 0
                        ? $plan->actual_price
                        : ($plan->price > 0
                            ? $plan->price
                            : 0);

                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'title' => $plan->title,
                        'sku' => $plan->slug,
                        'slug' => $plan->slug,
                        'actual_price' => (float) $plan->actual_price, // Store the actual price
                        'rate' => (float) $rate, // Store the calculated rate
                    ];
                })->values();
            })
            ->toArray();

        $assignees = $this->crmAssigneeNames();
        extract($this->crmLeadFormOptions());

        return view(
            'superadmin.crm.leads.create',
            compact(
                'providers',
                'providerPlans',
                'assignees',
                'leadSources',
                'leadStatuses',
                'leadPriorities',
                'leadCategories',
                'leadBifurcations',
                'designations'
            )
        );
    }

    public function checkDomain(Request $request)
    {
        $domain = $this->normalizeDomain($request->query('domain'));
        $ignoreId = $request->query('ignore_id');

        if (!$domain || !str_contains($domain, '.')) {
            return response()->json([
                'ok' => false,
                'available' => false,
                'invalid' => true,
                'message' => 'Enter a valid domain (example.com)',
            ]);
        }

        if ($this->domainExistsOnLead($domain, $ignoreId)) {
            return response()->json([
                'ok' => true,
                'available' => false,
                'registered' => true,
                'message' => 'This domain is already saved on another lead.',
            ]);
        }

        return response()->json($this->lookupDomainAvailability($domain));
    }

    /**
     * Store Lead
     */
    public function store(Request $request)
    {
        if (!$request->filled('lead_category') && $request->filled('lead_priority')) {
            $request->merge(['lead_category' => $request->input('lead_priority')]);
        }

        $request->merge([
            'company_name' => $request->filled('company_name') ? $request->company_name : null,
            'gstin' => $request->filled('gstin') ? $request->gstin : null,
            'domain' => $this->normalizeDomain($request->input('domain')),
        ]);

        try {
            DB::connection('pgsql_crm')->statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_gstin_key');
            DB::connection('pgsql_crm')->statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_company_name_key');
        } catch (\Throwable $e) {
            // ignore
        }

        $validated = $request->validate([

            /*
             * Customer — name, email, mobile required (call capture)
             */
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'salutation' => [
                'required',
                'in:Mr,Mrs,Ms'
            ],

            'designation' => [
                'nullable',
                'string',
                'max:255'
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(
                    'pgsql_crm.leads',
                    'email'
                ),
            ],

            'mobile' => [
                'required',
                'regex:/^[0-9]{6,15}$/',
                Rule::unique(
                    'pgsql_crm.leads',
                    'mobile'
                ),
            ],
            'mobile_code' => [
                'nullable',
                'string',
                'max:8',
            ],

            'company_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'industry' => [
                'nullable',
                'string',
                'max:255'
            ],

            'no_of_employees' => [
                'nullable',
                'integer',
                'min:0'
            ],

            'gstin' => array_values(array_filter([
                'nullable',
                'string',
                'max:15',
                $request->input('business_type') === 'existing'
                    ? null
                    : Rule::unique('pgsql_crm.leads', 'gstin'),
            ])),

            /*
             * Lead
             */
            'lead_category' => [
                'nullable',
                'string',
                'max:100'
            ],

            'lead_status' => [
                'required',
                'string',
                'max:100'
            ],

            'business_type' => [
                'nullable',
                'in:new,existing'
            ],

            'lead_source' => [
                'nullable',
                'string',
                'max:100'
            ],

            'lead_bifurcation' => [
                'nullable',
                'string',
                'max:100'
            ],

            'assigned_to' => [
                'nullable',
                'string',
                'max:255'
            ],

            /*
             * Address
             */
            'mailing_address' => [
                'nullable',
                'string'
            ],

            'country' => [
                'nullable',
                'string',
                'max:100'
            ],

            'state' => [
                'nullable',
                'string',
                'max:100'
            ],

            'city' => [
                'nullable',
                'string',
                'max:100'
            ],

            'pincode' => [
                'nullable',
                'string',
                'max:20'
            ],

            'domain_type' => [
                'nullable',
                'in:existing,new'
            ],

            'domain' => [
                'nullable',
                'string',
                'max:255'
            ],

            'domain_rate' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'domain_period' => [
                'nullable',
                'string',
                'max:50'
            ],

            'sac' => [
                'nullable',
                'string',
                'max:50'
            ],

            'domain_start' => [
                'nullable',
                'date'
            ],

            'domain_end' => [
                'nullable',
                'date'
            ],

            'domain_renewal_date' => [
                'nullable',
                'date'
            ],

            /*
             * Follow Up
             */
            'follow_up_date' => [
                'nullable',
                'date'
            ],

            'follow_up_note' => [
                'nullable',
                'string'
            ],

            /*
             * Documents
             */
            'documents' => [
                'nullable',
                'array',
                'max:10'
            ],

            'documents.*' => [
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:10240'
            ],
            'document_types' => [
                'nullable',
                'array'
            ],
            'document_types.*' => [
                'nullable',
                'string',
                'max:100'
            ],

            /*
             * Plans
             */
            'plans' => [
                'nullable',
                'array'
            ],

            'plans.*.provider_id' => [
                'nullable',
                'integer'
            ],

            'plans.*.plan_id' => [
                'nullable',
                'integer'
            ],

            'plans.*.quote_period' => [
                'nullable',
                'string',
                'max:100'
            ],

            'plans.*.quantity' => [
                'nullable',
                'integer',
                'min:0'
            ],

            /*
             * Contacts
             */
            'contacts' => [
                'nullable',
                'array'
            ],

            'contacts.*.name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'contacts.*.designation' => [
                'nullable',
                'string',
                'max:255'
            ],

            'contacts.*.email' => [
                'nullable',
                'email',
                'max:255'
            ],

            'contacts.*.mobile' => [
                'nullable',
                'regex:/^[0-9]{6,15}$/'
            ],

            'vendor_services' => [
                'nullable',
                'array'
            ],
            'vendor_services.*.domain' => [
                'nullable',
                'string',
                'max:255'
            ],
            'vendor_services.*.provider_id' => [
                'nullable',
                'integer'
            ],
            'vendor_services.*.plan_id' => [
                'nullable',
                'integer'
            ],
            'vendor_services.*.quantity' => [
                'nullable',
                'integer',
                'min:1'
            ],
            'vendor_services.*.rate' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'vendor_services.*.renewal_date' => [
                'nullable',
                'date'
            ],
        ], [
            'name.required' => 'Name is required.',
            'salutation.required' => 'Title is required.',
            'salutation.in' => 'Select Mr, Mrs or Ms.',
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email.',
            'email.unique' => 'A lead with this email already exists.',
            'mobile.required' => 'Mobile No. is required.',
            'mobile.regex' => 'Enter a valid mobile number.',
            'mobile.unique' => 'A lead with this mobile number already exists.',
            'contacts.*.mobile.regex' => 'Enter a valid contact mobile number.',
            'lead_status.required' => 'Lead Status is required.',
            'gstin.unique' => 'This GSTIN is already used. Choose Existing Business to add another contact.',
        ]);

        if ($request->input('business_type') !== 'existing') {
            $this->assertLeadDomainAllowed($request);
            $validated['domain'] = $this->normalizeDomain($request->input('domain'));
            $validated['domain_type'] = $request->input('domain_type') ?: null;
            if ($validated['domain_type'] === 'existing') {
                $validated['domain_rate'] = null;
                $validated['domain_period'] = null;
                $validated['sac'] = null;
                $validated['domain_start'] = null;
                $validated['domain_end'] = null;
            } else {
                $validated['domain_renewal_date'] = null;
            }
        }

        if ($request->input('business_type') === 'existing') {
            $vendorDomain = null;
            foreach ($request->input('vendor_services', []) as $row) {
                $vendorDomain = $this->normalizeDomain($row['domain'] ?? null);
                if ($vendorDomain) {
                    break;
                }
            }
            $domain = $this->normalizeDomain($request->input('domain')) ?: $vendorDomain;
            $type = (string) $request->input('domain_type', '');
            if ($domain && $type === '') {
                $type = 'existing';
            }
            $request->merge([
                'domain' => $domain,
                'domain_type' => $type !== '' ? $type : null,
            ]);
            $validated['domain'] = $domain;
            $validated['domain_type'] = $type !== '' ? $type : null;
            if ($type !== 'new') {
                $validated['domain_rate'] = null;
                $validated['domain_period'] = null;
                $validated['sac'] = null;
                $validated['domain_start'] = null;
                $validated['domain_end'] = null;
            } else {
                $validated['domain_renewal_date'] = null;
            }
            if ($domain) {
                $this->assertLeadDomainAllowed($request);
                $validated['domain'] = $this->normalizeDomain($request->input('domain')) ?: $domain;
                $validated['domain_type'] = $request->input('domain_type') ?: ($domain ? 'existing' : null);
                if (($validated['domain_type'] ?? '') !== 'new') {
                    $validated['domain_rate'] = null;
                    $validated['domain_period'] = null;
                    $validated['sac'] = null;
                    $validated['domain_start'] = null;
                    $validated['domain_end'] = null;
                } else {
                    $validated['domain_renewal_date'] = null;
                }
            }
        }

        $optionalColumns = [
            'company_name',
            'lead_category',
            'country',
            'assigned_to',
            'lead_source',
            'lead_bifurcation',
            'designation',
            'industry',
            'gstin',
            'mailing_address',
            'state',
            'city',
            'pincode',
            'follow_up_date',
            'follow_up_note',
            'no_of_employees',
        ];

        foreach ($optionalColumns as $column) {
            if (Schema::connection('pgsql_crm')->hasColumn('leads', $column)) {
                try {
                    DB::connection('pgsql_crm')->statement(
                        'ALTER TABLE leads ALTER COLUMN ' . $column . ' DROP NOT NULL'
                    );
                } catch (\Throwable $e) {
                    // Column may already allow null
                }
            }
        }

        $this->checkPlans($request->input('plans', []));
        $this->ensureLeadColumn('lead_bifurcation', 'varchar(100) NULL');

        DB::connection('pgsql_crm')->transaction(
            function () use (
                $request,
                $validated
            ) {

                /*
                 * Lead Data
                 */
                $leadData = $validated;

                unset(
                    $leadData['plans'],
                    $leadData['contacts'],
                    $leadData['documents'],
                    $leadData['document_types'],
                    $leadData['vendor_services']
                );

                $leadData['business_type'] = $request->input('business_type') === 'existing' ? 'existing' : 'new';
                $leadData['lead_bifurcation'] = $request->filled('lead_bifurcation')
                    ? trim((string) $request->input('lead_bifurcation'))
                    : null;
                $this->ensureLeadColumn('business_type', 'varchar(20) NULL');
                $this->ensureLeadColumn('domain_renewal_date', 'date NULL');
                $this->ensureLeadColumn('salutation', 'varchar(10) NULL');
                $this->ensureLeadColumn('mobile_code', 'varchar(8) NULL');

                if (($leadData['domain_type'] ?? '') !== 'new') {
                    $leadData['domain_rate'] = null;
                    $leadData['domain_period'] = null;
                    $leadData['sac'] = null;
                    $leadData['domain_start'] = null;
                    $leadData['domain_end'] = null;
                } else {
                    $leadData['domain_renewal_date'] = null;
                }

                if (empty($leadData['domain'])) {
                    $leadData['domain'] = null;
                    $leadData['domain_type'] = null;
                    $leadData['domain_renewal_date'] = null;
                } elseif (empty($leadData['domain_type'])) {
                    $leadData['domain'] = null;
                    $leadData['domain_type'] = null;
                    $leadData['domain_renewal_date'] = null;
                }

                foreach (['sac', 'domain_start', 'domain_end', 'domain_renewal_date'] as $column) {
                    if (!Schema::connection('pgsql_crm')->hasColumn('leads', $column)) {
                        unset($leadData[$column]);
                    }
                }

                /*
                 * Upload Documents
                 */
                $documents = [];
                $documentTypes = $request->input('document_types', []);

                if ($request->hasFile('documents')) {

                    foreach (
                        $request->file('documents')
                        as $index => $file
                    ) {

                        if (!$file->isValid()) {
                            continue;
                        }

                        $filename =
                            'lead_' .
                            time() .
                            '_' .
                            uniqid() .
                            '.' .
                            $file->getClientOriginalExtension();

                        $path = $file->storeAs(
                            'crm/leads/documents',
                            $filename,
                            'public'
                        );

                        $documents[] = [
                            'type' => $documentTypes[$index] ?? 'Other',
                            'path' => $path,
                            'name' => $file->getClientOriginalName(),
                        ];
                    }
                }

                /*
                 * Save Document Paths
                 */
                $leadData['document'] =
                    !empty($documents)
                    ? $documents
                    : null;

                /*
                 * Create Lead
                 */
                $lead = Lead::create($leadData);
                $this->saveLeadBifurcation($lead, $request);

                /*
                 * Additional Contacts
                 */
                foreach (
                    $request->input('contacts', [])
                    as $contact
                ) {

                    if (
                        empty($contact['name']) &&
                        empty($contact['designation']) &&
                        empty($contact['email']) &&
                        empty($contact['mobile'])
                    ) {
                        continue;
                    }

                    LeadContact::create([

                        'lead_id' => $lead->id,

                        'name' =>
                        $contact['name'] ?? null,

                        'designation' =>
                        $contact['designation'] ?? null,

                        'email' =>
                        $contact['email'] ?? null,

                        'mobile' =>
                        $contact['mobile'] ?? null,
                    ]);
                }

                /*
                 * Plans / Services
                 */
                $this->ensureLeadServiceColumns();
                $isExistingBusiness = $request->input('business_type') === 'existing';

                if ($isExistingBusiness) {
                    foreach ($request->input('vendor_services', []) as $row) {
                        if (empty($row['provider_id']) || empty($row['plan_id'])) {
                            continue;
                        }
                        $plan = Plan::where('id', $row['plan_id'])
                            ->where('provider_id', $row['provider_id'])
                            ->first();
                        if (!$plan) {
                            continue;
                        }
                        $this->storeLeadServiceRow($lead, [
                            'provider_id' => $plan->provider_id,
                            'plan_id' => $plan->id,
                            'provider' => optional($plan->provider)->name,
                            'plan' => $plan->name ?: $plan->title,
                            'sku' => $plan->slug,
                            'quantity' => $row['quantity'] ?? null,
                            'rate' => $row['rate'] ?? null,
                            'renewal_date' => $row['renewal_date'] ?? null,
                            'domain' => $this->normalizeDomain($row['domain'] ?? null),
                            'is_info' => true,
                        ]);
                    }
                }

                foreach ($request->input('plans', []) as $planData) {
                    if (empty($planData['provider_id']) || empty($planData['plan_id'])) {
                        continue;
                    }

                    $plan = Plan::where('id', $planData['plan_id'])
                        ->where('provider_id', $planData['provider_id'])
                        ->first();

                    if (!$plan) {
                        continue;
                    }

                    $this->storeLeadServiceRow($lead, [
                        'provider_id' => $plan->provider_id,
                        'plan_id' => $plan->id,
                        'provider' => optional($plan->provider)->name,
                        'plan' => $plan->name ?: $plan->title,
                        'quote_period' => $planData['quote_period'] ?? null,
                        'sku' => $planData['sku'] ?? $plan->slug,
                        'quantity' => $planData['quantity'] ?? null,
                        'rate' => $plan->actual_price > 0 ? $plan->actual_price : ($plan->price > 0 ? $plan->price : 0),
                        'is_info' => false,
                    ]);
                }
            }
        );

        return redirect()
            ->route('crm.leads')
            ->with(
                'success',
                'Lead added successfully.'
            );
    }

    /**
     * Lead Details
     */
    public function show(Lead $lead)
    {
        $lead->load([
            'contacts',
            'services.provider',
            'services.plan'
        ]);

        $providers = $this->activeOnlyQuery(Provider::query(), 'providers')
            ->orderBy('name')
            ->get(['id', 'name']);

        $providerPlans = $this->providerPlansFor($providers);

        $quotations = Quotation::where('lead_id', $lead->id)->latest()->get();

        $history = collect();
        $history->push([
            'at' => $lead->created_at,
            'text' => 'Lead created' . ($lead->lead_source ? ' from ' . $lead->lead_source : ''),
        ]);
        foreach ($lead->contacts as $contact) {
            $history->push([
                'at' => $contact->created_at,
                'text' => 'Contact added: ' . ($contact->name ?: 'Unnamed'),
            ]);
        }
        foreach ($lead->services as $service) {
            $planName = $service->plan->name ?? $service->plan->title ?? 'Service';
            $history->push([
                'at' => $service->created_at,
                'text' => 'Service added: ' . $planName,
            ]);
        }
        foreach ($quotations as $quotation) {
            $history->push([
                'at' => $quotation->created_at,
                'text' => 'Quotation created: ' . $quotation->quotation_no,
            ]);
        }
        if ($lead->follow_up_date) {
            $history->push([
                'at' => $lead->updated_at,
                'text' => 'Follow-up set for ' . $lead->follow_up_date->format('d M Y, g:i A'),
            ]);
        }
        $history = $history->filter(fn ($row) => !empty($row['at']))->sortByDesc('at')->values();

        $assignees = $this->crmAssigneeNames();
        extract($this->crmLeadFormOptions($lead));

        return view(
            'superadmin.crm.leads.show',
            compact('lead', 'providers', 'providerPlans', 'history', 'assignees', 'leadSources', 'leadStatuses', 'leadPriorities', 'leadCategories', 'leadBifurcations', 'designations')
        );
    }

    public function assignEmployees(Request $request)
    {
        $data = $request->validate([
            'assigned_to' => 'required|string|max:255',
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'integer',
        ]);

        Lead::whereIn('id', $data['lead_ids'])->update([
            'assigned_to' => $data['assigned_to'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function download(Request $request)
    {
        $data = $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'integer',
        ]);

        $leads = Lead::whereIn('id', $data['lead_ids'])->orderBy('id')->get();
        $filename = 'leads-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Company', 'Name', 'Designation', 'Email', 'Mobile', 'Status', 'Source', 'Assigned To', 'Created']);
            foreach ($leads as $lead) {
                fputcsv($out, [
                    $lead->id,
                    $lead->company_name,
                    $lead->name,
                    $lead->designation,
                    $lead->email,
                    $lead->mobile,
                    $lead->lead_status,
                    $lead->lead_source,
                    $lead->assigned_to,
                    optional($lead->created_at)->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'integer',
        ]);

        $count = Lead::whereIn('id', $data['lead_ids'])->count();
        Lead::whereIn('id', $data['lead_ids'])->delete();

        return back()->with('success', $count . ' lead(s) deleted.');
    }

    public function update(Request $request, Lead $lead)
    {
        if (!$request->filled('lead_category') && $request->filled('lead_priority')) {
            $request->merge(['lead_category' => $request->input('lead_priority')]);
        }

        $request->merge([
            'company_name' => $request->filled('company_name') ? $request->company_name : null,
            'gstin' => $request->filled('gstin') ? $request->gstin : null,
            'domain' => $this->normalizeDomain($request->input('domain')),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'salutation' => 'required|in:Mr,Mrs,Ms',
            'designation' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('pgsql_crm.leads', 'email')->ignore($lead->id),
            ],
            'mobile' => [
                'required',
                'regex:/^[0-9]{6,15}$/',
                Rule::unique('pgsql_crm.leads', 'mobile')->ignore($lead->id),
            ],
            'mobile_code' => 'nullable|string|max:6',
            'company_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'industry' => 'nullable|string|max:255',
            'no_of_employees' => 'nullable|integer|min:0',
            'gstin' => array_values(array_filter([
                'nullable',
                'string',
                'max:15',
                $request->input('business_type') === 'existing'
                    ? null
                    : Rule::unique('pgsql_crm.leads', 'gstin')->ignore($lead->id),
            ])),
            'mailing_address' => 'nullable|string',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'assigned_to' => 'nullable|string|max:255',
            'lead_category' => 'nullable|string|max:100',
            'lead_source' => 'nullable|string|max:100',
            'lead_bifurcation' => 'nullable|string|max:100',
            'lead_status' => 'required|string|max:100',
            'business_type' => 'nullable|in:new,existing',
            'domain_type' => 'nullable|in:existing,new',
            'domain' => 'nullable|string|max:255',
            'domain_period' => 'nullable|string|max:50',
            'domain_rate' => 'nullable|numeric|min:0',
            'sac' => 'nullable|string|max:50',
            'domain_start' => 'nullable|date',
            'domain_end' => 'nullable|date',
            'domain_renewal_date' => 'nullable|date',
        ], [
            'name.required' => 'Name is required.',
            'salutation.required' => 'Title is required.',
            'salutation.in' => 'Select Mr, Mrs or Ms.',
            'email.required' => 'Email is required.',
            'mobile.required' => 'Mobile No. is required.',
            'mobile.regex' => 'Enter a valid mobile number.',
            'lead_status.required' => 'Lead Status is required.',
        ]);

        $this->assertLeadDomainAllowed($request, $lead->id);
        $data['domain'] = $this->normalizeDomain($request->input('domain'));
        $data['domain_type'] = $request->input('domain_type') ?: null;
        $data['domain_renewal_date'] = !empty($data['domain_renewal_date'])
            ? $data['domain_renewal_date']
            : null;
        $this->ensureLeadColumn('domain_renewal_date', 'date NULL');
        $this->ensureLeadColumn('salutation', 'varchar(10) NULL');
        $this->ensureLeadColumn('mobile_code', 'varchar(8) NULL');
        if ($data['domain_type'] === 'existing') {
            $data['domain_rate'] = null;
            $data['domain_period'] = null;
            $data['sac'] = null;
            $data['domain_start'] = null;
            $data['domain_end'] = null;
        } else {
            $data['domain_renewal_date'] = null;
        }

        $this->ensureLeadColumn('business_type', 'varchar(20) NULL');
        $this->ensureLeadColumn('lead_bifurcation', 'varchar(100) NULL');
        $data['lead_bifurcation'] = $request->filled('lead_bifurcation')
            ? trim((string) $request->input('lead_bifurcation'))
            : null;
        if ($request->filled('business_type')) {
            $data['business_type'] = $request->input('business_type') === 'existing' ? 'existing' : 'new';
        }

        if (($data['domain_type'] ?? '') !== 'new') {
            $data['domain_rate'] = null;
            $data['domain_period'] = null;
            $data['sac'] = null;
            $data['domain_start'] = null;
            $data['domain_end'] = null;
        } else {
            $data['domain_renewal_date'] = null;
        }

        if (empty($data['domain'])) {
            $data['domain'] = null;
            $data['domain_type'] = null;
            $data['domain_renewal_date'] = null;
        } elseif (empty($data['domain_type'])) {
            $data['domain'] = null;
            $data['domain_type'] = null;
            $data['domain_renewal_date'] = null;
        }

        foreach (['sac', 'domain_start', 'domain_end', 'domain_renewal_date'] as $column) {
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', $column)) {
                unset($data[$column]);
            }
        }

        $lead->update($data);
        $this->saveLeadBifurcation($lead, $request);

        return back()->with('success', 'Lead details saved.');
    }

    public function updateFollowUp(Request $request, Lead $lead)
    {
        if (!$request->filled('lead_category') && $request->filled('lead_priority')) {
            $request->merge(['lead_category' => $request->input('lead_priority')]);
        }

        $data = $request->validate([
            'lead_category' => 'nullable|string|max:100',
            'lead_source' => 'nullable|string|max:100',
            'lead_bifurcation' => 'nullable|string|max:100',
            'lead_status' => 'required|string|max:100',
            'assigned_to' => 'nullable|string|max:255',
            'follow_up_date' => 'nullable|date',
            'follow_up_note' => 'nullable|string',
        ], [
            'lead_status.required' => 'Lead Status is required.',
        ]);

        $this->ensureLeadColumn('lead_bifurcation', 'varchar(100) NULL');
        $lead->update($data);
        $this->saveLeadBifurcation($lead, $request);
        app(FollowUpController::class)->record($lead, $data);

        return back()->with('success', 'Follow-up saved.');
    }

    public function storeContact(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|regex:/^[0-9]{6,15}$/',
        ]);

        $lead->contacts()->create($data);

        return back()->with('success', 'Contact added.');
    }

    public function updateContact(Request $request, Lead $lead, LeadContact $contact)
    {
        if ((int) $contact->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|regex:/^[0-9]{6,15}$/',
        ]);

        $contact->update($data);

        return back()->with('success', 'Contact updated.');
    }

    public function destroyContact(Lead $lead, LeadContact $contact)
    {
        if ((int) $contact->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }

    public function storeService(Request $request, Lead $lead)
    {
        $this->saveLeadService($request, $lead);

        return back()->with('success', $request->boolean('is_info') ? 'Current service added.' : 'Service added.');
    }

    public function updateService(Request $request, Lead $lead, LeadService $leadService)
    {
        if ((int) $leadService->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $this->saveLeadService($request, $lead, $leadService);

        return back()->with('success', 'Service updated.');
    }

    public function destroyService(Lead $lead, LeadService $leadService)
    {
        if ((int) $leadService->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $leadService->delete();

        return back()->with('success', 'Service removed.');
    }

    public function storeDocuments(Request $request, Lead $lead)
    {
        $request->validate([
            'documents' => 'required|array|max:10',
            'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'document_type' => 'nullable|string|max:100',
        ], [
            'documents.required' => 'Please choose a document.',
        ]);

        $existing = $lead->document ?? [];
        if (!is_array($existing)) {
            $existing = $existing ? [$existing] : [];
        }
        $existing = array_values(array_filter($existing));
        $docType = $request->input('document_type', 'Other');

        foreach ($request->file('documents') as $file) {
            if (!$file->isValid()) {
                continue;
            }
            $filename = 'lead_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $existing[] = [
                'type' => $docType,
                'path' => $file->storeAs('crm/leads/documents', $filename, 'public'),
                'name' => $file->getClientOriginalName(),
            ];
        }

        $lead->update(['document' => $existing ?: null]);

        return back()->with('success', 'Document added.');
    }

    public function destroyDocument(Request $request, Lead $lead)
    {
        $index = (int) $request->input('index', -1);
        $existing = $lead->document ?? [];
        if (!is_array($existing)) {
            $existing = $existing ? [$existing] : [];
        }
        $existing = array_values(array_filter($existing));

        if (!isset($existing[$index])) {
            return back()->with('error', 'Document not found.');
        }

        $entry = $existing[$index];
        $path = is_array($entry) ? ($entry['path'] ?? '') : $entry;
        if ($path) {
            Storage::disk('public')->delete($path);
        }
        unset($existing[$index]);
        $lead->update(['document' => array_values($existing) ?: null]);

        return back()->with('success', 'Document removed.');
    }

    protected function saveLeadService(Request $request, Lead $lead, ?LeadService $service = null)
    {
        $isInfo = $request->boolean('is_info');
        $data = $request->validate([
            'provider_id' => 'required|integer',
            'plan_id' => 'required|integer',
            'quote_period' => $isInfo ? 'nullable|string|max:100' : 'required|string|max:100',
            'sku' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'rate' => 'nullable|numeric|min:0',
            'renewal_date' => 'nullable|date',
            'domain' => 'nullable|string|max:255',
            'is_info' => 'nullable',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        if (!$isInfo) {
            $lead->loadMissing('services.provider');
            $rows = [];
            foreach ($lead->services as $row) {
                if ($row->isCompetitorInfo()) {
                    continue;
                }
                if ($service && (int) $row->id === (int) $service->id) {
                    continue;
                }
                $rows[] = ['provider_id' => $row->provider_id, 'plan_id' => $row->plan_id];
            }
            $rows[] = ['provider_id' => $data['provider_id'], 'plan_id' => $data['plan_id']];
            $this->checkPlans($rows);
        }

        $payload = [
            'provider_id' => $data['provider_id'],
            'plan_id' => $plan->id,
            'provider' => optional($plan->provider)->name,
            'plan' => $plan->name ?: $plan->title,
            'quote_period' => $isInfo ? null : ($data['quote_period'] ?? null),
            'sku' => $data['sku'] ?: $plan->slug,
            'quantity' => $data['quantity'],
            'rate' => $data['rate'] !== null && $data['rate'] !== ''
                ? $data['rate']
                : ($plan->actual_price > 0 ? $plan->actual_price : $plan->price),
            'renewal_date' => $isInfo ? ($data['renewal_date'] ?? null) : null,
            'domain' => $isInfo ? $this->normalizeDomain($data['domain'] ?? null) : null,
            'is_info' => $isInfo,
        ];

        $this->ensureLeadServiceColumns();

        if ($isInfo && ($lead->business_type ?? '') !== 'existing') {
            $this->ensureLeadColumn('business_type', 'varchar(20) NULL');
            $lead->update(['business_type' => 'existing']);
        }

        if ($service) {
            $filtered = ['lead_id' => $lead->id];
            foreach ($payload as $col => $val) {
                if (Schema::connection('pgsql_crm')->hasColumn('lead_services', $col)) {
                    $filtered[$col] = $val;
                }
            }
            $service->update($filtered);
            return;
        }

        $this->storeLeadServiceRow($lead, $payload);
    }

    protected function providerPlansFor($providers)
    {
        $plans = $this->activeOnlyQuery(Plan::query(), 'plans')
            ->whereIn('provider_id', $providers->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'provider_id', 'name', 'title', 'slug', 'actual_price', 'price']);

        return $plans->groupBy('provider_id')->map(function ($group) {
            return $group->map(function ($plan) {
                $rate = $plan->actual_price > 0 ? $plan->actual_price : ($plan->price > 0 ? $plan->price : 0);
                return [
                    'id' => $plan->id,
                    'name' => $plan->name ?: $plan->title,
                    'sku' => $plan->slug,
                    'slug' => $plan->slug,
                    'actual_price' => (float) $plan->actual_price,
                    'rate' => (float) $rate,
                ];
            })->values();
        })->toArray();
    }

    public function uploads()
    {
        return view('superadmin.crm.leads.uploads');
    }

    public function downloadSample()
    {
        $headers = [
            'Name',
            'Salutation',
            'Designation',
            'Email',
            'Mobile',
            'Mobile Code',
            'Company Name',
            'Industry',
            'No of Employees',
            'GSTIN',
            'Mailing Address',
            'Country',
            'State',
            'City',
            'Pincode',
            'Provider',
            'SKU',
            'Quote Period',
            'Quantity',
            'Rate',
            'Contact Name',
            'Contact Designation',
            'Contact Email',
            'Contact Mobile',
            'Follow Up Date',
            'Follow Up Note',
            'Lead Priority',
            'Lead Status',
            'Lead Source',
            'Lead Bifurcation',
            'Assigned To'
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            // Add a sample row
            fputcsv($file, [
                'John Doe',
                'Mr',
                'Manager',
                'john@example.com',
                '9876543210',
                '91',
                'Example Corp',
                'IT',
                '50',
                '22AAAAA0000A1Z5',
                '1090C, Municipal no 1197/1090C, 15th Main, Mumbai',
                'India',
                'Maharashtra',
                'Mumbai',
                '400001',
                'Google Workspace',
                'business-plus',
                '3 Year',
                '50',
                '2400',
                'Jane Doe',
                'Manager',
                'jane@example.com',
                '9876543211',
                '2026-11-01',
                'Follow up note',
                'Hot',
                'New',
                'Website',
                'B2B',
                'Admin'
            ]);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_sample.csv"',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            return back()->withErrors([
                'file' => 'The file must be a CSV or Excel file (.csv, .xls, .xlsx).',
            ]);
        }

        try {
            $data = $this->uploadedFileToRows($file);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not read the uploaded file: ' . $e->getMessage());
        }

        if (count($data) < 2) {
            return back()->with('error', 'The uploaded file is empty or missing data rows.');
        }

        $rawHeaders = array_shift($data);
        $headers = [];
        foreach ($rawHeaders as $i => $h) {
            // Remove UTF-8 BOM if present
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            $headers[$i] = trim($clean);
        }
        \Log::info('CRM IMPORT HEADERS DEBUG', [
    'raw_headers' => $rawHeaders,
    'clean_headers' => $headers,
    'name_index' => $this->findColumnIndex($headers, [
        'name',
        'customer name',
        'lead name',
        'full name',
        'client name',
        'contact person'
    ]),
    'email_index' => $this->findColumnIndex($headers, [
        'email',
        'email address',
        'email id',
        'customer email'
    ]),
    'mobile_index' => $this->findColumnIndex($headers, [
        'mobile',
        'mobile no',
        'mobile number',
        'phone',
        'phone number',
        'contact no',
        'contact number',
        'mobile phone',
        'cell'
    ]),
]);

        // Map column aliases
        $colName = $this->findColumnIndex($headers, ['name', 'customer name', 'lead name', 'full name', 'client name', 'contact person']);
        $colSalutation = $this->findColumnIndex($headers, ['salutation', 'title', 'prefix']);
        $colEmail = $this->findColumnIndex($headers, ['email', 'email address', 'email id', 'customer email']);
        $colMobile = $this->findColumnIndex($headers, ['mobile', 'mobile no', 'mobile number', 'phone', 'phone number', 'contact no', 'contact number', 'mobile phone', 'cell']);
        $colMobileCode = $this->findColumnIndex($headers, ['mobile code', 'country code', 'dial code', 'std code']);
        $colCompany = $this->findColumnIndex($headers, ['company name', 'company', 'organization', 'org name', 'business name', 'firm name']);
        $colDesignation = $this->findColumnIndex($headers, ['designation', 'job title', 'role', 'position']);
        $colIndustry = $this->findColumnIndex($headers, ['industry', 'sector', 'business type']);
        $colEmployees = $this->findColumnIndex($headers, ['no of employees', 'number of employees', 'employees', 'employee count', 'staff count']);
        $colGstin = $this->findColumnIndex($headers, ['gstin', 'gst', 'gst no', 'gst number']);
        $colCategory = $this->findColumnIndex($headers, ['lead category', 'lead priority', 'category', 'priority']);
        $colStatus = $this->findColumnIndex($headers, ['lead status', 'status', 'stage']);
        $colSource = $this->findColumnIndex($headers, ['lead source', 'source', 'channel']);
        $colBifurcation = $this->findColumnIndex($headers, ['lead bifurcation', 'bifurcation']);
        $colAssigned = $this->findColumnIndex($headers, ['assigned to', 'assignee', 'assigned employee', 'employee', 'owner']);
        $colAddress = $this->findColumnIndex($headers, ['mailing address', 'address', 'street address', 'office address']);
        $colCountry = $this->findColumnIndex($headers, ['country']);
        $colState = $this->findColumnIndex($headers, ['state', 'province']);
        $colCity = $this->findColumnIndex($headers, ['city']);
        $colPincode = $this->findColumnIndex($headers, ['pincode', 'pin code', 'zip', 'zipcode', 'postal code']);
        $colFollowUpDate = $this->findColumnIndex($headers, ['follow up date', 'followup date', 'next follow up', 'follow up']);
        $colFollowUpNote = $this->findColumnIndex($headers, ['follow up note', 'followup note', 'add note', 'note', 'remarks', 'remark', 'comments']);

        $colContactName = $this->findColumnIndex($headers, ['contact name', 'name1', 'secondary contact name', 'alternate contact', 'contact person 2']);
        $colContactDesignation = $this->findColumnIndex($headers, ['contact designation', 'designation1', 'secondary designation']);
        $colContactEmail = $this->findColumnIndex($headers, ['contact email', 'email1', 'secondary email']);
        $colContactMobile = $this->findColumnIndex($headers, ['contact mobile', 'mobile1', 'mobile no1', 'contact mobile no', 'secondary mobile', 'alternate mobile']);

        $colProvider = $this->findColumnIndex($headers, ['provider', 'service provider', 'vendor']);
        $colSku = $this->findColumnIndex($headers, ['sku', 'plan sku', 'plan code', 'plan']);
        $colQuotePeriod = $this->findColumnIndex($headers, ['quote period', 'billing cycle', 'period', 'duration']);
        $colQuantity = $this->findColumnIndex($headers, ['quantity', 'qty', 'units', 'licenses']);
        $colRate = $this->findColumnIndex($headers, ['rate', 'price', 'unit price', 'cost']);

        if ($colName === null || $colEmail === null || $colMobile === null) {
            $missing = [];
            if ($colName === null) $missing[] = 'Name';
            if ($colEmail === null) $missing[] = 'Email';
            if ($colMobile === null) $missing[] = 'Mobile';
            return back()->with('error', 'Missing required column(s): ' . implode(', ', $missing) . '. Please check the column headers or download the sample CSV.');
        }

        $successCount = 0;
        $errors = [];
        $seenEmails = [];
        $seenMobiles = [];

        foreach ($data as $index => $row) {
            $rowNum = $index + 2; // Row 1 is header

            // Check if entire row is empty
            $nonEmptyCells = array_filter($row, fn ($v) => trim((string) $v) !== '');
            if (empty($nonEmptyCells)) {
                continue; // ignore blank rows
            }

            $name = trim((string) ($row[$colName] ?? ''));
            $email = strtolower(trim((string) ($row[$colEmail] ?? '')));
            $rawMobile = trim((string) ($row[$colMobile] ?? ''));
            if (stripos($rawMobile, 'e') !== false || strpos($rawMobile, '.') !== false) {
                if (is_numeric($rawMobile)) {
                    $rawMobile = sprintf('%.0f', (float) $rawMobile);
                }
            }
            $mobile = preg_replace('/[^0-9]/', '', $rawMobile);
            $company = $colCompany !== null ? trim((string) ($row[$colCompany] ?? '')) : '';
            $identifier = $company !== '' ? ($company . ($name !== '' ? ' (' . $name . ')' : '')) : ($name !== '' ? $name : 'Row ' . $rowNum);

            // Validation checks
            if ($name === '') {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => 'Customer Name is required and was empty.',
                ];
                continue;
            }

            if ($email === '') {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => 'Email address is required and was empty.',
                ];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => "Invalid email format '{$email}'.",
                ];
                continue;
            }

            if ($mobile === '') {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => 'Mobile number is required and was empty.',
                ];
                continue;
            }

            if (strlen($mobile) < 6 || strlen($mobile) > 15) {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => "Invalid mobile number '{$rawMobile}' (must be 6 to 15 digits).",
                ];
                continue;
            }

            // In-file duplicate check
            if (isset($seenEmails[$email])) {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => "Duplicate email '{$email}' in uploaded file (already in Row {$seenEmails[$email]}).",
                ];
                continue;
            }

            if (isset($seenMobiles[$mobile])) {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => "Duplicate mobile '{$mobile}' in uploaded file (already in Row {$seenMobiles[$mobile]}).",
                ];
                continue;
            }

            // Database duplicate checks
            $existingEmail = Lead::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existingEmail) {
                $existingName = $existingEmail->company_name ?: $existingEmail->name;
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => "Email '{$email}' already exists on Lead #{$existingEmail->id} (" . $existingName . ").",
                ];
                continue;
            }

            $existingMobile = Lead::where('mobile', $mobile)->first();
            if ($existingMobile) {
                $existingName = $existingMobile->company_name ?: $existingMobile->name;
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => "Mobile '{$mobile}' already exists on Lead #{$existingMobile->id} (" . $existingName . ").",
                ];
                continue;
            }

            $seenEmails[$email] = $rowNum;
            $seenMobiles[$mobile] = $rowNum;

            // Extract remaining fields
            $salutation = $colSalutation !== null ? trim((string) ($row[$colSalutation] ?? '')) : '';
            if (!in_array($salutation, ['Mr', 'Mrs', 'Ms'], true)) {
                $salutation = 'Mr';
            }
            $mobileCode = $colMobileCode !== null ? preg_replace('/[^0-9]/', '', (string) ($row[$colMobileCode] ?? '')) : '91';
            if ($mobileCode === '') $mobileCode = '91';

            $designation = $colDesignation !== null ? trim((string) ($row[$colDesignation] ?? '')) : null;
            $industry = $colIndustry !== null ? trim((string) ($row[$colIndustry] ?? '')) : null;
            $noOfEmployees = $colEmployees !== null ? (int) ($row[$colEmployees] ?? 0) : null;
            $gstin = $colGstin !== null ? trim((string) ($row[$colGstin] ?? '')) : null;
            $leadCategory = $colCategory !== null ? trim((string) ($row[$colCategory] ?? '')) : 'Normal';
            if ($leadCategory === '') $leadCategory = 'Normal';
            $leadStatus = $colStatus !== null ? trim((string) ($row[$colStatus] ?? '')) : 'New';
            if ($leadStatus === '') $leadStatus = 'New';
            $leadSource = $colSource !== null ? trim((string) ($row[$colSource] ?? '')) : null;
            $leadBifurcation = $colBifurcation !== null ? trim((string) ($row[$colBifurcation] ?? '')) : null;
            $assignedTo = $colAssigned !== null ? trim((string) ($row[$colAssigned] ?? '')) : null;
            $mailingAddress = $colAddress !== null ? trim((string) ($row[$colAddress] ?? '')) : null;
            $country = $colCountry !== null ? trim((string) ($row[$colCountry] ?? '')) : 'India';
            if ($country === '') $country = 'India';
            $state = $colState !== null ? trim((string) ($row[$colState] ?? '')) : null;
            $city = $colCity !== null ? trim((string) ($row[$colCity] ?? '')) : null;
            $pincode = $colPincode !== null ? trim((string) ($row[$colPincode] ?? '')) : null;

            $followUpDate = null;
            if ($colFollowUpDate !== null && !empty($row[$colFollowUpDate])) {
                try {
                    $followUpDate = \Carbon\Carbon::parse($row[$colFollowUpDate])->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $followUpDate = null;
                }
            }
            $followUpNote = $colFollowUpNote !== null ? trim((string) ($row[$colFollowUpNote] ?? '')) : null;

            try {
                $this->ensureLeadColumn('salutation', 'varchar(10) NULL');
                $this->ensureLeadColumn('mobile_code', 'varchar(8) NULL');
                $this->ensureLeadColumn('business_type', 'varchar(20) NULL');
                $this->ensureLeadColumn('lead_bifurcation', 'varchar(100) NULL');

                $lead = Lead::create([
                    'name' => $name,
                    'salutation' => $salutation,
                    'email' => $email,
                    'mobile' => $mobile,
                    'mobile_code' => $mobileCode,
                    'company_name' => $company !== '' ? $company : $name,
                    'designation' => $designation ?: null,
                    'industry' => $industry ?: null,
                    'no_of_employees' => $noOfEmployees ?: null,
                    'gstin' => $gstin ?: null,
                    'lead_category' => $leadCategory,
                    'lead_status' => $leadStatus,
                    'lead_source' => $leadSource ?: null,
                    'lead_bifurcation' => $leadBifurcation ?: null,
                    'assigned_to' => $assignedTo ?: null,
                    'business_type' => 'new',
                    'mailing_address' => $mailingAddress ?: null,
                    'country' => $country,
                    'state' => $state ?: null,
                    'city' => $city ?: null,
                    'pincode' => $pincode ?: null,
                    'follow_up_date' => $followUpDate,
                    'follow_up_note' => $followUpNote ?: null,
                ]);

                // Create Contact if present
                $contactName = $colContactName !== null ? trim((string) ($row[$colContactName] ?? '')) : '';
                if ($contactName !== '') {
                    LeadContact::create([
                        'lead_id' => $lead->id,
                        'name' => $contactName,
                        'designation' => $colContactDesignation !== null ? trim((string) ($row[$colContactDesignation] ?? '')) : null,
                        'email' => $colContactEmail !== null ? trim((string) ($row[$colContactEmail] ?? '')) : null,
                        'mobile' => $colContactMobile !== null ? (function ($v) {
                            $v = trim((string) $v);
                            if (stripos($v, 'e') !== false || strpos($v, '.') !== false) {
                                if (is_numeric($v)) {
                                    $v = sprintf('%.0f', (float) $v);
                                }
                            }
                            return preg_replace('/[^0-9]/', '', $v) ?: null;
                        })($row[$colContactMobile] ?? '') : null,
                    ]);
                }

                // Create Service/Plan if present
                $providerName = $colProvider !== null ? trim((string) ($row[$colProvider] ?? '')) : '';
                $sku = $colSku !== null ? trim((string) ($row[$colSku] ?? '')) : '';
                if ($providerName !== '' && $sku !== '') {
                    $provider = Provider::whereRaw('LOWER(name) = ?', [strtolower($providerName)])->first();
                    if ($provider) {
                        $plan = Plan::where('provider_id', $provider->id)
                            ->where(function ($q) use ($sku) {
                                $q->where('slug', $sku)
                                  ->orWhereRaw('LOWER(name) = ?', [strtolower($sku)])
                                  ->orWhereRaw('LOWER(title) = ?', [strtolower($sku)]);
                            })->first();
                        if ($plan) {
                            $qty = $colQuantity !== null ? (int) ($row[$colQuantity] ?? 1) : 1;
                            if ($qty < 1) $qty = 1;
                            $rate = $colRate !== null && trim((string) ($row[$colRate] ?? '')) !== '' ? (float) $row[$colRate] : ($plan->actual_price > 0 ? (float) $plan->actual_price : (float) $plan->price);
                            $quotePeriod = $colQuotePeriod !== null ? trim((string) ($row[$colQuotePeriod] ?? '')) : 'Yearly';

                            $this->storeLeadServiceRow($lead, [
                                'provider_id' => $provider->id,
                                'plan_id' => $plan->id,
                                'quote_period' => $quotePeriod ?: 'Yearly',
                                'sku' => $plan->slug,
                                'quantity' => $qty,
                                'rate' => $rate,
                                'provider' => $provider->name,
                                'plan' => $plan->name ?: $plan->title,
                            ]);
                        }
                    }
                }

                $successCount++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNum,
                    'lead' => $identifier,
                    'reason' => 'Database error: ' . $e->getMessage(),
                ];
            }
        }

        $skippedCount = count($errors);

        if ($successCount > 0) {
            $msg = "Import completed: {$successCount} lead" . ($successCount === 1 ? '' : 's') . " imported successfully.";
            if ($skippedCount > 0) {
                $msg .= " {$skippedCount} row" . ($skippedCount === 1 ? '' : 's') . " skipped.";
            }

            return redirect()
                ->route('crm.leads')
                ->with('success', $msg)
                ->with('success_count', $successCount)
                ->with('skipped_count', $skippedCount)
                ->with('import_errors', $errors);
        }

        $errorMsg = "Import failed. All {$skippedCount} row" . ($skippedCount === 1 ? '' : 's') . " were skipped.";

        return back()
            ->with('error', $errorMsg)
            ->with('success_count', 0)
            ->with('skipped_count', $skippedCount)
            ->with('import_errors', $errors);
    }

    protected function findColumnIndex(array $headers, array $aliases): ?int
    {
        $normalize = function ($value) {
    
            $value = (string) $value;
    
            // Remove BOM
            $value = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                $value
            );
    
            // Replace non-breaking spaces
            $value = str_replace(
                "\xC2\xA0",
                ' ',
                $value
            );
    
            // Trim
            $value = trim($value);
    
            // Lowercase
            $value = strtolower($value);
    
            // Remove special characters/spaces
            return preg_replace(
                '/[^a-z0-9]/',
                '',
                $value
            );
        };
    
        $normalizedAliases = array_map(
            $normalize,
            $aliases
        );
    
        foreach ($headers as $index => $header) {
    
            $normalizedHeader = $normalize($header);
    
            if (
                $normalizedHeader !== '' &&
                in_array(
                    $normalizedHeader,
                    $normalizedAliases,
                    true
                )
            ) {
                return $index;
            }
        }
    
        return null;
    }

    protected function crmAssigneeNames()
    {
        try {
            return CrmUser::query()
                ->where('role', 'executive')
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function leadDropdowns($lead = null): array
    {
        $form = $this->crmLeadFormOptions($lead);
        $providers = $this->activeOnlyQuery(Provider::query(), 'providers')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'salutation' => ['Mr', 'Mrs', 'Ms'],
            'business_type' => ['new', 'existing'],
            'domain_type' => ['existing', 'new'],
            'domain_period' => ['Monthly', 'Quarterly', 'Half Yearly', 'Yearly'],
            'quote_period' => ['Monthly', 'Quarterly', 'Half Yearly', 'Yearly'],
            'lead_priority' => $form['leadPriorities'] ?? [],
            'lead_status' => $form['leadStatuses'] ?? [],
            'lead_source' => $form['leadSources'] ?? [],
            'lead_bifurcation' => $form['leadBifurcations'] ?? [],
            'designation' => $form['designations'] ?? [],
            'assignees' => $this->crmAssigneeNames()->values()->all(),
            'providers' => $providers,
            'plans' => $this->providerPlansFor($providers),
        ];
    }

    protected function crmLeadFormOptions($lead = null): array
    {
        $leadSources = $this->crmSettingNamesFrom(CrmLeadSource::class);
        $leadStatuses = $this->crmSettingNamesFrom(CrmLeadStatus::class);
        $this->ensureCrmLeadPrioritiesTable();
        $leadPriorities = $this->crmSettingNamesFrom(CrmLeadPriority::class);
        $leadBifurcations = $this->crmSettingNamesFrom(CrmLeadBifurcation::class);
        $designations = $this->crmSettingNamesFrom(CrmDesignation::class);

        if ($lead) {
            $leadSources = $this->appendCurrentOption($leadSources, $lead->lead_source ?? null);
            $leadStatuses = $this->appendCurrentOption($leadStatuses, $lead->lead_status ?? null);
            $leadPriorities = $this->appendCurrentOption($leadPriorities, $lead->lead_category ?? null);
            $leadBifurcations = $this->appendCurrentOption($leadBifurcations, $lead->lead_bifurcation ?? null);
            $designations = $this->appendCurrentOption($designations, $lead->designation ?? null);
        }

        $leadCategories = $leadPriorities;

        return compact('leadSources', 'leadStatuses', 'leadPriorities', 'leadCategories', 'leadBifurcations', 'designations');
    }

    protected function appendCurrentOption(array $names, $current): array
    {
        $current = trim((string) $current);
        if ($current !== '' && !in_array($current, $names, true)) {
            $names[] = $current;
        }

        return $names;
    }

    protected function crmSettingNamesFrom(string $model): array
    {
        try {
            $query = $model::query()->orderBy('name');
            $table = (new $model)->getTable();
            if (Schema::connection('pgsql_crm')->hasColumn($table, 'status')) {
                $query->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereIn('status', ['active', 'Active', '1', 1]);
                });
            }

            return $query->pluck('name')->filter()->unique()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function uploadedFileToRows($file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if ($ext === 'xlsx') {
            return $this->xlsxToRows($path);
        }

        if ($ext === 'xls') {
            throw new \RuntimeException('Please save the Excel file as .xlsx or .csv and upload again.');
        }

        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open the uploaded CSV file.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', rtrim($firstLine, "\r\n"));
        if (preg_match('/^sep=(.)$/i', $firstLine, $match)) {
            $delimiter = $match[1];
        } else {
            $delimiter = ',';
            $maxColumns = 1;
            foreach ([',', ';', "\t", '|'] as $candidate) {
                $columnCount = count(str_getcsv($firstLine, $candidate));
                if ($columnCount > $maxColumns) {
                    $delimiter = $candidate;
                    $maxColumns = $columnCount;
                }
            }
            rewind($handle);
        }

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    protected function xlsxToRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive is required to read XLSX files.');
        }
    
        $zip = new \ZipArchive();
    
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to open the XLSX file.');
        }
    
        /*
         * Read shared strings
         */
        $sharedStrings = [];
    
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    
        if ($sharedXml !== false) {
            $ss = @simplexml_load_string($sharedXml);
    
            if ($ss) {
                foreach ($ss->si as $si) {
                    $text = '';
    
                    /*
                     * Normal shared string
                     */
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    }
    
                    /*
                     * Rich text shared string
                     */
                    if (isset($si->r)) {
                        $text = '';
    
                        foreach ($si->r as $run) {
                            if (isset($run->t)) {
                                $text .= (string) $run->t;
                            }
                        }
                    }
    
                    $sharedStrings[] = $text;
                }
            }
        }
    
        /*
         * Find first worksheet
         */
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    
        if ($sheetXml === false) {
            $zip->close();
    
            throw new \RuntimeException(
                'The XLSX file has no worksheet data.'
            );
        }
    
        $zip->close();
    
        $sheet = @simplexml_load_string($sheetXml);
    
        if (!$sheet) {
            throw new \RuntimeException(
                'Unable to read the XLSX worksheet.'
            );
        }
    
        /*
         * Handle Excel namespaces
         */
        $namespaces = $sheet->getNamespaces(true);
    
        if (isset($namespaces[''])) {
            $sheet->registerXPathNamespace(
                'x',
                $namespaces['']
            );
        }
    
        /*
         * Get all rows
         */
        $xmlRows = isset($namespaces[''])
            ? $sheet->xpath('//x:sheetData/x:row')
            : $sheet->sheetData->row;
    
        if (!$xmlRows) {
            return [];
        }
    
        $rows = [];
    
        foreach ($xmlRows as $xmlRow) {
    
            $cells = [];
    
            /*
             * Get cells
             */
            $xmlCells = isset($namespaces[''])
                ? $xmlRow->xpath('./x:c')
                : $xmlRow->c;
    
            foreach ($xmlCells as $cell) {
    
                $cellRef = (string) $cell['r'];
    
                if ($cellRef === '') {
                    continue;
                }
    
                /*
                 * Convert A1/B1/C1 etc. into numeric indexes
                 */
                $columnIndex = $this->xlsxColumnIndex($cellRef);
    
                $type = (string) $cell['t'];
    
                $value = '';
    
                /*
                 * Shared string
                 */
                if ($type === 's') {
    
                    $sharedIndex = isset($cell->v)
                        ? (int) $cell->v
                        : -1;
    
                    $value = $sharedStrings[$sharedIndex] ?? '';
                }
    
                /*
                 * Inline string
                 */
                elseif ($type === 'inlineStr') {
    
                    if (isset($cell->is->t)) {
                        $value = (string) $cell->is->t;
                    }
                }
    
                /*
                 * Boolean
                 */
                elseif ($type === 'b') {
    
                    $value = ((string) $cell->v === '1')
                        ? '1'
                        : '0';
                }
    
                /*
                 * Normal number / date / string
                 */
                else {
    
                    $value = isset($cell->v)
                        ? (string) $cell->v
                        : '';
                }
    
                /*
                 * Clean value
                 */
                $value = preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    $value
                );
    
                $value = str_replace(
                    "\xC2\xA0",
                    ' ',
                    $value
                );
    
                $value = trim($value);
    
                $cells[$columnIndex] = $value;
            }
    
            /*
             * Ignore completely empty rows
             */
            if (empty($cells)) {
                continue;
            }
    
            /*
             * Important:
             * Create row using the actual highest column
             * of THIS row, not global $maxCol.
             */
            $maxColumn = max(array_keys($cells));
    
            $row = [];
    
            for ($i = 0; $i <= $maxColumn; $i++) {
                $row[$i] = $cells[$i] ?? '';
            }
    
            $rows[] = $row;
        }
    
        return $rows;
    }

    protected function ensureCrmLeadPrioritiesTable(): void
    {
        try {
            DB::connection('pgsql_crm')->statement("
                CREATE TABLE IF NOT EXISTS crm_lead_priorities (
                    id BIGSERIAL PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    status VARCHAR(50) DEFAULT 'active',
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ");

            if (DB::connection('pgsql_crm')->table('crm_lead_priorities')->count() === 0) {
                $now = now();
                DB::connection('pgsql_crm')->table('crm_lead_priorities')->insert([
                    ['name' => 'Hot', 'description' => 'High priority lead', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
                    ['name' => 'Warm', 'description' => 'Medium priority lead', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
                    ['name' => 'Cold', 'description' => 'Low priority lead', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
                    ['name' => 'Normal', 'description' => 'Standard priority lead', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ensureCrmLeadPrioritiesTable failed', ['error' => $e->getMessage()]);
        }
    }

    protected function ensureLeadColumn(string $column, string $type): void
    {
        try {
            DB::connection('pgsql_crm')->statement(
                'ALTER TABLE leads ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type
            );
        } catch (\Throwable $e) {
            Log::warning('ensureLeadColumn failed', [
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function saveLeadBifurcation(Lead $lead, Request $request): void
    {
        $value = trim((string) $request->input('lead_bifurcation'));
        $value = $value !== '' ? $value : null;
        $this->ensureLeadColumn('lead_bifurcation', 'varchar(100) NULL');

        try {
            DB::connection('pgsql_crm')->table('leads')->where('id', $lead->id)->update([
                'lead_bifurcation' => $value,
            ]);
            $lead->setAttribute('lead_bifurcation', $value);
        } catch (\Throwable $e) {
            Log::warning('lead_bifurcation save failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function ensureLeadServiceColumns(): void
    {
        $columns = [
            'provider_id' => 'bigint NULL',
            'plan_id' => 'bigint NULL',
            'renewal_date' => 'date NULL',
            'is_info' => 'boolean NULL',
            'domain' => 'varchar(255) NULL',
        ];

        foreach ($columns as $column => $type) {
            try {
                if (!Schema::connection('pgsql_crm')->hasColumn('lead_services', $column)) {
                    DB::connection('pgsql_crm')->statement(
                        'ALTER TABLE lead_services ADD COLUMN IF NOT EXISTS ' . $column . ' ' . $type
                    );
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    protected function storeLeadServiceRow(Lead $lead, array $data): void
    {
        $this->ensureLeadServiceColumns();
        $payload = [
            'lead_id' => $lead->id,
            'provider_id' => $data['provider_id'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'quote_period' => $data['quote_period'] ?? null,
            'sku' => $data['sku'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'rate' => $data['rate'] ?? null,
            'renewal_date' => !empty($data['renewal_date'])
                ? substr((string) $data['renewal_date'], 0, 10)
                : null,
            'is_info' => !empty($data['is_info']),
            'domain' => $data['domain'] ?? null,
            'provider' => $data['provider'] ?? null,
            'plan' => $data['plan'] ?? null,
        ];

        $filtered = [];
        foreach ($payload as $column => $value) {
            if (Schema::connection('pgsql_crm')->hasColumn('lead_services', $column)) {
                $filtered[$column] = $value;
            }
        }

        LeadService::create($filtered);
    }

    protected function normalizeDomain($domain): ?string
    {
        $domain = strtolower(trim((string) $domain));

        return $domain !== '' ? $domain : null;
    }

    protected function domainExistsOnLead(?string $domain, $ignoreId = null): bool
    {
        $domain = $this->normalizeDomain($domain);
        if (!$domain) {
            return false;
        }

        $query = Lead::query()->whereRaw('LOWER(TRIM(domain)) = ?', [$domain]);
        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->exists();
    }

    protected function assertLeadDomainAllowed(Request $request, $ignoreId = null): void
    {
        $type = (string) $request->input('domain_type', '');
        $domain = $this->normalizeDomain($request->input('domain'));
        if ($type === '' || !$domain) {
            return;
        }

        if ($this->domainExistsOnLead($domain, $ignoreId)) {
            throw ValidationException::withMessages([
                'domain' => 'This domain is already saved on another lead.',
            ]);
        }

        if ($type !== 'new') {
            return;
        }

        $check = $this->lookupDomainAvailability($domain);
        if (!empty($check['invalid']) || !empty($check['available']) || empty($check['registered'])) {
            return;
        }

        $request->merge(['domain_type' => 'existing']);
    }

    protected function lookupDomainAvailability(string $domain): array
    {
        if ($domain === '' || !str_contains($domain, '.')) {
            return [
                'ok' => false,
                'available' => false,
                'invalid' => true,
                'message' => 'Enter a valid domain (example.com)',
            ];
        }

        [$sld, $tld] = explode('.', $domain, 2);

        $baseUrl = rtrim((string) config('services.domaincheck.base_url', 'https://domaincheck.httpapi.com'), '/');
        $authUserid = preg_replace('/\D+/', '', (string) config('services.domaincheck.auth_userid', ''));
        $apiKey = (string) config('services.domaincheck.api_key', '');

        $settings = PvSetting::query()
            ->whereIn('key_id', ['domaincheck_base_url', 'domaincheck_auth_userid', 'domaincheck_api_key'])
            ->pluck('key_secret', 'key_id');

        if (!empty($settings['domaincheck_base_url'])) {
            $baseUrl = rtrim((string) $settings['domaincheck_base_url'], '/');
        }
        if (!empty($settings['domaincheck_auth_userid'])) {
            $authUserid = preg_replace('/\D+/', '', (string) $settings['domaincheck_auth_userid']);
        }
        if (!empty($settings['domaincheck_api_key'])) {
            $apiKey = (string) $settings['domaincheck_api_key'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                ])
                ->get($baseUrl . '/api/domains/available.json', [
                    'auth-userid' => (int) $authUserid,
                    'api-key' => $apiKey,
                    'domain-name' => $sld,
                    'tlds' => $tld,
                ]);
        } catch (\Throwable $e) {
            return $this->domainAvailabilityFromDns($domain, 'Could not verify domain availability.');
        }

        $payload = $response->json();
        $status = '';
        if (is_array($payload)) {
            $entry = $payload[$domain] ?? null;
            if (!is_array($entry)) {
                foreach ($payload as $key => $value) {
                    if (is_string($key) && strcasecmp($key, $domain) === 0 && is_array($value)) {
                        $entry = $value;
                        break;
                    }
                }
            }
            $status = strtolower(trim((string) ($entry['status'] ?? '')));
        }

        if (in_array($status, ['available', 'availablefortransfer'], true)) {
            return [
                'ok' => true,
                'available' => true,
                'registered' => false,
                'invalid' => false,
                'message' => 'This domain is available to register.',
            ];
        }

        if (in_array($status, ['regthroughothers', 'regthroughus', 'registered', 'unavailable'], true)) {
            return [
                'ok' => true,
                'available' => false,
                'registered' => true,
                'invalid' => false,
                'message' => 'This domain is already registered, so Already have domain was selected. You can save the lead.',
            ];
        }

        return $this->domainAvailabilityFromDns($domain, 'Could not verify with registrar. Checked DNS instead.');
    }

    protected function domainAvailabilityFromDns(string $domain, string $note): array
    {
        $hasDns = @checkdnsrr($domain, 'A')
            || @checkdnsrr($domain, 'AAAA')
            || @checkdnsrr($domain, 'MX')
            || @checkdnsrr($domain, 'NS');

        if ($hasDns) {
            return [
                'ok' => true,
                'available' => false,
                'registered' => true,
                'invalid' => false,
                'message' => 'This domain is already registered, so Already have domain was selected. You can save the lead.',
            ];
        }

        return [
            'ok' => true,
            'available' => true,
            'registered' => false,
            'invalid' => false,
            'message' => 'This domain is available to register.',
        ];
    }

    protected function xlsxColumnIndex(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/i', $cellRef, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = ($n * 26) + (ord($letters[$i]) - 64);
        }

        return $n - 1;
    }

    protected function checkPlans($plans)
    {
        $pairs = [];
        $otherProviders = [];
        foreach ($plans as $row) {
            $id = (int) ($row['provider_id'] ?? 0);
            $planId = (int) ($row['plan_id'] ?? 0);
            if (!$id) {
                continue;
            }
            $pair = $id . ':' . $planId;
            if (in_array($pair, $pairs, true)) {
                throw ValidationException::withMessages(['plans' => 'This plan is already added.']);
            }
            $pairs[] = $pair;
            $name = optional(Provider::find($id))->name;
            if ($name && stripos($name, 'google') !== false) {
                continue;
            }
            if (in_array($id, $otherProviders, true)) {
                throw ValidationException::withMessages(['plans' => 'Same provider can only be added once.']);
            }
            $otherProviders[] = $id;
        }
    }
}
