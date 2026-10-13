<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function successResponse($message, $data = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'error' => false,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function errorResponse($message, $code = 400, $data = null)
    {
        return response()->json([
            'success' => false,
            'error' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function validationErrorResponse($validator)
    {
        $errors = $validator->errors();
        
        if ($errors->count() === 1) {
            $field = $errors->keys()[0];
            return response()->json([
                'error' => true,
                'message' => $errors->first(),
                $field => $errors->first(),
                'data' => null
            ], 422);
        }

        return response()->json([
            'error' => true,
            'message' => 'Validation failed',
            'errors' => $errors->toArray()
        ], 422);
    }
}
