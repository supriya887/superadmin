<?php

namespace App\Http\Controllers\SuperAdmin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadService;
use App\Models\Plan;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

                $q->where('name', 'like', "%{$search}%")
                    ->orWhere(
                        'company_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'mobile',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'gstin',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where(
                'lead_status',
                $request->status
            );
        }

        if ($request->filled('source')) {
            $query->where(
                'lead_source',
                $request->source
            );
        }

        $leads = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view(
            'superadmin.crm.leads.index',
            compact('leads')
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

        return view(
            'superadmin.crm.leads.create',
            compact(
                'providers',
                'providerPlans'
            )
        );
    }

    /**
     * Store Lead
     */
    public function store(Request $request)
    {
        $validated = $request->validate([

            /*
             * Customer
             */
            'name' => [
                'required',
                'string',
                'max:255'
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
                'string',
                'max:30',
                Rule::unique(
                    'pgsql_crm.leads',
                    'mobile'
                ),
            ],

            'company_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'pgsql_crm.leads',
                    'company_name'
                ),
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

            'gstin' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(
                    'pgsql_crm.leads',
                    'gstin'
                ),
            ],

            /*
             * Lead
             */
            'lead_category' => [
                'required',
                'string',
                'max:100'
            ],

            'lead_status' => [
                'required',
                'string',
                'max:100'
            ],

            'lead_source' => [
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
                'string',
                'max:30'
            ],
        ]);

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
                    $leadData['documents']
                );

                /*
                 * Upload Documents
                 */
                $documents = [];

                if ($request->hasFile('documents')) {

                    foreach (
                        $request->file('documents')
                        as $file
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

                        $documents[] = $path;
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
                foreach (
                    $request->input('plans', [])
                    as $planData
                ) {

                    if (
                        empty($planData['provider_id']) ||
                        empty($planData['plan_id'])
                    ) {
                        continue;
                    }

                    $plan = Plan::where(
                        'id',
                        $planData['plan_id']
                    )
                        ->where(
                            'provider_id',
                            $planData['provider_id']
                        )
                        ->first();

                    if (!$plan) {
                        continue;
                    }

                    LeadService::create([

                        'lead_id' => $lead->id,

                        'provider_id' =>
                        $plan->provider_id,

                        'plan_id' =>
                        $plan->id,

                        'quote_period' =>
                        $planData['quote_period'] ?? null,

                        'sku' =>
                        $plan->slug,

                        'quantity' =>
                        $planData['quantity'] ?? null,

                        'rate' =>
                        $plan->actual_price > 0 ? $plan->actual_price : ($plan->price > 0 ? $plan->price : 0),
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

        return view(
            'superadmin.crm.leads.show',
            compact('lead')
        );
    }

    public function uploads()
    {
        return view('superadmin.crm.leads.uploads');
    }

    public function downloadSample()
    {
        $headers = [
            'Name',
            'Designation',
            'Email',
            'Mobile',
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
            'Document',
            'Contact Name',
            'Contact Designation',
            'Contact Email',
            'Contact Mobile',
            'Follow Up Date',
            'Follow Up Note',
            'Lead Category',
            'Lead Status',
            'Lead Source',
            'Assigned To'
        ];

        $callback = function () use ($headers) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $headers);
            // Add a sample row
            fputcsv($file, [
                'John Doe',
                'Manager',
                'john@example.com',
                '9876543210',
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
                '',
                'Jane Doe',
                'Manager',
                'jane@example.com',
                '9876543211',
                '2026-11-01',
                'Follow up note',
                'Hot',
                'New',
                'Website',
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
            'file' => 'required|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $data = array_map('str_getcsv', file($file->getRealPath()));

        if (count($data) < 2) {
            return back()->with('error', 'The uploaded file is empty or missing data rows.');
        }

        $headers = array_shift($data);

        // Handle potential duplicate columns from Excel (Name.1, Email.1, etc.)
        $headers = array_map(function ($header) {
            $header = trim($header);
            if ($header === 'Name.1') return 'Contact Name';
            if ($header === 'Designation.1') return 'Contact Designation';
            if ($header === 'Email.1') return 'Contact Email';
            if ($header === 'Mobile No') return 'Contact Mobile';
            if ($header === 'Add Note') return 'Follow Up Note';
            return $header;
        }, $headers);

        $headerMap = array_flip($headers);

        $requiredHeaders = ['Name', 'Email', 'Mobile', 'Company Name', 'Lead Category', 'Lead Status'];
        foreach ($requiredHeaders as $req) {
            if (!isset($headerMap[$req])) {
                return back()->with('error', "Missing required column: {$req}. Please download the latest sample CSV.");
            }
        }

        $successCount = 0;
        $errors = [];

        DB::connection('pgsql_crm')->transaction(function () use ($data, $headerMap, &$successCount, &$errors) {
            foreach ($data as $index => $row) {
                if (empty(array_filter($row))) {
                    continue; // skip empty rows
                }

                $rowNum = $index + 2; // +1 for 0-index, +1 for header
                $email = $row[$headerMap['Email']] ?? null;
                $mobile = $row[$headerMap['Mobile']] ?? null;
                $company = $row[$headerMap['Company Name']] ?? null;

                if (!$email || !$mobile || !$company || empty($row[$headerMap['Name']])) {
                    $errors[] = "Row {$rowNum}: Missing Name, Email, Mobile, or Company Name.";
                    continue;
                }

                $exists = Lead::where('email', $email)
                    ->orWhere('mobile', $mobile)
                    ->orWhere('company_name', $company)
                    ->exists();

                if ($exists) {
                    $errors[] = "Row {$rowNum}: A lead with email '{$email}', mobile '{$mobile}', or company '{$company}' already exists.";
                    continue;
                }

                $lead = Lead::create([
                    'name' => $row[$headerMap['Name']] ?? null,
                    'email' => $email,
                    'mobile' => $mobile,
                    'company_name' => $company,
                    'designation' => $row[$headerMap['Designation']] ?? null,
                    'industry' => $row[$headerMap['Industry']] ?? null,
                    'no_of_employees' => isset($headerMap['No of Employees']) ? ((int)$row[$headerMap['No of Employees']] ?: null) : null,
                    'gstin' => $row[$headerMap['GSTIN']] ?? null,
                    'lead_category' => $row[$headerMap['Lead Category']] ?? null,
                    'lead_status' => $row[$headerMap['Lead Status']] ?? null,
                    'lead_source' => $row[$headerMap['Lead Source']] ?? null,
                    'assigned_to' => $row[$headerMap['Assigned To']] ?? null,
                    'mailing_address' => $row[$headerMap['Mailing Address']] ?? null,
                    'country' => $row[$headerMap['Country']] ?? null,
                    'state' => $row[$headerMap['State']] ?? null,
                    'city' => $row[$headerMap['City']] ?? null,
                    'pincode' => $row[$headerMap['Pincode']] ?? null,
                    'follow_up_date' => !empty($row[$headerMap['Follow Up Date']]) ? $row[$headerMap['Follow Up Date']] : null,
                    'follow_up_note' => $row[$headerMap['Follow Up Note']] ?? null,
                ]);

                // Create Contact
                $contactName = $row[$headerMap['Contact Name']] ?? null;
                if ($contactName) {
                    LeadContact::create([
                        'lead_id' => $lead->id,
                        'name' => $contactName,
                        'designation' => $row[$headerMap['Contact Designation']] ?? null,
                        'email' => $row[$headerMap['Contact Email']] ?? null,
                        'mobile' => $row[$headerMap['Contact Mobile']] ?? null,
                    ]);
                }

                // Create Service/Plan
                $providerName = $row[$headerMap['Provider']] ?? null;
                $sku = $row[$headerMap['SKU']] ?? null;
                if ($providerName && $sku) {
                    $provider = Provider::where('name', trim($providerName))->first();
                    if ($provider) {
                        $plan = Plan::where('slug', trim($sku))->where('provider_id', $provider->id)->first();
                        if ($plan) {
                            LeadService::create([
                                'lead_id' => $lead->id,
                                'provider_id' => $provider->id,
                                'plan_id' => $plan->id,
                                'quote_period' => $row[$headerMap['Quote Period']] ?? null,
                                'sku' => $plan->slug,
                                'quantity' => !empty($row[$headerMap['Quantity']]) ? (int)$row[$headerMap['Quantity']] : 1,
                                'rate' => !empty($row[$headerMap['Rate']]) ? (float)$row[$headerMap['Rate']] : ($plan->actual_price > 0 ? $plan->actual_price : $plan->price),
                            ]);
                        }
                    }
                }

                $successCount++;
            }
        });

        if ($successCount > 0) {
            $msg = "Successfully imported {$successCount} leads.";
            if (count($errors) > 0) {
                $msg .= " However, some rows failed: " . implode(" | ", array_slice($errors, 0, 3));
                if (count($errors) > 3) $msg .= " (and " . (count($errors) - 3) . " more).";
            }
            return redirect()->route('crm.leads')->with('success', $msg);
        }

        $errorMsg = "Failed to import leads. Reasons: " . implode(" | ", array_slice($errors, 0, 5));
        return back()->with('error', $errorMsg);
    }
}
