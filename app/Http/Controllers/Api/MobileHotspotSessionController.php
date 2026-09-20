<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Services\Network\SessionDisconnectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MobileHotspotSessionController extends Controller
{
    private const STATUSES = ['pending', 'active', 'disconnect_pending', 'stopped', 'expired'];

    private const VIEWS = ['live', 'recent', 'all'];

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'router' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $router = isset($data['router'])
            ? $organization->networkDevices()->where('uuid', $data['router'])->firstOrFail()
            : null;
        $view = $data['view'] ?? 'live';
        $sessions = $organization->sessions()
            ->with('networkDevice', 'customer', 'accessPlan')
            ->when(trim((string) ($data['search'] ?? '')), fn (Builder $query, string $term) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('radius_username', 'like', "%{$term}%")
                    ->orWhere('mac_address', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhere('client_name', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"))))
            ->when($data['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($view === 'live', fn (Builder $query) => $query
                ->whereIn('status', ['active', 'disconnect_pending']))
            ->when($view === 'recent', fn (Builder $query) => $query
                ->whereIn('status', ['stopped', 'expired']))
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id))
            ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('started_at', '>=', $from))
            ->when($data['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('started_at', '<=', $to))
            ->latest('started_at')
            ->paginate((int) ($data['per_page'] ?? 30));
        $canDisconnect = $this->canDisconnect($request, $organization);
        $summary = $organization->sessions();

        return response()->json(['data' => [
            'summary' => [
                'live' => (clone $summary)->whereIn('status', ['active', 'disconnect_pending'])->count(),
                'recent' => (clone $summary)->whereIn('status', ['stopped', 'expired'])->count(),
                'total_usage_bytes' => (int) ((clone $summary)->sum('input_bytes') + (clone $summary)->sum('output_bytes')),
            ],
            'sessions' => collect($sessions->items())
                ->map(fn (HotspotSession $session) => $this->session($session, $canDisconnect))
                ->values(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
            'options' => [
                'statuses' => self::STATUSES,
                'routers' => $organization->networkDevices()->orderBy('name')->get()->map(fn (NetworkDevice $device) => [
                    'id' => $device->uuid,
                    'name' => $device->name,
                ])->values(),
            ],
            'permissions' => ['can_disconnect' => $canDisconnect],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function disconnect(
        Request $request,
        Organization $organization,
        HotspotSession $session,
        SessionDisconnectService $disconnects,
    ): JsonResponse {
        abort_unless($session->organization_id === $organization->id, 404);
        abort_unless($this->canDisconnect($request, $organization), 403, 'Your role cannot disconnect sessions.');

        try {
            $session = $disconnects->disconnect($session);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['session' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => $this->session($session->load('networkDevice', 'customer', 'accessPlan'), true),
            'message' => 'The router confirmed that the session was disconnected.',
        ])->header('Cache-Control', 'no-store, private');
    }

    private function session(HotspotSession $session, bool $canDisconnect): array
    {
        return [
            'id' => $session->uuid,
            'status' => $session->status,
            'username' => $session->radius_username,
            'customer_name' => $session->customer?->name,
            'customer_phone' => $session->customer?->phone,
            'plan_name' => $session->accessPlan?->name,
            'router_id' => $session->networkDevice?->uuid,
            'router_name' => $session->networkDevice?->name,
            'client_name' => $session->client_name,
            'mac_address' => $session->mac_address,
            'ip_address' => $session->ip_address,
            'input_bytes' => (int) $session->input_bytes,
            'output_bytes' => (int) $session->output_bytes,
            'total_bytes' => $session->totalBytes(),
            'started_at' => $session->started_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
            'terminate_cause' => $session->terminate_cause,
            'can_disconnect' => $canDisconnect && in_array($session->status, ['active', 'disconnect_pending'], true),
        ];
    }

    private function canDisconnect(Request $request, Organization $organization): bool
    {
        return $request->user()->is_platform_admin
            || in_array($request->user()->roleFor($organization), ['owner', 'manager', 'technician'], true);
    }
}
