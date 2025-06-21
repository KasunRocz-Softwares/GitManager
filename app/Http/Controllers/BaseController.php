<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Base controller with common functionality for all controllers.
 */
abstract class BaseController extends Controller
{
    /**
     * Create a standardized success response.
     *
     * @param mixed $data Response data
     * @param string|null $message Success message
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse($data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    /**
     * Create a standardized error response.
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param Exception|null $exception Exception that occurred
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $status = 500, ?Exception $exception = null): JsonResponse
    {
        if ($exception) {
            Log::error($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $message
        ], $status);
    }
}
