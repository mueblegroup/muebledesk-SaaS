<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredClients($request);
        $clients = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function export(Request $request)
    {
        $clients = $this->filteredClients($request)->get();

        return response()->streamDownload(function () use ($clients) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Type', 'Contact Person', 'Email', 'Billing Email', 'Phone', 'TIN Number', 'ID Type', 'ID Number', 'SST Number', 'City', 'State', 'Country', 'Created At']);

            foreach ($clients as $client) {
                fputcsv($handle, [
                    $client->name,
                    $client->client_type,
                    $client->contact_person,
                    $client->email,
                    $client->billing_email,
                    $client->phone,
                    $client->tin_number,
                    $client->id_type,
                    $client->id_number,
                    $client->sst_registration_number,
                    $client->city,
                    $client->state,
                    $client->country_code,
                    optional($client->created_at)->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'clients.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $clients = Client::query()
            ->when(Auth::user()?->isEmployee(), fn ($query) => $query->where('employee_id', Auth::id()))
            ->whereIn('id', $ids)
            ->get();

        foreach ($clients as $client) {
            $this->authorize('delete', $client);
            if ($client->user) {
                $client->user->delete();
            }
            $client->delete();
        }

        return redirect()->route('clients.index')->with('success', $clients->count().' client(s) deleted successfully.');
    }

    public function create()
    {
        $this->authorize('create', Client::class);
        return view('clients.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Client::class);
        $validatedClientData = $request->validate($this->clientRules() + [
            'send_password_setup_link' => 'nullable|boolean',
        ]);

        try {
            $plainPassword = Str::random(40);
            $sendPasswordSetupLink = (bool) ($validatedClientData['send_password_setup_link'] ?? false);
            unset($validatedClientData['send_password_setup_link']);

            $client = $this->createClientAndUser($this->normalizeClientData($validatedClientData), $plainPassword);

            if ($sendPasswordSetupLink) {
                $status = $this->sendPasswordLinkToClient($client);

                return redirect()->route('clients.show', $client)
                    ->with($status === Password::RESET_LINK_SENT ? 'success' : 'warning', $status === Password::RESET_LINK_SENT
                        ? 'Client created and password setup link sent to '.$client->email.'.'
                        : 'Client created, but the password setup email could not be sent. Check mail settings.');
            }

            return redirect()->route('clients.show', $client)
                ->with('success', 'Client and customer portal user created. Send a password setup link from the client profile when ready.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error creating client and user: '.$e->getMessage(), ['exception' => $e]);
            return redirect()->back()->with('error', 'An unexpected error occurred while creating the client.')->withInput();
        }
    }

    public function quickStore(Request $request)
    {
        $this->authorize('create', Client::class);
        $validatedClientData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:clients,email', 'unique:users,email'],
            'phone' => 'nullable|string|max:30',
            'send_password_setup_link' => 'nullable|boolean',
        ]);

        try {
            $plainPassword = Str::random(40);
            $sendPasswordSetupLink = (bool) ($validatedClientData['send_password_setup_link'] ?? false);
            unset($validatedClientData['send_password_setup_link']);

            $client = $this->createClientAndUser($this->normalizeClientData(array_merge($validatedClientData, [
                'client_type' => 'company',
                'billing_email' => $validatedClientData['email'],
                'address' => null,
                'tin_number' => null,
            ])), $plainPassword);

            $message = 'Client added. It has been selected below.';
            if ($sendPasswordSetupLink) {
                $status = $this->sendPasswordLinkToClient($client);
                $message .= $status === Password::RESET_LINK_SENT
                    ? ' Password setup link sent to '.$client->email.'.'
                    : ' Password setup email could not be sent. Check mail settings.';
            }

            return back()
                ->withInput($request->except(['name', 'email', 'phone', 'send_password_setup_link']))
                ->with('quick_client_id', $client->id)
                ->with($sendPasswordSetupLink ? 'success' : 'success', $message);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'quickClient')->withInput();
        } catch (\Exception $e) {
            Log::error('Error quick creating client: '.$e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Could not add client from this page.')->withInput();
        }
    }

    public function sendPasswordSetupLink(Client $client)
    {
        $this->authorize('update', $client);

        if (! $client->user) {
            return back()->with('error', 'This client does not have a linked customer portal user.');
        }

        $status = $this->sendPasswordLinkToClient($client);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $status === Password::RESET_LINK_SENT
            ? 'Password setup/reset link sent to '.$client->user->email.'.'
            : 'Could not send password setup/reset link. Please check mail settings.');
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);
        return view('clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        $this->authorize('update', $client);
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $validatedData = $request->validate($this->clientRules($client));
        $validatedData = $this->normalizeClientData($validatedData);

        if ($client->user && $client->user->email !== $validatedData['email']) {
            $client->user->update(['email' => $validatedData['email']]);
        }

        $client->update($validatedData);

        return redirect()->route('clients.show', $client)->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);

        if ($client->user) {
            $client->user->delete();
        }

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client deleted successfully.');
    }

    private function createClientAndUser(array $validatedClientData, string $plainPassword): Client
    {
        return DB::transaction(function () use ($validatedClientData, $plainPassword) {
            $user = User::create([
                'name' => $validatedClientData['name'],
                'email' => $validatedClientData['email'],
                'password' => Hash::make($plainPassword),
                'role' => UserRoleEnum::Customer,
            ]);

            return Client::create(array_merge($validatedClientData, [
                'employee_id' => Auth::id(),
                'user_id' => $user->id,
            ]));
        });
    }

    private function sendPasswordLinkToClient(Client $client): string
    {
        $email = $client->user?->email ?? $client->email;

        return Password::sendResetLink(['email' => $email]);
    }

    private function clientRules(?Client $client = null): array
    {
        $clientId = $client?->id;
        $userId = $client?->user_id ?? 'NULL';

        return [
            'name' => 'required|string|max:255',
            'client_type' => ['required', 'string', Rule::in(['company', 'individual', 'government', 'non_profit'])],
            'contact_person' => 'nullable|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:clients,email,'.$clientId, 'unique:users,email,'.$userId],
            'billing_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:500',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'tin_number' => 'nullable|string|max:100',
            'id_type' => ['nullable', 'string', Rule::in(['BRN', 'NRIC', 'PASSPORT', 'ARMY', 'OTHER'])],
            'id_number' => 'nullable|string|max:100',
            'sst_registration_number' => 'nullable|string|max:100',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'notes' => 'nullable|string|max:5000',
        ];
    }

    private function normalizeClientData(array $data): array
    {
        $data['client_type'] = $data['client_type'] ?? 'company';
        $data['billing_email'] = $data['billing_email'] ?? $data['email'];
        $data['country_code'] = strtoupper($data['country_code'] ?? 'MY');

        $structuredAddress = collect([
            $data['address_line_1'] ?? null,
            $data['address_line_2'] ?? null,
            trim(collect([$data['postcode'] ?? null, $data['city'] ?? null])->filter()->implode(' ')),
            $data['state'] ?? null,
            $data['country_code'] ?? null,
        ])->filter()->implode("\n");

        if ($structuredAddress !== '') {
            $data['address'] = $structuredAddress;
        }

        return $data;
    }

    private function filteredClients(Request $request)
    {
        $query = Client::query()
            ->when(Auth::user()?->isEmployee(), fn ($builder) => $builder->where('employee_id', Auth::id()));

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('billing_email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('tin_number', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sort = in_array($request->input('sort'), ['name', 'email', 'created_at'], true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }
}
