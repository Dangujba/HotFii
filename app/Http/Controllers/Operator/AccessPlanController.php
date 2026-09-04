<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessPlanController extends Controller
{
    private const TYPES = ['paid', 'free', 'internal'];

    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'type' => ListFilters::choice($request, 'type', self::TYPES),
            'state' => ListFilters::choice($request, 'state', ['active', 'inactive']),
        ];

        return view('operator.plans', [
            'plans' => $organization->accessPlans()
                ->when($filters['search'], fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
                ->when($filters['type'], fn ($query, $type) => $query->where('access_type', $type))
                ->when($filters['state'], fn ($query, $state) => $query->where('is_active', $state === 'active'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'types' => self::TYPES,
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255'],'access_type'=>['required','in:paid,free,internal'],'price_naira'=>['required','numeric','min:0'],'duration_minutes'=>['nullable','integer','min:1'],'data_limit_mb'=>['nullable','integer','min:1'],'download_kbps'=>['nullable','integer','min:64'],'upload_kbps'=>['nullable','integer','min:64'],'simultaneous_use'=>['required','integer','min:1','max:20'],'validity_days'=>['nullable','integer','min:1']]);
        $organization->accessPlans()->create(['name'=>$data['name'],'access_type'=>$data['access_type'],'price_kobo'=>(int)round($data['price_naira']*100),'duration_minutes'=>$data['duration_minutes']??null,'data_limit_bytes'=>isset($data['data_limit_mb'])?$data['data_limit_mb']*1024*1024:null,'download_kbps'=>$data['download_kbps']??null,'upload_kbps'=>$data['upload_kbps']??null,'simultaneous_use'=>$data['simultaneous_use'],'validity_days'=>$data['validity_days']??null]);
        return back()->with('success','Access plan created.');
    }
}