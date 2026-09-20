<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'role' => ListFilters::choice($request, 'role', ListFilters::enumValues(MembershipRole::class)),
        ];

        // Columns are qualified because this reads through the membership pivot.
        // The role filter stays outside a when() closure: wherePivot lives on the
        // relation, and when() hands its callback the underlying query builder.
        $members = $organization->users()
            ->when($filters['search'], fn ($query, $term) => $query->where(fn ($inner) => $inner
                ->where('users.name', 'like', "%{$term}%")
                ->orWhere('users.email', 'like', "%{$term}%")));

        if ($filters['role'] !== '') {
            $members->wherePivot('role', $filters['role']);
        }

        return view('operator.team', [
            'members' => $members->orderBy('users.name')->paginate(25)->withQueryString(),
            'roles' => MembershipRole::cases(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $actorRole = (string) $request->user()->roleFor($organization);
        $assignableRoles = $actorRole === 'owner'
            ? array_column(MembershipRole::cases(), 'value')
            : ['technician', 'accountant', 'agent', 'viewer'];
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:'.implode(',', $assignableRoles)],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();
        abort_if($organization->users()->whereKey($user->id)->exists(), 422, 'That user is already a team member.');

        $organization->users()->attach($user->id, ['role' => $data['role'], 'joined_at' => now()]);
        $this->audit($request, $organization, $user, 'team.member.added', [], ['role' => $data['role']]);

        return back()->with('success', 'Team member added.');
    }

    public function update(Request $request, Organization $organization, User $member): RedirectResponse
    {
        abort_unless($organization->users()->whereKey($member->id)->exists(), 404);
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', array_column(MembershipRole::cases(), 'value'))],
        ]);

        $previousRole = (string) $member->roleFor($organization);
        if ($previousRole === 'owner' && $data['role'] !== 'owner') {
            abort_if(
                $organization->users()->wherePivot('role', 'owner')->count() <= 1,
                422,
                'The organization must keep at least one owner.',
            );
        }

        $organization->users()->updateExistingPivot($member->id, ['role' => $data['role']]);
        $this->audit($request, $organization, $member, 'team.member.role-updated', ['role' => $previousRole], ['role' => $data['role']]);

        return back()->with('success', 'Role updated.');
    }

    private function audit(
        Request $request,
        Organization $organization,
        User $member,
        string $action,
        array $before,
        array $after,
    ): void {
        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $member->id,
            'ip_address' => $request->ip(),
            'before' => $before,
            'after' => $after,
        ]);
    }
}
