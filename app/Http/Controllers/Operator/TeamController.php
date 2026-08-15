<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.team', ['members' => $organization->users()->orderBy('name')->get()]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'in:'.implode(',', array_column(MembershipRole::cases(), 'value'))],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();
        abort_if($organization->users()->whereKey($user->id)->exists(), 422, 'That user is already a team member.');

        $organization->users()->attach($user->id, ['role' => $data['role'], 'joined_at' => now()]);
        return back()->with('success', 'Team member added.');
    }

    public function update(Request $request, Organization $organization, User $member): RedirectResponse
    {
        abort_unless($organization->users()->whereKey($member->id)->exists(), 404);
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', array_column(MembershipRole::cases(), 'value'))],
        ]);

        $organization->users()->updateExistingPivot($member->id, ['role' => $data['role']]);
        return back()->with('success', 'Role updated.');
    }
}