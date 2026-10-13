<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadService;
use App\Http\Controllers\SuperAdmin\Crm\LeadsController as WebLeadsController;
use Illuminate\Http\Request;

class LeadsController extends WebLeadsController
{
    public function index(Request $request)
    {
        $data = parent::index($request)->getData();
        $leads = $data['leads'];

        return response()->json([
            'success' => true,
            'message' => 'Leads fetched successfully.',
            'data' => [
                'leads' => $leads->items(),
                'pagination' => [
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage(),
                    'per_page' => $leads->perPage(),
                    'total' => $leads->total(),
                ],
                'stats' => $data['leadStats'] ?? [],
                'employees' => $data['employees'] ?? [],
                'options' => $this->leadDropdowns(),
            ],
        ]);
    }

    public function create()
    {
        return response()->json([
            'success' => true,
            'message' => 'Send this body to POST /api/crm/lead-create (multipart/form-data when sending documents).',
            'data' => [
                'sample' => [
                    'customer_detail' => [
                        'salutation' => 'Mr',
                        'name' => '',
                        'designation' => '',
                        'email' => '',
                        'mobile_code' => '91',
                        'mobile' => '',
                        'company_name' => '',
                        'industry' => '',
                        'no_of_employees' => null,
                        'gstin' => '',
                        'business_type' => 'new',
                    ],
                    'address_information' => [
                        'mailing_address' => '',
                        'country' => '',
                        'state' => '',
                        'city' => '',
                        'pincode' => '',
                    ],
                    'domain' => [
                        'domain_type' => '',
                        'domain' => '',
                        'domain_period' => '',
                        'domain_rate' => null,
                        'sac' => '',
                        'domain_start' => '',
                        'domain_end' => '',
                        'domain_renewal_date' => '',
                    ],
                    'current_service_info' => [
                        [
                            'domain' => '',
                            'provider_id' => null,
                            'plan_id' => null,
                            'quantity' => 1,
                            'rate' => null,
                            'renewal_date' => '',
                        ],
                    ],
                    'our_service_plan_detail' => [
                        [
                            'provider_id' => null,
                            'plan_id' => null,
                            'quote_period' => '',
                            'sku' => '',
                            'quantity' => 1,
                            'rate' => null,
                        ],
                    ],
                    'additional_contact' => [
                        [
                            'name' => '',
                            'designation' => '',
                            'email' => '',
                            'mobile' => '',
                        ],
                    ],
                    'lead_specification' => [
                        'lead_priority' => '',
                        'lead_status' => 'New',
                        'lead_source' => '',
                        'lead_bifurcation' => '',
                        'assigned_to' => '',
                    ],
                    'follow_up_detail' => [
                        'follow_up_date' => '',
                        'follow_up_note' => '',
                    ],
                    'documents' => [],
                    'document_types' => [],
                ],
                'options' => $this->leadDropdowns(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->flattenLeadPayload($request);
        parent::store($request);

        $lead = Lead::where('email', $request->email)->latest('id')->first();
        $lead?->load(['contacts', 'services.provider', 'services.plan']);

        return response()->json([
            'success' => true,
            'message' => 'Lead added successfully.',
            'data' => ['lead' => $lead],
        ], 201);
    }

    public function show(Lead $lead)
    {
        $data = parent::show($lead)->getData();
        $lead = $data['lead'] ?? $lead;
        $detail = $lead->toArray();
        $detail['domain'] = [
            'domain_type' => $lead->domain_type,
            'domain' => $lead->domain,
            'domain_period' => $lead->domain_period,
            'domain_rate' => $lead->domain_rate,
            'sac' => $lead->sac,
            'domain_start' => $lead->domain_start,
            'domain_end' => $lead->domain_end,
            'domain_renewal_date' => $lead->domain_renewal_date,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Lead fetched successfully.',
            'data' => [
                'lead' => $detail,
                'history' => $data['history'] ?? [],
                'options' => $this->leadDropdowns($lead),
            ],
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $this->flattenLeadPayload($request);
        parent::update($request, $lead);

        $lead->refresh()->load(['contacts', 'services.provider', 'services.plan']);

        return response()->json([
            'success' => true,
            'message' => 'Lead details saved successfully.',
            'data' => ['lead' => $lead],
        ]);
    }

    public function updateFollowUp(Request $request, Lead $lead)
    {
        $this->flattenLeadPayload($request);
        parent::updateFollowUp($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up saved successfully.',
            'data' => ['lead' => $lead->refresh()],
        ]);
    }

    public function assignEmployees(Request $request)
    {
        parent::assignEmployees($request);

        return response()->json([
            'success' => true,
            'message' => 'Leads assigned successfully.',
            'data' => [
                'leads' => Lead::whereIn('id', $request->input('lead_ids', []))->get(),
            ],
        ]);
    }

    public function checkDomain(Request $request)
    {
        return parent::checkDomain($request);
    }

    public function storeContact(Request $request, Lead $lead)
    {
        parent::storeContact($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Contact added successfully.',
            'data' => ['contacts' => $lead->contacts()->get()],
        ]);
    }

    public function updateContact(Request $request, Lead $lead, LeadContact $contact)
    {
        parent::updateContact($request, $lead, $contact);

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully.',
            'data' => ['contacts' => $lead->contacts()->get()],
        ]);
    }

    public function destroyContact(Lead $lead, LeadContact $contact)
    {
        parent::destroyContact($lead, $contact);

        return response()->json([
            'success' => true,
            'message' => 'Contact removed successfully.',
            'data' => ['contacts' => $lead->contacts()->get()],
        ]);
    }

    public function storeService(Request $request, Lead $lead)
    {
        parent::storeService($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Service added successfully.',
            'data' => ['services' => $lead->services()->with(['provider', 'plan'])->get()],
        ]);
    }

    public function updateService(Request $request, Lead $lead, LeadService $leadService)
    {
        parent::updateService($request, $lead, $leadService);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => ['services' => $lead->services()->with(['provider', 'plan'])->get()],
        ]);
    }

    public function destroyService(Lead $lead, LeadService $leadService)
    {
        parent::destroyService($lead, $leadService);

        return response()->json([
            'success' => true,
            'message' => 'Service removed successfully.',
            'data' => ['services' => $lead->services()->with(['provider', 'plan'])->get()],
        ]);
    }

    public function storeDocuments(Request $request, Lead $lead)
    {
        parent::storeDocuments($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Document added successfully.',
            'data' => ['documents' => $lead->refresh()->document],
        ]);
    }

    public function destroyDocument(Request $request, Lead $lead)
    {
        parent::destroyDocument($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Document removed successfully.',
            'data' => ['documents' => $lead->refresh()->document],
        ]);
    }

    public function download(Request $request)
    {
        return parent::download($request);
    }

    public function downloadSample()
    {
        return parent::downloadSample();
    }

    public function upload(Request $request)
    {
        parent::upload($request);

        return response()->json([
            'success' => true,
            'message' => 'Lead import completed successfully.',
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('lead_ids', []);
        $count = Lead::whereIn('id', $ids)->count();
        Lead::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => $count . ' lead(s) deleted successfully.',
        ]);
    }

    public function destroy(Lead $lead)
    {
        $id = $lead->id;
        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully.',
            'data' => ['deleted_id' => $id],
        ]);
    }

    protected function flattenLeadPayload(Request $request): void
    {
        foreach (['customer_detail', 'address_information', 'domain', 'lead_specification', 'follow_up_detail', 'current_service_info', 'our_service_plan_detail', 'additional_contact'] as $key) {
            $decoded = is_string($request->input($key)) ? json_decode($request->input($key), true) : null;
            if (is_array($decoded)) {
                array_walk_recursive($decoded, function (&$value) {
                    $value = $value === '' ? null : $value;
                });
                $request->merge([$key => $decoded]);
            }
        }

        foreach (['customer_detail', 'address_information', 'domain', 'lead_specification', 'follow_up_detail'] as $key) {
            if (is_array($request->input($key))) {
                $request->merge($request->input($key));
            }
        }

        if ($request->has('current_service_info')) {
            $request->merge(['vendor_services' => $request->current_service_info]);
        }

        if ($request->has('our_service_plan_detail')) {
            $request->merge(['plans' => $request->our_service_plan_detail]);
        }

        if ($request->has('additional_contact')) {
            $request->merge(['contacts' => $request->additional_contact]);
        }

        if ($request->filled('lead_priority') && ! $request->filled('lead_category')) {
            $request->merge(['lead_category' => $request->lead_priority]);
        }
    }
}
