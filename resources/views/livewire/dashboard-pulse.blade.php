{{-- Five tiles, polled. The charts live outside this component on purpose: a
     Livewire re-render replaces this DOM, which would wipe a chart instance. --}}
<div class="row g-3 mb-4 row-cols-2 row-cols-lg-3 row-cols-xl-5" wire:poll.30s>
    @foreach($tiles as $tile)
        <div class="col">
            <div class="card metric-card hf-stat h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <p class="hf-stat-label mb-1">{{ $tile['label'] }}</p>
                            <div class="hf-stat-value">{{ $tile['value'] }}</div>
                        </div>
                        <span class="hf-stat-icon hf-stat-icon-{{ $tile['tone'] }}"><i class="bi bi-{{ $tile['icon'] }}"></i></span>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                        @if($tile['delta'])
                            {{-- Direction is spelled out by the arrow and the sign, so the
                                 colour is reinforcement rather than the whole message. --}}
                            <span class="hf-stat-delta hf-stat-delta-{{ $tile['delta']['direction'] }}">
                                <i class="bi bi-arrow-{{ $tile['delta']['direction'] }}-short"></i>{{ $tile['delta']['text'] }}
                            </span>
                        @endif
                        <span class="hf-stat-foot">{{ $tile['foot'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
