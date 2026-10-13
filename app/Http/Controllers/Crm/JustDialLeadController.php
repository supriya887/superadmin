<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmUser;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JustDialLeadController extends Controller
{
    public function store(Request $request)
    {
        $this->prepareColumns();

        $leadId = trim((string) $request->input('leadid'));
        if ($leadId === '') {
            return $this->received();
        }
        if (Lead::withTrashed()->where('justdial_lead_id', $leadId)->exists()) {
            return $this->received();
        }

        $name = trim((string) $request->input('name'));
        $mobile = $this->digits($request->input('mobile'));
        $phone = trim((string) $request->input('phone'));
        if ($mobile === '') {
            $mobile = $this->digits($phone);
        }

        $email = trim((string) $request->input('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $prefix = trim((string) $request->input('prefix'));
        $salutation = null;
        foreach (['Mr', 'Ms', 'Mrs', 'Dr'] as $title) {
            if (strcasecmp($prefix, $title) === 0) {
                $salutation = $title;
                break;
            }
        }

        $company = trim((string) $request->input('company'));
        $pincode = trim((string) $request->input('pincode'));
        if ($pincode === '' || $pincode === '0') {
            $pincode = null;
        }

        $created = $this->leadTime($request->input('date'), $request->input('time'));

        $lead = new Lead();
        $lead->name = $name !== '' ? $name : 'Unknown';
        $lead->salutation = $salutation;
        $lead->mobile = $mobile !== '' ? $mobile : null;
        $lead->email = $email !== '' ? $email : null;
        $lead->company_name = $company !== '' ? $company : null;
        $lead->industry = trim((string) $request->input('category')) ?: null;
        $lead->city = trim((string) $request->input('city')) ?: null;
        $lead->mailing_address = trim((string) $request->input('area')) ?: null;
        $lead->pincode = $pincode;
        $lead->country = 'India';
        $lead->lead_source = 'JustDial';
        $lead->lead_status = 'New';
        $lead->lead_category = 'Normal';
        $lead->follow_up_note = $this->note($request) ?: null;
        $lead->justdial_lead_id = $leadId !== '' ? $leadId : null;
        $lead->assigned_to = $this->assignee();
        if ($created) {
            $lead->created_at = $created;
            $lead->updated_at = $created;
        }
        try {
            $lead->save();
        } catch (\Throwable $e) {
            if ($leadId !== '' && Lead::withTrashed()->where('justdial_lead_id', $leadId)->exists()) {
                return $this->received();
            }
            throw $e;
        }

        return $this->received();
    }

    private function received()
    {
        return response('RECEIVED', 200)->header('Content-Type', 'text/plain');
    }

    private function note(Request $request): string
    {
        $lines = [];
        foreach ([
            'Lead type' => $request->input('leadtype'),
            'Phone' => $request->input('phone'),
            'Branch area' => $request->input('brancharea'),
            'Branch pin' => $request->input('branchpin'),
            'Parent ID' => $request->input('parentid'),
        ] as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '' && $value !== '0') {
                $lines[] = $label . ': ' . $value;
            }
        }
        if ((string) $request->input('dncmobile') === '1') {
            $lines[] = 'Mobile is DND';
        }
        if ((string) $request->input('dncphone') === '1') {
            $lines[] = 'Phone is DND';
        }

        return implode("\n", $lines);
    }

    private function assignee(): ?string
    {
        $employees = CrmUser::query()
            ->where('status', 'active')
            ->whereNotNull('jd_lead_assign')
            ->get();

        $picked = null;
        $lowest = null;

        foreach ($employees as $employee) {
            $raw = strtolower(trim((string) $employee->jd_lead_assign));
            $canAssign = in_array($raw, ['yes', '1', 'true', 'on'], true)
                || (ctype_digit($raw) && (int) $raw > 0);
            if (!$canAssign) {
                continue;
            }

            $count = Lead::query()
                ->where('lead_source', 'JustDial')
                ->where('assigned_to', $employee->name)
                ->count();

            if ($lowest === null || $count < $lowest) {
                $lowest = $count;
                $picked = $employee->name;
            }
        }

        return $picked;
    }

    private function leadTime($date, $time): ?Carbon
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '') {
            return null;
        }
        if ($time === '') {
            $time = '00:00:00';
        }
        try {
            return Carbon::parse($date . ' ' . $time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function digits($value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function prepareColumns(): void
    {
        $db = DB::connection('pgsql_crm');
        $schema = Schema::connection('pgsql_crm');

        if (!$schema->hasColumn('leads', 'justdial_lead_id')) {
            $db->statement('ALTER TABLE leads ADD COLUMN IF NOT EXISTS justdial_lead_id varchar(255) NULL');
        }
        $db->statement('CREATE UNIQUE INDEX IF NOT EXISTS leads_justdial_lead_id_unique ON leads (justdial_lead_id)');

        foreach (['email', 'mobile', 'company_name', 'salutation'] as $column) {
            if ($schema->hasColumn('leads', $column)) {
                $db->statement('ALTER TABLE leads ALTER COLUMN ' . $column . ' DROP NOT NULL');
            }
        }
        if (!$schema->hasColumn('leads', 'salutation')) {
            $db->statement('ALTER TABLE leads ADD COLUMN IF NOT EXISTS salutation varchar(10) NULL');
        }

        try {
            $uniques = $db->select("
                SELECT con.conname
                FROM pg_constraint con
                JOIN pg_class rel ON rel.oid = con.conrelid
                WHERE rel.relname = 'leads'
                  AND con.contype = 'u'
                  AND pg_get_constraintdef(con.oid) ~* '\\m(email|mobile)\\M'
            ");
            foreach ($uniques as $unique) {
                $db->statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS ' . $unique->conname);
            }
        } catch (\Throwable $e) {
            // A duplicate mobile or email must still be saved as a new lead.
        }
    }
}
