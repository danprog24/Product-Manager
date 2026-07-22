<?php

namespace App\Http\Traits;

trait ApiResponse
{
    /**
     * Return a success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success', 
        int $statusCode = 200
    )
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(
        string $message = 'Error', 
        int $statusCode = 400
    )
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $statusCode);
    }
}
