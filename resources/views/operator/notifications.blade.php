@extends('layouts.app')
@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('subheading', 'Router, payment, voucher, and subscription alerts')
@section('actions')<form method="POST" action="{{ route('notifications.read') }}">@csrf<button class="btn btn-outline-secondary">Mark all read</button></form>@endsection
@section('content')
<div class="card metric-card">
<x-filter-bar :action="route('notifications.index')" :active="$filtered">
    <div class="col-md-5"><input class="form-control form-control-sm" name="search" value="{{ $filters['search'] }}" placeholder="Search alerts"></div>
    <div class="col-md-4"><select class="form-select form-select-sm" name="state"><option value="">Read and unread</option><option value="unread" @selected($filters['state'] === 'unread')>Unread only</option><option value="read" @selected($filters['state'] === 'read')>Read only</option></select></div>
</x-filter-bar>
<div class="list-group list-group-flush">@forelse($notifications as $notification)<div class="list-group-item px-4 py-3 {{ $notification->read_at ? '' : 'bg-success-subtle' }}"><div class="d-flex justify-content-between"><strong>{{ $notification->data['title'] ?? class_basename($notification->type) }}</strong><span class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</span></div><div class="text-secondary mt-1">{{ $notification->data['message'] ?? 'A HotFii event requires your attention.' }}</div></div>@empty<div class="p-5 text-center text-secondary">{{ $filtered ? 'No notifications match these filters.' : 'You have no notifications.' }}</div>@endforelse</div></div><div class="mt-3">{{ $notifications->links() }}</div>
@endsection