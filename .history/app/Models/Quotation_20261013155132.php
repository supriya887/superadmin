<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    protected $connection = 'pgsql_crm';

    protected $table = 'quotations';

    public const DEAL_TYPES = [
        'new' => 'New',
        'license_addon' => 'License Add-on',
        'renewal' => 'Renewal',
        'upgrade' => 'Upgrade',
        'downgrade' => 'Downgrade',
        'partial_upgrade' => 'Partial Upgrade',
    ];

    protected $fillable = [
        'quotation_no',
        'deal_type',
        'lead_id',
        'company_name',
        'gstin',
        'name',
        'salutation',
        'email',
        'mobile_code',
        'designation',
        'mailing_address',
        'city',
        'state',
        'pincode',
        'country',
        'provider_id',
        'service_name',
        'valid_date',
        'discount',
        'quotation_period',
        'domain_name',
        'sac',
        'domain_start',
        'domain_end',
        'domain_rate',
        'plans',
        'plan_name',
        'quantity',
        'total_amount',
        'discount_status',
        'quotation_status',
        'invoice_status',
        'assigned_to',
        'follow_up_date',
        'sent_at',
        'sent_emails',
        'sent_cc_emails',
        'responded_email',
        'responded_at',
        'invoice_sent_at',
        'invoice_sent_emails',
        'invoice_sent_cc_emails',
        'invoice_responded_email',
        'invoice_responded_at',
        'order_id',
        'crm_order_no',
        'po_mode',
        'po_number',
        'po_date',
        'po_amount',
        'po_document_url',
        'po_journey_status',
    ];

    protected $casts = [
        'valid_date' => 'date',
        'domain_start' => 'date',
        'domain_end' => 'date',
        'follow_up_date' => 'date',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'invoice_sent_at' => 'datetime',
        'invoice_responded_at' => 'datetime',
        'plans' => 'array',
        'sent_emails' => 'array',
        'sent_cc_emails' => 'array',
        'invoice_sent_emails' => 'array',
        'invoice_sent_cc_emails' => 'array',
        'discount' => 'decimal:2',
        'domain_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'po_date' => 'date',
        'po_amount' => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function scopeCrmDeals($query)
    {
        return $query
            ->whereRaw("LOWER(TRIM(COALESCE(quotation_status, ''))) = 'approved'")
            ->whereRaw("LOWER(TRIM(COALESCE(invoice_status, ''))) = 'approved'");
    }

    public function isCrmDeal(): bool
    {
        return strtolower(trim((string) $this->quotation_status)) === 'approved'
            && strtolower(trim((string) $this->invoice_status)) === 'approved';
    }

    public function dealTypeLabel(): string
    {
        return self::DEAL_TYPES[$this->deal_type ?: 'new'] ?? 'New';
    }

    public function employeeName(): string
    {
        $raw = trim((string) ($this->assigned_to ?: optional($this->lead)->assigned_to ?: ''));
        if ($raw === '') {
            return 'Unassigned';
        }
        if (ctype_digit($raw)) {
            $name = CrmUser::query()->where('id', (int) $raw)->value('name');

            return $name ?: ('Employee #' . $raw);
        }

        return $raw;
    }

    public function siblingQuery()
    {
        $query = static::query();

        if ($this->lead_id) {
            return $query->where('lead_id', $this->lead_id);
        }

        return $query->where('email', $this->email)->whereNull('lead_id');
    }

    public function isLatest(): bool
    {
        if (isset($this->attributes['is_latest_version'])) {
            return (bool) $this->attributes['is_latest_version'];
        }

        return (int) $this->siblingQuery()->max('id') === (int) $this->id;
    }

    public function isLocked(): bool
    {
        if (isset($this->attributes['is_locked'])) {
            return (bool) $this->attributes['is_locked'];
        }

        return strtolower((string) $this->invoice_status) === 'approved'
            || strtolower((string) ($this->quotation_status ?? '')) === 'approved';
    }

    public static function attachVersions($quotations): void
    {
        $items = collect($quotations instanceof \Illuminate\Support\Collection ? $quotations->all() : $quotations);
        if ($items->isEmpty()) {
            return;
        }

        $leadIds = $items->pluck('lead_id')->filter()->unique()->values();
        $emails = $items->whereNull('lead_id')->pluck('email')->filter()->unique()->values();

        $siblings = collect();
        if ($leadIds->isNotEmpty()) {
            $siblings = $siblings->merge(static::whereIn('lead_id', $leadIds)->orderBy('id')->get(['id', 'lead_id', 'email', 'invoice_status']));
        }
        if ($emails->isNotEmpty()) {
            $siblings = $siblings->merge(
                static::whereNull('lead_id')->whereIn('email', $emails)->orderBy('id')->get(['id', 'lead_id', 'email', 'invoice_status'])
            );
        }

        $grouped = $siblings->groupBy(fn ($q) => $q->lead_id ? 'l:' . $q->lead_id : 'e:' . $q->email);

        foreach ($items as $quotation) {
            $key = $quotation->lead_id ? 'l:' . $quotation->lead_id : 'e:' . $quotation->email;
            $group = collect($grouped->get($key, []))->sortBy('id')->values();
            $total = max($group->count(), 1);
            $index = $group->search(fn ($row) => (int) $row->id === (int) $quotation->id);
            $version = $index === false ? 1 : ($index + 1);
            $locked = strtolower((string) $quotation->invoice_status) === 'approved'
                || strtolower((string) ($quotation->quotation_status ?? '')) === 'approved';

            $quotation->setAttribute('version_no', $version);
            $quotation->setAttribute('version_total', $total);
            $quotation->setAttribute('is_latest_version', $version === $total);
            $quotation->setAttribute('is_locked', $locked);
        }
    }
}
