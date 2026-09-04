@props(['action', 'active' => false, 'title' => null])
{{-- Shared filter row for the list pages. GET, so the active filters live in
     the URL and stay shareable, and submitting drops any ?page= of its own
     accord instead of stranding somebody on page 9 of a new result set. --}}
<div class="card-header">
    @if($title)<h2 class="h5 mb-3">{{ $title }}</h2>@endif
    <form class="row g-2 align-items-center" method="GET" action="{{ $action }}">
        {{ $slot }}
        <div class="col-auto ms-auto d-flex align-items-center gap-1">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            @if($active)<a class="btn btn-sm btn-link text-secondary text-decoration-none" href="{{ $action }}">Clear</a>@endif
        </div>
    </form>
</div>
