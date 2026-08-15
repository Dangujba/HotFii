<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255'],'address'=>['nullable','string','max:255']]);
        $organization->locations()->create($data);
        return back()->with('success','Location created.');
    }
}