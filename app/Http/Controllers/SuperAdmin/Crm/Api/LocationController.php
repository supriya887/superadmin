<?php

namespace App\Http\Controllers\SuperAdmin\Crm\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function countries()
    {
        return response()->json([
            'success' => true,
            'message' => 'Countries fetched successfully.',
            'data' => array_keys($this->locations()),
        ]);
    }

    public function states(Request $request)
    {
        $country = trim((string) $request->query('country'));
        $locations = $this->locations();

        if ($country === '' || !isset($locations[$country])) {
            return response()->json([
                'success' => false,
                'message' => 'Country is required.',
                'data' => [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'States fetched successfully.',
            'data' => array_keys($locations[$country]),
        ]);
    }

    public function cities(Request $request)
    {
        $country = trim((string) $request->query('country'));
        $state = trim((string) $request->query('state'));
        $locations = $this->locations();

        if ($country === '' || $state === '' || !isset($locations[$country][$state])) {
            return response()->json([
                'success' => false,
                'message' => 'Country and state are required.',
                'data' => [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cities fetched successfully.',
            'data' => $locations[$country][$state],
        ]);
    }

    protected function locations(): array
    {
        return [
            'India' => [
                'Andhra Pradesh' => ['Visakhapatnam', 'Vijayawada', 'Guntur'],
                'Delhi' => ['New Delhi', 'Dwarka', 'Rohini'],
                'Goa' => ['Panaji', 'Margao', 'Vasco'],
                'Gujarat' => ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot'],
                'Haryana' => ['Gurugram', 'Faridabad', 'Panipat'],
                'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru'],
                'Kerala' => ['Kochi', 'Thiruvananthapuram', 'Kozhikode'],
                'Madhya Pradesh' => ['Bhopal', 'Indore', 'Jabalpur'],
                'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur', 'Nashik', 'Thane'],
                'Punjab' => ['Ludhiana', 'Amritsar', 'Chandigarh'],
                'Rajasthan' => ['Jaipur', 'Udaipur', 'Jodhpur'],
                'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Madurai'],
                'Telangana' => ['Hyderabad', 'Warangal', 'Nizamabad'],
                'Uttar Pradesh' => ['Lucknow', 'Noida', 'Kanpur', 'Varanasi'],
                'West Bengal' => ['Kolkata', 'Howrah', 'Durgapur'],
            ],
            'United Arab Emirates' => [
                'Dubai' => ['Dubai', 'Deira', 'Jebel Ali'],
                'Abu Dhabi' => ['Abu Dhabi', 'Al Ain'],
                'Sharjah' => ['Sharjah'],
            ],
            'United States' => [
                'California' => ['Los Angeles', 'San Francisco', 'San Diego'],
                'New York' => ['New York', 'Buffalo'],
                'Texas' => ['Houston', 'Dallas', 'Austin'],
            ],
            'United Kingdom' => [
                'England' => ['London', 'Manchester', 'Birmingham'],
                'Scotland' => ['Edinburgh', 'Glasgow'],
                'Wales' => ['Cardiff'],
            ],
        ];
    }
}
