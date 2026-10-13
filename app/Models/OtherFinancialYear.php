<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherFinancialYear extends Model
{
    /**
     * The database connection used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_billing';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'other_financial_year';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'short_year',         // varchar(32)
        'financial_year',     // varchar(32)
        'finyear',            // varchar(32)
        'start_date',         // date
        'end_date',           // date
        'status',             // int(2)
        'updated_at'          // timestamp, nullable
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id'           => 'integer',
        'short_year'   => 'string',
        'financial_year' => 'string',
        'finyear'      => 'string',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'status'       => 'integer',
        'updated_at'   => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Set updated_at attribute if present.
     */
    public function setUpdatedAt($value)
    {
        $this->attributes['updated_at'] = $value;
    }
}
