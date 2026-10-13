<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPoDetail extends Model
{
    protected $connection = 'pgsql_billing';
    use HasFactory;
    // The associated table
    protected $table = 'order_po_details';
    // Mass assignable attributes
    protected $fillable = [
        'order_id',
        'po_mode',
        'po_number',
        'po_date',
        'po_amount',
        'po_document_url',
        'manual_name',
        'manual_email',
        'manual_mobile',
        'manual_gstin',
        'manual_company_name',
        'manual_company_address',
        'manual_city',
        'manual_state',
        'manual_country',
        'manual_pincode',
        'journey_status',
        'aadhar_verified',
        'aadhar_verified_at',
        'aadhaar_ref_no',
        'aadhaar_masked',
        'sign_required',
        'status',
        'final_po_link',
    ];

    // Attribute casting
    protected $casts = [
        'id'                   => 'integer',
        'order_id'             => 'integer',
        'po_mode'              => 'string',
        'po_number'            => 'string',
        'po_date'              => 'date',
        'po_amount'            => 'decimal:2',
        'po_document_url'      => 'string',
        'manual_name'          => 'string',
        'manual_email'         => 'string',
        'manual_mobile'        => 'string',
        'manual_gstin'         => 'string',
        'manual_company_name'  => 'string',
        'manual_company_address'=> 'string',
        'manual_city'          => 'string',
        'manual_state'         => 'string',
        'manual_country'       => 'string',
        'manual_pincode'       => 'string',
        'journey_status'       => 'string',
        'aadhar_verified'      => 'boolean',
        'aadhar_verified_at'   => 'datetime',
        'aadhaar_ref_no'       => 'string',
        'aadhaar_masked'       => 'string',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // The primary key is "id" and is auto-incremented (default for Laravel).
    // Timestamps enabled by default

    /**
     * Get the order associated with this PO detail.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
