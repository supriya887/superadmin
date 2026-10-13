<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;


class Partner extends Model
{
    use HasFactory, HasApiTokens;

    protected $connection = 'pgsql_auth';
    protected $table = 'partners';

    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
        'company_name',
        'legal_name',
        'trade_name',
        'gstin',
        'pan_no',
        'company_address',
        'address',
        'country',
        'state',
        'city',
        'postcode',
        'pincode',
        'gst_status',
        'last_updated',
        'constitution',
        'registration_date',
        'nature_of_business',
        'gst_id',
        'email_verified',
        'approveby',
        'status',
        'rejection_reason',
        'approve_datetime',
        'credit_limit',
        'wallet_balance',
        'aadhaar_mandatory',
        'partner_discount_type',
        'partner_discount_percent',
        'discount_percent',
        'distributor_discount',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'last_updated' => 'date',
        'registration_date' => 'date',
        'approve_datetime' => 'datetime',
        'credit_limit' => 'decimal:2',
        'wallet_balance' => 'decimal:2',
        'partner_discount_percent' => 'float',
        'discount_percent' => 'float',
        'distributor_discount' => 'float',
    ];

    protected static function booted(): void
    {
        static::updating(function (Partner $partner) {
            if (!$partner->isDirty('credit_limit')) {
                return;
            }

            $new = round((float) ($partner->credit_limit ?? 0), 2);
            $old = round((float) ($partner->getOriginal('credit_limit') ?? 0), 2);

            if ($new <= 0.005 && $old > 0.005) {
                throw ValidationException::withMessages([
                    'credit_limit' => 'Credit limit cannot be set to zero.',
                ]);
            }
        });
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'partner_id');
    }

    public function creditRequests(): HasMany
    {
        return $this->hasMany(CreditRequest::class, 'partner_id');
    }

    public function invoicePaymentTransactions(): HasMany
    {
        return $this->hasMany(InvoicePaymentTransaction::class, 'partner_id');
    }

    public function isAadhaarMandatory(): bool
    {
        return strtolower(trim((string) ($this->aadhaar_mandatory ?? 'no'))) === 'yes';
    }

    public static function aadhaarMandatoryForId(?int $partnerId): bool
    {
        if ($partnerId === null || $partnerId <= 0) {
            return false;
        }

        try {
            $value = static::query()->whereKey($partnerId)->value('aadhaar_mandatory');
        } catch (\Throwable $e) {
            return false;
        }

        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return strtolower(trim((string) ($value ?? 'no'))) === 'yes';
    }
    public function permissions(): HasMany
    {
        return $this->hasMany(PartnerPermission::class, 'partner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(PartnerUser::class, 'partner_id');
    }
}
