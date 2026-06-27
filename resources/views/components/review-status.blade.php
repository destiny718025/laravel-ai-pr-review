@props(['status'])

@php
    $value = $status instanceof \App\Enums\ReviewRunStatus ? $status->value : (string) $status;
    $label = str($value)->replace('_', ' ')->title();

    $colors = [
        'pending' => ['border' => '#0F766E', 'background' => '#F0FDF9'],
        'queued' => ['border' => '#A15C00', 'background' => '#FFFBEB'],
        'running' => ['border' => '#A15C00', 'background' => '#FFFBEB'],
        'completed' => ['border' => '#15803D', 'background' => '#F0FDF4'],
        'failed' => ['border' => '#B42318', 'background' => '#FEF3F2'],
        'cancelled' => ['border' => '#A15C00', 'background' => '#FFFBEB'],
    ][$value] ?? ['border' => '#D7DEE2', 'background' => '#FFFFFF'];
@endphp

<span
    style="
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid {{ $colors['border'] }};
        border-radius: 8px;
        background: {{ $colors['background'] }};
        padding: 4px 8px;
        color: #111827;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.4;
    "
>
    <span
        aria-hidden="true"
        style="
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: {{ $colors['border'] }};
        "
    ></span>
    {{ $label }}
</span>
