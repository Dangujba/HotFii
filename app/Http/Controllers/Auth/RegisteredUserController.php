<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\MembershipRole;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View { return view('auth.register'); }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255'],'email'=>['required','email','max:255','unique:users'],'password'=>['required','confirmed',Password::defaults()],'organization_name'=>['required','string','max:255'],'mode'=>['required','in:commerce,internal,hybrid']]);
        [$user,$organization]=DB::transaction(function() use($data){
            $user=User::create(['name'=>$data['name'],'email'=>Str::lower($data['email']),'password'=>$data['password']]);
            $mode=OrganizationMode::from($data['mode']);
            $organization=Organization::create(['name'=>$data['organization_name'],'slug'=>Str::slug($data['organization_name']).'-'.Str::lower(Str::random(5)),'mode'=>$mode,'status'=>OrganizationStatus::Live,'billing_plan'=>$mode===OrganizationMode::Commerce?BillingPlan::Sandbox:BillingPlan::Organization20]);
            $organization->users()->attach($user->id,['role'=>MembershipRole::Owner->value,'joined_at'=>now()]);
            return [$user,$organization];
        });
        event(new Registered($user)); Auth::login($user); $request->session()->put('current_organization_id',$organization->id);
        return redirect()->route('dashboard')->with('success','Your organization is live. Add a location and router, then activate your payment profile when you are ready to collect money.');
    }
}