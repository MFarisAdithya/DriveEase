@php
    $sizeClass = $sizeClass ?? 'h-12 sm:h-14';
    $haloSize = $haloSize ?? 'w-12 h-12 sm:w-14 sm:h-14';
    $wrapperClass = $wrapperClass ?? '';
@endphp

<span class="driveease-logo-stage relative inline-flex items-center {{ $wrapperClass }}">
    <!-- Subtle rotating gradient prism aura behind the DriveEase emblem -->
    <span class="pointer-events-none absolute -left-1 top-1/2 -translate-y-1/2 {{ $haloSize }} rounded-full opacity-55 blur-[7px] animate-driveease-spin-slow"
          style="background: conic-gradient(from 0deg, rgba(54,156,199,0.55), rgba(255,100,84,0.45), rgba(11,40,64,0.25), rgba(54,156,199,0.55));"
          aria-hidden="true"></span>
    <img src="{{ asset('images/driveease-logo.png') }}"
         alt="DriveEase"
         class="relative z-10 {{ $sizeClass }} w-auto object-contain">
</span>
