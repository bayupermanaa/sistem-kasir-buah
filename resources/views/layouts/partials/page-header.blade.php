@props(['title', 'subtitle' => null, 'icon' => null])

<div class="ui-surface ui-page-header ui-surface-soft">
    <div class="d-flex align-items-start gap-3">
        @if ($icon)
            <div class="ui-page-header-icon">
                <i class="bi {{ $icon }} fs-5"></i>
            </div>
        @endif

        <div class="flex-grow-1">
            <div class="ui-page-header-title">{{ $title }}</div>
            @if ($subtitle)
                <p class="ui-page-header-subtitle mt-1">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
</div>
