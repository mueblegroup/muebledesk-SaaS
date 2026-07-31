<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientApiController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = Client::query()->with('employee:id,name,email', 'user:id,name,email,role');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('tin_number', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->ok($clients->items(), $this->paginationMeta($clients));
    }

    public function show(Client $client)
    {
        return $this->ok($client->load('employee:id,name,email', 'user:id,name,email,role'));
    }

    public function store(Request $request, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules());

        $client = DB::transaction(function () use ($validated, $activityLogger) {
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'customer',
                ]
            );

            $client = Client::create(array_merge($validated, ['user_id' => $user->id]));
            $activityLogger->log('client.created', 'Client created via API', $client, [], $client->toArray());

            return $client;
        });

        return $this->created($client->fresh()->load('user:id,name,email,role'));
    }

    public function update(Request $request, Client $client, ActivityLogger $activityLogger)
    {
        $validated = $request->validate($this->rules($client));
        $old = $client->toArray();
        $client->update($validated);
        $activityLogger->log('client.updated', 'Client updated via API', $client, $old, $client->fresh()->toArray());

        return $this->ok($client->fresh());
    }

    public function destroy(Client $client, ActivityLogger $activityLogger)
    {
        abort_if($client->invoices()->exists() || $client->quotations()->exists(), 409, 'Client has related records and cannot be deleted.');
        $old = $client->toArray();
        $client->delete();
        $activityLogger->log('client.deleted', 'Client deleted via API', null, $old, []);

        return $this->deleted();
    }

    private function rules(?Client $client = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_type' => ['nullable', Rule::in(['individual', 'company', 'government', 'non_profit'])],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client?->id)],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'id_type' => ['nullable', Rule::in(['brn', 'nric', 'passport', 'army', 'other'])],
            'id_number' => ['nullable', 'string', 'max:255'],
            'sst_registration_number' => ['nullable', 'string', 'max:255'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'employee_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
