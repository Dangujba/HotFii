<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class MobileNotificationController extends Controller
{
    private const CATEGORIES = ['router', 'payment', 'invoice', 'account'];

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $needle = '%"organization_id":"'.$organization->uuid.'"%';
        $notifications = $request->user()->notifications()
            ->where('data', 'like', $needle)
            ->when($data['category'] ?? null, fn ($query, string $category) => $query
                ->where('data', 'like', '%"category":"'.$category.'"%'))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20));
        $preference = $this->preference($request, $organization);

        return response()->json(['data' => [
            'notifications' => collect($notifications->items())->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? class_basename($notification->type),
                'message' => $notification->data['message'] ?? 'A HotFii event requires your attention.',
                'category' => $notification->data['category'] ?? 'account',
                'url' => $notification->data['url'] ?? null,
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values(),
            'unread_count' => $request->user()->unreadNotifications()->where('data', 'like', $needle)->count(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'preferences' => $this->preferences($preference),
            'options' => ['categories' => self::CATEGORIES],
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function updatePreferences(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'push_enabled' => ['required', 'boolean'],
            'router_alerts' => ['required', 'boolean'],
            'payment_alerts' => ['required', 'boolean'],
            'invoice_alerts' => ['required', 'boolean'],
            'account_alerts' => ['required', 'boolean'],
        ]);
        $preference = $this->preference($request, $organization);
        $preference->update($data);

        return response()->json(['data' => [
            'preferences' => $this->preferences($preference->refresh()),
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function read(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate(['notification_id' => ['nullable', 'uuid']]);
        $query = $request->user()->unreadNotifications()
            ->where('data', 'like', '%"organization_id":"'.$organization->uuid.'"%');

        if ($id = $data['notification_id'] ?? null) {
            $notification = (clone $query)->whereKey($id)->firstOrFail();
            $notification->markAsRead();
        } else {
            $query->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['data' => ['message' => 'Notifications marked as read.']]);
    }

    private function preference(Request $request, Organization $organization): NotificationPreference
    {
        return NotificationPreference::firstOrCreate([
            'user_id' => $request->user()->id,
            'organization_id' => $organization->id,
        ]);
    }

    private function preferences(NotificationPreference $preference): array
    {
        return [
            'push_enabled' => $preference->push_enabled,
            'router_alerts' => $preference->router_alerts,
            'payment_alerts' => $preference->payment_alerts,
            'invoice_alerts' => $preference->invoice_alerts,
            'account_alerts' => $preference->account_alerts,
        ];
    }
}
