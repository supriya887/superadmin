@php
    $iconFile = ltrim((string) ($icon ?? 'question'), '/');
    if (!str_ends_with($iconFile, '.svg')) {
        $iconFile .= '.svg';
    }
    $iconWidth = (int) ($width ?? 24);
    $iconAlt = (string) ($alt ?? '');
    $iconStyle = (string) ($style ?? 'vertical-align:middle;margin-right:8px;border:0;display:inline-block;');
    $appUrl = rtrim((string) config('app.url'), '/');
    if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
        $appUrl = 'https://superadmin.tizzygroup.com';
    }
    $iconSrc = $appUrl . '/emails/icons/' . $iconFile;
@endphp
<img src="{{ $iconSrc }}" width="{{ $iconWidth }}" alt="{{ $iconAlt }}" style="{{ $iconStyle }}">
