@extends('layouts.platform')
@section('title', 'Users')
@section('heading', 'Users')
@section('subheading', 'Everyone with an account on the deployment')
@section('content')

<div class="row g-3 mb-4">
    @foreach([
        ['Accounts', number_format($stats['total']), null],
        ['Verified', number_format($stats['verified']), route('platform.users.index', ['verified' => 'yes'])],
        ['Platform admins', number_format($stats['admins']), route('platform.users.index', ['admins' => 'yes'])],
        ['In no organization', number_format($stats['orphaned']), null],
    ] as [$label, $value, $href])
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100"><div class="card-body">
                <div class="text-secondary small">{{ $label }}</div>
                <div class="fs-5 fw-bold">@if($href)<a class="text-decoration-none text-reset" href="{{ $href }}">{{ $value }}</a>@else{{ $value }}@endif</div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card metric-card">
    <x-filter-bar :action="route('platform.users.index')" :active="$filtered">
        <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Name, email or phone"></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="verified">
            <option value="">Any email state</option>
            <option value="yes" @selected($filters['verified'] === 'yes')>Verified</option>
            <option value="no" @selected($filters['verified'] === 'no')>Unverified</option>
        </select></div>
        <div class="col-md-3"><select class="form-select form-select-sm" name="admins">
            <option value="">Everyone</option>
            <option value="yes" @selected($filters['admins'] === 'yes')>Platform admins only</option>
        </select></div>
    </x-filter-bar>
    <div class="card-body p-0"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th>User</th><th>Phone</th><th>Email</th><th>Organizations</th><th>Registered</th></tr></thead>
        <tbody>@forelse($users as $user)
            <tr>
                <td>
                    <strong>{{ $user->name }}</strong>
                    @if($user->is_platform_admin)<span class="badge text-bg-danger ms-1">Platform admin</span>@endif
                    <div class="small text-secondary">{{ $user->email }}</div>
                </td>
                <td class="small">{{ $user->phone ?: '—' }}</td>
                <td class="small">
                    @if($user->hasVerifiedEmail())
                        <span class="status-dot bg-success"></span>Verified {{ $user->email_verified_at->format('j M Y') }}
                    @else
                        <span class="status-dot bg-warning"></span><span class="text-warning">Unverified</span>
                    @endif
                </td>
                <td class="small">
                    @forelse($user->organizations as $organization)
                        <a class="text-decoration-none" href="{{ route('platform.organizations.show', $organization) }}">{{ $organization->name }}</a><span class="text-secondary">{{ ' (' . ucfirst($organization->pivot->role) . ')' }}</span>@if(! $loop->last), @endif
                    @empty
                        <span class="text-secondary">{{ $user->is_platform_admin ? 'Support account' : 'None' }}</span>
                    @endforelse
                </td>
                <td class="small text-secondary">{{ $user->created_at->format('j M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-5 text-secondary">{{ $filtered ? 'No users match these filters.' : 'No users have registered yet.' }}</td></tr>
        @endforelse</tbody>
    </table></div></div>
    <div class="card-footer small text-secondary">
        <i class="bi bi-lock me-1"></i>Read-only. A platform admin can impersonate every tenant, so the flag is granted only from a shell with
        <code>php artisan hotfii:create-admin</code> — there is no grant button, password reset or force-verify here on purpose.
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
