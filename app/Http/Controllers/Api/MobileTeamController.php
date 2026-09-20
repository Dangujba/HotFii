<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MobileTeamController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(MembershipRole::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $role = $request->user()->roleFor($organization);
        $members = $organization->users()
            ->when(trim((string) ($data['search'] ?? '')), fn ($query, string $term) => $query
                ->where(fn ($inner) => $inner->where('users.name', 'like', '%'.$term.'%')
                    ->orWhere('users.email', 'like', '%'.$term.'%')));
        if (filled($data['role'] ?? null)) {
            $members->wherePivot('role', $data['role']);
        }
        $members = $members->orderBy('users.name')->paginate((int) ($data['per_page'] ?? 25));

        return response()->json(['data' => [
            'members' => collect($members->items())->map(fn (User $member) => [
                'id' => $member->uuid,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'joined_at' => $this->joinedAt($member->pivot->joined_at),
                'is_current_user' => $member->is($request->user()),
            ])->values(),
            'pagination' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
            ],
            'roles' => collect(MembershipRole::cases())->map(fn (MembershipRole $item) => [
                'value' => $item->value,
                'label' => ucfirst($item->value),
            ])->values(),
            'permissions' => [
                'can_add' => in_array($role, ['owner', 'manager'], true),
                'can_change_roles' => $role === 'owner',
                'assignable_roles' => $this->assignableRoles((string) $role),
            ],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $actorRole = (string) $request->user()->roleFor($organization);
        abort_unless(in_array($actorRole, ['owner', 'manager'], true), 403, 'You cannot add team members.');
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in($this->assignableRoles($actorRole))],
        ]);
        $user = User::query()->where('email', $data['email'])->firstOrFail();
        abort_if($organization->users()->whereKey($user->id)->exists(), 422, 'That user is already a team member.');
        $joinedAt = now();
        $organization->users()->attach($user->id, ['role' => $data['role'], 'joined_at' => $joinedAt]);
        $this->audit($request, $organization, $user, 'team.member.added', [], ['role' => $data['role']]);

        return response()->json([
            'data' => ['member' => $this->member($user, $data['role'], $joinedAt->toIso8601String(), $request->user())],
            'message' => 'Team member added.',
        ], Response::HTTP_CREATED)->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, Organization $organization, User $member): JsonResponse
    {
        abort_unless($request->user()->roleFor($organization) === 'owner', 403, 'Only an owner can change member roles.');
        $membership = $organization->users()->whereKey($member->id)->first();
        abort_unless($membership, 404);
        $data = $request->validate([
            'role' => ['required', Rule::enum(MembershipRole::class)],
        ]);
        $previousRole = (string) $membership->pivot->role;
        if ($previousRole === 'owner' && $data['role'] !== 'owner') {
            abort_if(
                $organization->users()->wherePivot('role', 'owner')->count() <= 1,
                422,
                'The organization must keep at least one owner.',
            );
        }
        $organization->users()->updateExistingPivot($member->id, ['role' => $data['role']]);
        $this->audit($request, $organization, $member, 'team.member.role-updated', ['role' => $previousRole], ['role' => $data['role']]);

        return response()->json([
            'data' => ['member' => $this->member(
                $member,
                $data['role'],
                $this->joinedAt($membership->pivot->joined_at),
                $request->user(),
            )],
            'message' => 'Role updated.',
        ])->header('Cache-Control', 'no-store, private');
    }

    private function assignableRoles(string $actorRole): array
    {
        if ($actorRole === 'owner') {
            return array_column(MembershipRole::cases(), 'value');
        }

        return ['technician', 'accountant', 'agent', 'viewer'];
    }

    private function member(User $member, string $role, ?string $joinedAt, User $currentUser): array
    {
        return [
            'id' => $member->uuid,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $role,
            'joined_at' => $joinedAt,
            'is_current_user' => $member->is($currentUser),
        ];
    }

    private function joinedAt(mixed $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toIso8601String() : null;
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
