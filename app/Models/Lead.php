<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $connection = 'pgsql_crm';

    protected $table = 'leads';

    protected $fillable = [
        'name',
        'salutation',
        'designation',
        'email',
        'mobile',
        'mobile_code',
        'company_name',
        'industry',
        'no_of_employees',
        'gstin',

        'lead_category',
        'lead_status',
        'lead_source',
        'lead_bifurcation',
        'justdial_lead_id',
        'assigned_to',
        'business_type',

        'mailing_address',
        'country',
        'state',
        'city',
        'pincode',

        'follow_up_date',
        'follow_up_note',

        'document',
        'domain',
        'domain_type',
        'domain_rate',
        'domain_period',
        'sac',
        'domain_start',
        'domain_end',
        'domain_renewal_date',
    ];

    protected $casts = [
        'follow_up_date' => 'datetime',
        'domain_start' => 'date',
        'domain_end' => 'date',
        'domain_renewal_date' => 'date',
        'no_of_employees' => 'integer',
        'document' => 'array',
    ];

    protected $appends = [
        'lead_priority',
    ];

    public function getLeadPriorityAttribute(): ?string
    {
        $value = $this->attributes['lead_category'] ?? null;

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setLeadPriorityAttribute($value): void
    {
        $this->attributes['lead_category'] = $value !== null && $value !== '' ? $value : null;
    }

    public function contacts()
    {
        return $this->hasMany(LeadContact::class, 'lead_id');
    }

    public function services()
    {
        return $this->hasMany(LeadService::class, 'lead_id');
    }

    public static function assigneeKeys($employee): array
    {
        return array_values(array_unique(array_filter([
            (string) ($employee->id ?? ''),
            trim((string) ($employee->name ?? '')),
        ])));
    }

    public function scopeForEmployee($query, $employee)
    {
        $name = strtolower(trim((string) ($employee->name ?? '')));
        $id = (string) ($employee->id ?? '');

        return $query->where(function ($q) use ($name, $id) {
            if ($id !== '') {
                $q->orWhere('assigned_to', $id);
            }
            if ($name !== '') {
                $q->orWhereRaw('LOWER(TRIM(assigned_to::text)) = ?', [$name]);
            }
        });
    }

    public static function dialCodes(): array
    {
        return [
            '93' => 'Afghanistan',
            '355' => 'Albania',
            '213' => 'Algeria',
            '376' => 'Andorra',
            '244' => 'Angola',
            '54' => 'Argentina',
            '374' => 'Armenia',
            '61' => 'Australia',
            '43' => 'Austria',
            '994' => 'Azerbaijan',
            '973' => 'Bahrain',
            '880' => 'Bangladesh',
            '375' => 'Belarus',
            '32' => 'Belgium',
            '501' => 'Belize',
            '229' => 'Benin',
            '975' => 'Bhutan',
            '591' => 'Bolivia',
            '387' => 'Bosnia',
            '267' => 'Botswana',
            '55' => 'Brazil',
            '673' => 'Brunei',
            '359' => 'Bulgaria',
            '226' => 'Burkina Faso',
            '257' => 'Burundi',
            '855' => 'Cambodia',
            '237' => 'Cameroon',
            '1' => 'USA / Canada',
            '238' => 'Cape Verde',
            '236' => 'Central African Republic',
            '235' => 'Chad',
            '56' => 'Chile',
            '86' => 'China',
            '57' => 'Colombia',
            '269' => 'Comoros',
            '242' => 'Congo',
            '506' => 'Costa Rica',
            '385' => 'Croatia',
            '53' => 'Cuba',
            '357' => 'Cyprus',
            '420' => 'Czech Republic',
            '45' => 'Denmark',
            '253' => 'Djibouti',
            '593' => 'Ecuador',
            '20' => 'Egypt',
            '503' => 'El Salvador',
            '240' => 'Equatorial Guinea',
            '291' => 'Eritrea',
            '372' => 'Estonia',
            '251' => 'Ethiopia',
            '679' => 'Fiji',
            '358' => 'Finland',
            '33' => 'France',
            '241' => 'Gabon',
            '220' => 'Gambia',
            '995' => 'Georgia',
            '49' => 'Germany',
            '233' => 'Ghana',
            '30' => 'Greece',
            '502' => 'Guatemala',
            '224' => 'Guinea',
            '245' => 'Guinea-Bissau',
            '592' => 'Guyana',
            '509' => 'Haiti',
            '504' => 'Honduras',
            '852' => 'Hong Kong',
            '36' => 'Hungary',
            '354' => 'Iceland',
            '91' => 'India',
            '62' => 'Indonesia',
            '98' => 'Iran',
            '964' => 'Iraq',
            '353' => 'Ireland',
            '972' => 'Israel',
            '39' => 'Italy',
            '225' => 'Ivory Coast',
            '81' => 'Japan',
            '962' => 'Jordan',
            '7' => 'Kazakhstan / Russia',
            '254' => 'Kenya',
            '686' => 'Kiribati',
            '965' => 'Kuwait',
            '996' => 'Kyrgyzstan',
            '856' => 'Laos',
            '371' => 'Latvia',
            '961' => 'Lebanon',
            '266' => 'Lesotho',
            '231' => 'Liberia',
            '218' => 'Libya',
            '423' => 'Liechtenstein',
            '370' => 'Lithuania',
            '352' => 'Luxembourg',
            '853' => 'Macau',
            '389' => 'Macedonia',
            '261' => 'Madagascar',
            '265' => 'Malawi',
            '60' => 'Malaysia',
            '960' => 'Maldives',
            '223' => 'Mali',
            '356' => 'Malta',
            '222' => 'Mauritania',
            '230' => 'Mauritius',
            '52' => 'Mexico',
            '373' => 'Moldova',
            '377' => 'Monaco',
            '976' => 'Mongolia',
            '382' => 'Montenegro',
            '212' => 'Morocco',
            '258' => 'Mozambique',
            '95' => 'Myanmar',
            '264' => 'Namibia',
            '977' => 'Nepal',
            '31' => 'Netherlands',
            '64' => 'New Zealand',
            '505' => 'Nicaragua',
            '227' => 'Niger',
            '234' => 'Nigeria',
            '850' => 'North Korea',
            '47' => 'Norway',
            '968' => 'Oman',
            '92' => 'Pakistan',
            '970' => 'Palestine',
            '507' => 'Panama',
            '675' => 'Papua New Guinea',
            '595' => 'Paraguay',
            '51' => 'Peru',
            '63' => 'Philippines',
            '48' => 'Poland',
            '351' => 'Portugal',
            '974' => 'Qatar',
            '40' => 'Romania',
            '250' => 'Rwanda',
            '966' => 'Saudi Arabia',
            '221' => 'Senegal',
            '381' => 'Serbia',
            '248' => 'Seychelles',
            '232' => 'Sierra Leone',
            '65' => 'Singapore',
            '421' => 'Slovakia',
            '386' => 'Slovenia',
            '252' => 'Somalia',
            '27' => 'South Africa',
            '82' => 'South Korea',
            '211' => 'South Sudan',
            '34' => 'Spain',
            '94' => 'Sri Lanka',
            '249' => 'Sudan',
            '597' => 'Suriname',
            '268' => 'Swaziland',
            '46' => 'Sweden',
            '41' => 'Switzerland',
            '963' => 'Syria',
            '886' => 'Taiwan',
            '992' => 'Tajikistan',
            '255' => 'Tanzania',
            '66' => 'Thailand',
            '228' => 'Togo',
            '676' => 'Tonga',
            '216' => 'Tunisia',
            '90' => 'Turkey',
            '993' => 'Turkmenistan',
            '256' => 'Uganda',
            '380' => 'Ukraine',
            '971' => 'UAE',
            '44' => 'United Kingdom',
            '598' => 'Uruguay',
            '998' => 'Uzbekistan',
            '678' => 'Vanuatu',
            '58' => 'Venezuela',
            '84' => 'Vietnam',
            '967' => 'Yemen',
            '260' => 'Zambia',
            '263' => 'Zimbabwe',
        ];
    }

    public static function orderedDialCodes(): array
    {
        $codes = self::dialCodes();
        if (isset($codes['91'])) {
            $india = $codes['91'];
            unset($codes['91']);
            return ['91' => $india] + $codes;
        }

        return $codes;
    }
}