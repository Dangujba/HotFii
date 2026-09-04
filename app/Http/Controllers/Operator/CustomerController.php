<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Radius\RadiusCredentialService;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    private const TYPES = ['customer', 'employee', 'student', 'contractor', 'guest'];

    private const STATUSES = ['active', 'suspended', 'expired'];

    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'type' => ListFilters::choice($request, 'type', self::TYPES),
            'status' => ListFilters::choice($request, 'status', self::STATUSES),
        ];

        $customers = $organization->customers()
            ->with('currentCredential.accessPlan')
            ->when($filters['search'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('operator.customers', [
            'customers' => $customers,
            'plans' => $organization->accessPlans()
                ->whereIn('access_type', ['free', 'internal'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function store(Request $request, Organization $organization, RadiusCredentialService $credentials): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:customer,employee,student,contractor,guest'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'access_plan_id' => ['required', 'integer'],
            'username' => ['nullable', 'alpha_dash', 'min:4', 'max:48', 'unique:access_credentials,username'],
            'password' => ['nullable', 'string', 'min:8', 'max:64'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plan = $organization->accessPlans()
            ->whereIn('access_type', ['free', 'internal'])
            ->findOrFail($data['access_plan_id']);

        $customer = $organization->customers()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'email' => isset($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'status' => 'active',
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $password = $data['password'] ?? Str::password(12, true, true, false);
        $credential = $credentials->issue(
            $organization,
            $plan,
            $customer,
            null,
            $data['username'] ?? 'hf-'.Str::lower(Str::random(12)),
            $password,
        );

        return back()
            ->with('success', 'Identity created and synchronized to FreeRADIUS.')
            ->with('issued_credential', ['username' => $credential->username, 'password' => $password]);
    }
}