@php
    $fav = \App\Support\SiteTheme::faviconUrl();
@endphp
@if ($fav !== null && $fav !== '')
    <link rel="icon" href="{{ $fav }}" sizes="any">
@endif
