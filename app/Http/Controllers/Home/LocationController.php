<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    /**
     * Get all countries.
     */
    public function getCountries()
    {
        $countries = DB::table('location_mst')
            ->select('location_id as id', 'location_name as name')
            ->orderBy('location_name', 'asc')
            ->get();

        return response()->json($countries);
    }

    /**
     * Get states for a country.
     */
    public function getStates($countryId)
    {
        // Based on Admin\Home.php, states are in 'other_state'
        $states = DB::table('other_state')
            ->where('country_id', $countryId)
            ->select('id', 'state as name')
            ->orderBy('state', 'asc')
            ->get();

        return response()->json($states);
    }

    /**
     * Get cities for a state.
     */
    public function getCities($stateId)
    {
        // Many systems use location_mst for hierarchy, checking location_type = 2
        $cities = DB::table('location_mst')
            ->where('parent_id', $stateId)
            ->where('location_type', 2)
            ->select('location_id as id', 'location_name as name')
            ->orderBy('location_name', 'asc')
            ->get();

        return response()->json($cities);
    }
}
