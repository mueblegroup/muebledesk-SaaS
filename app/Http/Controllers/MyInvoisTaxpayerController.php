<?php

namespace App\Http\Controllers;

use App\Services\MyInvois\MyInvoisClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class MyInvoisTaxpayerController extends Controller
{
    public function search(Request $request, MyInvoisClient $client): JsonResponse
    {
        $validated = $request->validate([
            'id_type' => ['required', 'string', Rule::in(['NRIC', 'BRN', 'PASSPORT', 'ARMY'])],
            'id_number' => ['required', 'string', 'max:100'],
        ]);

        $idType = strtoupper($validated['id_type']);
        $idNumber = preg_replace('/[^A-Za-z0-9]/', '', $validated['id_number']);
        $fileType = $idType === 'NRIC' ? '1' : null;

        try {
            $tin = $client->searchTin($idType, $idNumber, $fileType);

            if (! $tin) {
                return response()->json([
                    'message' => 'No matching taxpayer was found in the '.strtoupper($client->environment()).' environment.',
                ], 404);
            }

            $verified = $client->validateTin($tin, $idType, $idNumber);

            return response()->json([
                'tin' => $tin,
                'id_type' => $idType,
                'id_number' => $idNumber,
                'country_code' => $idType === 'NRIC' ? 'MYS' : null,
                'verified' => $verified,
                'environment' => strtoupper($client->environment()),
                'message' => $verified
                    ? 'Taxpayer found and verified with MyInvois.'
                    : 'A TIN was found, but the identity combination could not be verified.',
            ]);
        } catch (Throwable $exception) {
            Log::error('MyInvois taxpayer lookup failed.', [
                'id_type' => $idType,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to search MyInvois right now: '.$exception->getMessage(),
            ], 422);
        }
    }
}
