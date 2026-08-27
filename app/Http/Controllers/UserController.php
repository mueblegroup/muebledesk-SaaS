<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $company = $this->currentCompany($request);
        $users = $this->filteredUsers($request)->paginate((int) $request->input('per_page', 10))->withQueryString();
        $roles = $this->tenantRoles();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'planUsage' => $company->planUsage(),
            'seatLimit' => $company->seatLimit(),
            'seatsUsed' => $company->seatsUsed(),
            'seatUsagePercentage' => $company->seatUsagePercentage(),
            'planName' => $company->subscription?->plan?->name,
        ]);
    }

    public function export(Request $request)
    {
        $users = $this->filteredUsers($request)->get();

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Job Title', 'Role', 'Created At']);
            foreach ($users as $user) {
                fputcsv($handle, [$user->name, $user->email, $user->phone, $user->job_title, $user->role?->value ?? $user->getRawOriginal('role'), optional($user->created_at)->format('Y-m-d H:i:s')]);
            }
            fclose($handle);
        }, 'users.csv', ['Content-Type' => 'text/csv']);
    }

    public function bulkDestroy(Request $request)
    {
        $company = $this->currentCompany($request);
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $users = $company->users()->whereIn('users.id', $ids)->get();

        foreach ($users as $user) {
            $this->authorize('delete', $user);
            abort_if($user->id === $request->user()->id, 422, 'You cannot remove your own account.');
            $company->users()->detach($user->id);
        }

        return redirect()->route('users.index')->with('success', $users->count().' team member(s) removed successfully.');
    }

    public function create(Request $request)
    {
        $company = $this->currentCompany($request);

        return view('users.create', [
            'roles' => $this->tenantRoles(),
            'planUsage' => $company->planUsage(),
            'seatLimit' => $company->seatLimit(),
            'seatsUsed' => $company->seatsUsed(),
            'seatUsagePercentage' => $company->seatUsagePercentage(),
            'planName' => $company->subscription?->plan?->name,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger)
    {
        $company = $this->currentCompany($request);
        $validated = $request->validate($this->rules($request));
        $this->ensureRoleAvailable($company, $validated['role']);

        try {
            $user = DB::transaction(function () use ($validated, $company) {
                $lockedCompany = Company::query()
                    ->whereKey($company->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->load('subscription.plan');

                $this->ensureRoleAvailable($lockedCompany, $validated['role']);

                $user = User::create([
                    'current_company_id' => $lockedCompany->id,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'password' => $validated['password'],
                    'role' => UserRoleEnum::from($validated['role']),
                    'job_title' => $validated['job_title'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'email_verified_at' => now(),
                ]);

                $lockedCompany->users()->attach($user->id, [
                    'role' => $validated['role'] === UserRoleEnum::Admin->value ? 'admin' : 'member',
                    'joined_at' => now(),
                ]);

                if ($validated['role'] === UserRoleEnum::Customer->value) {
                    $this->syncCustomerProfile($user, $validated);
                }

                return $user;
            });

            $activityLogger->log('user.created', 'Team member created', $user, [], $user->only(['id', 'name', 'email', 'phone', 'role']));

            return redirect()->route('users.index')->with('success', 'Team member created successfully.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error creating user: '.$e->getMessage(), ['request' => $request->except(['password', 'password_confirmation']), 'exception' => $e]);

            return back()->with('error', 'An error occurred while creating the user.')->withInput();
        }
    }

    public function edit(Request $request, User $user)
    {
        $company = $this->currentCompany($request);
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);
        $user->load('clients');

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->tenantRoles(),
            'planUsage' => $company->planUsage(),
        ]);
    }

    public function update(Request $request, User $user, ActivityLogger $activityLogger)
    {
        $company = $this->currentCompany($request);
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);
        $validated = $request->validate($this->rules($request, $user));
        $old = $user->only(['id', 'name', 'email', 'phone', 'job_title', 'address', 'role']);
        $currentRole = $user->role?->value ?? $user->getRawOriginal('role');

        if ($validated['role'] !== $currentRole) {
            $this->ensureRoleAvailable($company, $validated['role']);
        }

        DB::transaction(function () use ($validated, $user, $company, $currentRole) {
            $lockedCompany = Company::query()
                ->whereKey($company->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->load('subscription.plan');

            if ($validated['role'] !== $currentRole) {
                $this->ensureRoleAvailable($lockedCompany, $validated['role']);
            }

            $user->fill([
                'current_company_id' => $lockedCompany->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'role' => UserRoleEnum::from($validated['role']),
                'job_title' => $validated['job_title'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }
            $user->save();

            $lockedCompany->users()->updateExistingPivot($user->id, [
                'role' => $validated['role'] === UserRoleEnum::Admin->value ? 'admin' : 'member',
            ]);

            if ($validated['role'] === UserRoleEnum::Customer->value) {
                $this->syncCustomerProfile($user, $validated);
            }
        });

        $activityLogger->log('user.updated', 'Team member updated', $user, $old, $user->fresh()->only(['id', 'name', 'email', 'phone', 'job_title', 'address', 'role']));

        return redirect()->route('users.index')->with('success', 'Team member updated successfully.');
    }

    public function destroy(Request $request, User $user, ActivityLogger $activityLogger)
    {
        $company = $this->currentCompany($request);
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);
        abort_if($user->id === $request->user()->id, 422, 'You cannot remove your own account.');

        try {
            return DB::transaction(function () use ($company, $user, $activityLogger) {
                $old = $user->only(['id', 'name', 'email', 'role']);
                $company->users()->detach($user->id);
                $activityLogger->log('user.removed', 'Team member removed from company', $user, $old, []);

                return redirect()->route('users.index')->with('success', 'Team member removed successfully.');
            });
        } catch (\Throwable $e) {
            Log::error('Error removing user: '.$e->getMessage(), ['user_id' => $user->id, 'exception' => $e]);

            return back()->with('error', 'An error occurred while removing the user.');
        }
    }

    private function rules(Request $request, ?User $user = null): array
    {
        $customer = $request->input('role') === UserRoleEnum::Customer->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['required', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::in(collect($this->tenantRoles())->map->value->all())],
            'job_title' => [$customer ? 'nullable' : 'required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'client_type' => [Rule::requiredIf($customer), 'nullable', 'in:individual,company'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'billing_email' => [Rule::requiredIf($customer), 'nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address_line_1' => [Rule::requiredIf($customer), 'nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => [Rule::requiredIf($customer), 'nullable', 'string', 'max:100'],
            'state' => [Rule::requiredIf($customer), 'nullable', 'string', 'max:100'],
            'postcode' => [Rule::requiredIf($customer), 'nullable', 'string', 'max:20'],
            'country_code' => [Rule::requiredIf($customer), 'nullable', 'string', 'size:3'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'id_type' => ['nullable', 'in:NRIC,BRN,PASSPORT,ARMY'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'sst_registration_number' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function syncCustomerProfile(User $user, array $data): void
    {
        Client::updateOrCreate(['user_id' => $user->id], [
            'name' => $data['name'],
            'client_type' => $data['client_type'],
            'contact_person' => $data['contact_person'] ?? $data['name'],
            'email' => $data['email'],
            'billing_email' => $data['billing_email'],
            'phone' => $data['phone'],
            'website' => $data['website'] ?? null,
            'address' => $data['address'],
            'address_line_1' => $data['address_line_1'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'],
            'postcode' => $data['postcode'],
            'country_code' => strtoupper($data['country_code']),
            'tin_number' => $data['tin_number'] ?? null,
            'id_type' => $data['id_type'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'sst_registration_number' => $data['sst_registration_number'] ?? null,
            'payment_terms_days' => $data['payment_terms_days'] ?? 14,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function filteredUsers(Request $request)
    {
        $company = $this->currentCompany($request);
        $query = $company->users()->getQuery()->select('users.*');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('job_title', 'like', "%{$search}%"));
        }
        if ($role = $request->input('role')) {
            $query->where('users.role', $role);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('users.created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('users.created_at', '<=', $to);
        }

        $sort = in_array($request->input('sort'), ['name', 'email', 'role', 'created_at'], true) ? $request->input('sort') : 'name';

        return $query->orderBy('users.'.$sort, $request->input('direction') === 'desc' ? 'desc' : 'asc');
    }

    private function currentCompany(Request $request): Company
    {
        $company = $request->attributes->get('currentCompany') ?: $request->user()->currentCompany;
        abort_unless($company instanceof Company, 404, 'Company workspace not found.');

        return $company->loadMissing('subscription.plan');
    }

    private function ensureRoleAvailable(Company $company, string $role): void
    {
        $subscription = $company->subscription;
        abort_unless($subscription?->isActive(), 402, 'An active platform subscription is required.');

        $limit = $company->roleLimit($role);
        if ($limit === null) {
            return;
        }

        $used = $company->usageForRole($role);
        $label = ucfirst($role);

        abort_if(
            $used >= $limit,
            422,
            "Your {$subscription->plan?->name} plan allows a maximum of {$limit} {$label} account(s). Remove an existing {$role} or upgrade the company plan to add another."
        );
    }

    private function tenantRoles(): array
    {
        return array_values(array_filter(
            UserRoleEnum::cases(),
            fn (UserRoleEnum $role) => $role !== UserRoleEnum::SuperAdmin
        ));
    }
}
