@props(['title', 'subtitle' => null, 'icon' => null])

<div class="d-flex align-items-start gap-3 mb-3">
    @if ($icon)
        <div class="bg-white border rounded-3 d-inline-flex align-items-center justify-content-center text-primary shadow-sm" style="width: 3rem; height: 3rem;">
            <i class="bi {{ $icon }} fs-5"></i>
        </div>
    @endif

    <div>
        <h1 class="h4 mb-1">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
</div>
