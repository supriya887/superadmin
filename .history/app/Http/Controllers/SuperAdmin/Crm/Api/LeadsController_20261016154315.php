<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\LeadService;
use App\Models\Plan;
use App\Http\Controllers\SuperAdmin\Crm\FollowUpController;
use App\Http\Controllers\SuperAdmin\Crm\LeadsController as WebLeadsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            'data' => ['lead' => $lead ? ['document' => $this->documentsWithUrl($lead)] + $lead->toArray() : null],
        ], 201);
    }

    public function show(Lead $lead)
    {
        $data = parent::show($lead)->getData();
        $lead = $data['lead'] ?? $lead;
        $detail = $lead->toArray();
        $detail['document'] = $this->documentsWithUrl($lead);
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
        $this->flattenLeadPayload($request, $lead);
        $request->validate([
            'additional_contact' => 'nullable|array',
            'additional_contact.*.name' => 'nullable|string|max:255',
            'additional_contact.*.designation' => 'nullable|string|max:255',
            'additional_contact.*.email' => 'nullable|email|max:255',
            'additional_contact.*.mobile' => 'nullable|regex:/^[0-9]{6,15}$/',
            'current_service_info' => 'nullable|array',
            'current_service_info.*.provider_id' => 'nullable|integer',
            'current_service_info.*.plan_id' => 'nullable|integer',
            'current_service_info.*.quantity' => 'nullable|integer|min:1',
            'current_service_info.*.rate' => 'nullable|numeric|min:0',
            'current_service_info.*.renewal_date' => 'nullable|date',
            'current_service_info.*.domain' => 'nullable|string|max:255',
            'our_service_plan_detail' => 'nullable|array',
            'our_service_plan_detail.*.provider_id' => 'nullable|integer',
            'our_service_plan_detail.*.plan_id' => 'nullable|integer',
            'our_service_plan_detail.*.quote_period' => 'nullable|string|max:100',
            'our_service_plan_detail.*.quantity' => 'nullable|integer|min:1',
            'our_service_plan_detail.*.rate' => 'nullable|numeric|min:0',
            'document' => 'nullable|array',
            'document_types' => 'nullable|array',
            'document_types.*' => 'nullable|string|max:100',
            'follow_up_date' => 'nullable|date',
            'follow_up_note' => 'nullable|string',
        ]);
        if ($request->hasFile('documents')) {
            $request->validate([
                'documents' => 'array|max:10',
                'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);
        }

        DB::connection('pgsql_crm')->transaction(function () use ($request, $lead) {
            parent::update($request, $lead);

            $followUp = array_filter($request->only('follow_up_date', 'follow_up_note'));
            $lead->fill($followUp);
            if ($lead->isDirty(['follow_up_date', 'follow_up_note'])) {
                $lead->save();
                app(FollowUpController::class)->record($lead, $followUp);
            }
            if ($this->sectionSent($request, 'current_service_info') && $lead->business_type !== 'new') {
                $this->saveServices($lead, $this->rows($request, 'current_service_info'), true);
            }
            if ($this->sectionSent($request, 'our_service_plan_detail')) {
                $this->saveServices($lead, $this->rows($request, 'our_service_plan_detail'), false);
            }
            if ($this->sectionSent($request, 'additional_contact')) {
                $this->saveContacts($lead, $this->rows($request, 'additional_contact'));
            }
            $this->saveDocuments($lead->refresh(), $request);
        });

        $lead->refresh()->load(['contacts', 'services.provider', 'services.plan']);

        return response()->json([
            'success' => true,
            'message' => 'Lead details saved successfully.',
            'data' => ['lead' => ['document' => $this->documentsWithUrl($lead)] + $lead->toArray()],
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
        $this->flattenLeadPayload($request);
        parent::storeDocuments($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Document added successfully.',
            'data' => ['documents' => $this->documentsWithUrl($lead->refresh())],
        ]);
    }

    public function destroyDocument(Request $request, Lead $lead)
    {
        parent::destroyDocument($request, $lead);

        return response()->json([
            'success' => true,
            'message' => 'Document removed successfully.',
            'data' => ['documents' => $this->documentsWithUrl($lead->refresh())],
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

    protected function sectionSent(Request $request, string $key): bool
    {
        return array_key_exists($key, $request->all());
    }

    protected function rows(Request $request, string $key): array
    {
        $rows = $request->input($key);

        return is_array($rows) ? array_values($rows) : [];
    }

    protected function saveServices(Lead $lead, array $rows, bool $isInfo): void
    {
        $this->ensureLeadServiceColumns();
        $old = $lead->services()->get()->filter(fn ($service) => $service->isCompetitorInfo() === $isInfo);
        $keep = [];

        foreach ($rows as $row) {
            if (empty($row['plan_id'])) {
                continue;
            }
            $plan = Plan::where('id', $row['plan_id'])->where('provider_id', $row['provider_id'] ?? null)->first();
            if (!$plan) {
                throw ValidationException::withMessages(['plan_id' => 'Selected plan does not belong to the selected provider.']);
            }
            $service = $lead->services()->find($row['id'] ?? 0)
                ?? $old->first(fn ($s) => (int) $s->plan_id === (int) $plan->id);
            $data = array_filter([
                'quote_period' => $isInfo ? null : ($row['quote_period'] ?? null),
                'sku' => $row['sku'] ?? null,
                'quantity' => $row['quantity'] ?? null,
                'rate' => $row['rate'] ?? null,
                'renewal_date' => $isInfo ? ($row['renewal_date'] ?? null) : null,
                'domain' => $isInfo ? $this->normalizeDomain($row['domain'] ?? null) : null,
            ], fn ($value) => $value !== null && $value !== '') + [
                'lead_id' => $lead->id,
                'provider_id' => $plan->provider_id,
                'plan_id' => $plan->id,
                'is_info' => $isInfo,
            ];

            if ($service) {
                $service->update($data);
            } else {
                $service = LeadService::create($data + [
                    'sku' => $plan->slug,
                    'quantity' => 1,
                    'rate' => $plan->actual_price > 0 ? $plan->actual_price : $plan->price,
                ]);
            }
            $keep[] = (int) $service->id;
        }

        foreach ($old as $service) {
            if (!in_array((int) $service->id, $keep, true)) {
                $service->delete();
            }
        }
    }

    protected function saveContacts(Lead $lead, array $rows): void
    {
        $old = $lead->contacts()->get();
        $keep = [];

        foreach ($rows as $row) {
            $data = array_filter([
                'name' => trim((string) ($row['name'] ?? '')) ?: null,
                'designation' => trim((string) ($row['designation'] ?? '')) ?: null,
                'email' => trim((string) ($row['email'] ?? '')) ?: null,
                'mobile' => trim((string) ($row['mobile'] ?? '')) ?: null,
            ]);
            if (!$data) {
                continue;
            }
            $contact = $lead->contacts()->find($row['id'] ?? 0);
            if ($contact) {
                $contact->update($data);
            } else {
                $contact = $lead->contacts()->create($data);
            }
            $keep[] = (int) $contact->id;
        }

        foreach ($old as $contact) {
            if (!in_array((int) $contact->id, $keep, true)) {
                $contact->delete();
            }
        }
    }

    protected function saveDocuments(Lead $lead, Request $request): void
    {
        $sync = $this->sectionSent($request, 'document') || $request->attributes->get('document_sync');
        if (!$sync && !$request->hasFile('documents')) {
            return;
        }

        $existing = array_values(array_filter((array) ($lead->document ?? [])));
        $documents = $sync ? [] : $existing;

        if ($sync) {
            foreach ($this->rows($request, 'document') as $row) {
                if ($doc = $this->matchDocument($existing, $row)) {
                    $documents[] = $doc;
                }
            }
            foreach ($existing as $doc) {
                $path = is_array($doc) ? ($doc['path'] ?? '') : (string) $doc;
                $kept = collect($documents)->contains(fn ($item) => (is_array($item) ? ($item['path'] ?? '') : $item) === $path);
                if ($path && !$kept) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        if ($request->hasFile('documents')) {
            $names = array_column($documents, 'name');
            foreach ($request->file('documents') as $index => $file) {
                if (in_array($file->getClientOriginalName(), $names, true)) {
                    continue;
                }
                $documents[] = [
                    'type' => $request->input("document_types.$index") ?: 'Other',
                    'path' => $file->storeAs('crm/leads/documents', 'lead_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension(), 'public'),
                    'name' => $file->getClientOriginalName(),
                ];
            }
        }

        $lead->forceFill(['document' => $documents ?: null])->save();
    }

    protected function matchDocument(array $existing, $row): ?array
    {
        if (is_numeric($row)) {
            $row = ['index' => (int) $row];
        } elseif (is_string($row)) {
            $row = str_contains($row, '/') ? ['path' => $row] : ['name' => $row];
        } elseif (!is_array($row)) {
            return null;
        }

        if (isset($row['index']) && isset($existing[(int) $row['index']])) {
            $doc = $existing[(int) $row['index']];
            return is_array($doc) ? $doc : ['type' => 'Other', 'path' => $doc, 'name' => basename((string) $doc)];
        }

        $path = $this->documentPath($row);
        $name = trim((string) ($row['name'] ?? ''));

        foreach ($existing as $doc) {
            $docPath = is_array($doc) ? ($doc['path'] ?? '') : (string) $doc;
            $docName = is_array($doc) ? ($doc['name'] ?? basename($docPath)) : basename((string) $doc);
            if (($path && $docPath === $path) || ($name !== '' && $docName === $name)) {
                return is_array($doc) ? $doc : ['type' => 'Other', 'path' => $doc, 'name' => basename((string) $doc)];
            }
        }

        return null;
    }

    protected function documentPath(array $row): ?string
    {
        $path = trim((string) ($row['path'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        if ($path !== '' && !str_contains($path, '://')) {
            return $path;
        }
        foreach ([$url, $path] as $value) {
            if ($value !== '' && preg_match('#/storage/(.+?)(?:\?|$)#', $value, $match)) {
                return urldecode($match[1]);
            }
        }

        return $path !== '' ? $path : null;
    }

    protected function flattenLeadPayload(Request $request, ?Lead $lead = null): void
    {
        foreach (['customer_detail', 'address_information', 'domain', 'lead_specification', 'follow_up_detail', 'current_service_info', 'our_service_plan_detail', 'additional_contact', 'document', 'documents'] as $key) {
            $decoded = is_string($request->input($key)) ? json_decode($request->input($key), true) : null;
            if (is_array($decoded)) {
                array_walk_recursive($decoded, function (&$value) {
                    $value = $value === '' ? null : $value;
                });
                $request->merge([$key => $decoded]);
            }
        }

        // App may send existing docs as "documents" JSON — treat as keep-list, not file uploads
        if (!$request->hasFile('documents') && (is_array($request->input('documents')) || $request->exists('documents'))) {
            if (!$this->sectionSent($request, 'document')) {
                $request->merge(['document' => is_array($request->input('documents')) ? $request->input('documents') : []]);
            }
            $request->request->remove('documents');
            $request->attributes->set('document_sync', true);
        }
        if ($this->sectionSent($request, 'document')) {
            $request->attributes->set('document_sync', true);
        }

        foreach (['customer_detail', 'address_information', 'domain', 'lead_specification', 'follow_up_detail'] as $key) {
            if (is_array($request->input($key))) {
                $request->merge($request->input($key));
            }
        }

        foreach (['current_service_info', 'our_service_plan_detail', 'additional_contact', 'document'] as $key) {
            if ($request->exists($key) && !is_array($request->input($key))) {
                $request->merge([$key => []]);
            }
        }

        if ($request->exists('current_service_info')) {
            $request->merge(['vendor_services' => $request->current_service_info]);
        }

        if ($request->exists('our_service_plan_detail')) {
            $request->merge(['plans' => $request->our_service_plan_detail]);
        }

        if ($request->exists('additional_contact')) {
            $request->merge(['contacts' => $request->additional_contact]);
        }

        if ($request->filled('lead_priority') && ! $request->filled('lead_category')) {
            $request->merge(['lead_category' => $request->lead_priority]);
        }

        foreach ($lead ? ['salutation', 'name', 'designation', 'email', 'mobile', 'mobile_code', 'company_name', 'industry', 'no_of_employees', 'gstin', 'mailing_address', 'country', 'state', 'city', 'pincode', 'assigned_to', 'lead_category', 'lead_source', 'lead_bifurcation', 'lead_status', 'business_type', 'domain_type', 'domain', 'domain_period', 'domain_rate', 'sac', 'domain_start', 'domain_end', 'domain_renewal_date'] : [] as $field) {
            if (blank($request->input($field))) {
                $request->merge([$field => $lead->getAttribute($field)]);
            }
        }

        if ($request->has('domain_type') && !is_null($request->input('domain_type'))) {
            $type = strtolower(is_string($request->input('domain_type')) ? $request->input('domain_type') : '');
            $request->merge(['domain_type' => str_contains($type, 'new') ? 'new' : (str_contains($type, 'exist') || str_contains($type, 'already') ? 'existing' : null)]);
        }

        $file = $request->files->get('documents');
        if ($file && !is_array($file)) {
            $request->files->set('documents', [$file]);
        }

        if (is_string($request->input('document_types'))) {
            $types = json_decode($request->input('document_types'), true);
            $request->merge(['document_types' => is_array($types) ? $types : [$request->input('document_types')]]);
        }
    }

    protected function documentsWithUrl(Lead $lead): array
    {
        return array_values(array_map(function ($doc) {
            $doc = is_array($doc) ? $doc : ['type' => 'Other', 'path' => $doc, 'name' => basename((string) $doc)];
            return $doc + ['url' => asset('storage/' . ($doc['path'] ?? ''))];
        }, array_filter((array) ($lead->document ?? []))));
    }
}
