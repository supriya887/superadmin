{{-- Shared email logo — always has a real HTTPS src; CID only when mailable opts in --}}
@php
    $defaultLogoUrl = 'https://superadmin.tizzygroup.com/superadmin/provider-logo/idn7VlSxIb1DDHeVfcTbekBNJGTVu0DJkufL0ARk.png';
    $fallbackLogoUrl = trim((string) ($logoUrl ?? ''));
    if ($fallbackLogoUrl === '') {
        $fallbackLogoUrl = $defaultLogoUrl;
    }

    $useCid = !empty($embedLogoCid)
        && !empty($logoCid)
        && !empty($logoPath)
        && is_file($logoPath);

    $emailLogoSrc = $useCid ? ('cid:' . $logoCid) : $fallbackLogoUrl;
    $emailLogoAlt = $providerAlt ?? 'Tizzy Mail';
    $emailLogoWidth = $logoWidth ?? '180';
    $emailLogoStyle = $logoStyle ?? 'display:block; max-width:180px; width:180px; height:auto; border:0;';
@endphp
<img src="{{ $emailLogoSrc }}"
     alt="{{ $emailLogoAlt }}"
     width="{{ $emailLogoWidth }}"
     style="{{ $emailLogoStyle }}">
