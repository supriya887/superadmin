<?php

namespace App\Services;

use App\Models\SupportTicketCounter;
use Illuminate\Support\Facades\DB;

class SupportTicketIdService
{
    /**
     * Next public ticket number: SUP-YY-0001 (YY = 2-digit year, serial padded 4).
     */
    public function nextTicketNo(): string
    {
        return DB::connection('pgsql_support')->transaction(function () {
            $year = (int) now()->format('y');

            $row = SupportTicketCounter::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                SupportTicketCounter::create([
                    'year' => $year,
                    'next_serial' => 2,
                ]);

                return sprintf('SUP-%s-%04d', $year, 1);
            }

            $serial = (int) $row->next_serial;
            $row->next_serial = $serial + 1;
            $row->save();

            return sprintf('SUP-%s-%04d', $year, $serial);
        });
    }
}
