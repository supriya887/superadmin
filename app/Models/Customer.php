<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Partner;
use App\Models\Order;

class Customer extends Model
{
    protected $connection = 'pgsql_billing';
    protected $table = 'customers';
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'customer_no',
        'gstin',
        'name',
        'email',
        'mobile',
        'company_name',
        'company_address',
        'country',
        'state',
        'city',
        'zip_code',
        'postcode',
        'pincode',
        'pan_no',
        'legal_name',
        'trade_name',
        'address',
        'gst_status',
        'last_updated',
        'constitution',
        'registration_date',
        'nature_of_business',
        'status',
    ];

    /**
     * Get the partner who owns the customer.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * Generate the next sequential customer number for a partner (e.g. CUST10001).
     */
    public static function getNextCustomerNo(?int $partnerId = null): string
    {
        if (!$partnerId) {
            return '';
        }

        $lastCustomer = static::where('partner_id', $partnerId)
            ->orderByDesc('id')
            ->first();

        if ($lastCustomer && $lastCustomer->customer_no) {
            $lastNumber = (int) preg_replace('/\D/', '', $lastCustomer->customer_no);
            $customerNo = 'CUST' . ($lastNumber + 1);
        } else {
            $customerNo = 'CUST10001';
        }

        while (static::where('partner_id', $partnerId)->where('customer_no', $customerNo)->exists()) {
            $num = (int) preg_replace('/\D/', '', $customerNo) + 1;
            $customerNo = 'CUST' . $num;
        }

        return $customerNo;
    }
}
