{{-- filepath: services/php-web/laravel-patches/resources/views/astro.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4 text-white">
            <i class="bi bi-moon-stars"></i> Astronomy Positions
        </h1>
        <p class="text-white-50" style="font-size: 1.1rem;">Real-time celestial body positions from Astronomy API</p>
    </div>
</div>

{{-- Error Handling --}}
@if(isset($error))
<div class="alert error-alert" role="alert" style="color: #fff; background-color: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem;">
    <i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> {{ $error }}
    <br><small class="mt-2 d-block">Make sure ASTRO_APP_ID and ASTRO_APP_SECRET are configured in .env file</small>
</div>
@endif

@if(isset($events['data']['table']['rows']) && count($events['data']['table']['rows']) > 0)
    {{-- Observer Information --}}
    @if(isset($events['data']['observer']))
        <div class="alert alert-info mb-4" style="background-color: rgba(13, 110, 253, 0.1); border: 1px solid #0d6efd;">
            <i class="bi bi-geo-alt"></i> 
            <strong>Observer Location:</strong>
            Latitude: {{ $events['data']['observer']['location']['latitude'] ?? 'N/A' }}°,
            Longitude: {{ $events['data']['observer']['location']['longitude'] ?? 'N/A' }}°,
            Elevation: {{ $events['data']['observer']['location']['elevation'] ?? 0 }}m
        </div>
    @endif

    {{-- Date Range --}}
    @if(isset($events['data']['dates']))
        <div class="mb-3">
            <small class="text-muted">
                <i class="bi bi-calendar-range"></i>
                Period: {{ $events['data']['dates']['from'] ?? 'N/A' }} to {{ $events['data']['dates']['to'] ?? 'N/A' }}
            </small>
        </div>
    @endif

    {{-- Celestial Bodies Grid --}}
    <div class="row">
        @foreach($events['data']['table']['rows'] as $row)
            @php
                $body = $row['entry'] ?? [];
                $firstCell = $row['cells'][0] ?? null; // Первая позиция для отображения
            @endphp
            
            <div class="col-md-6 col-xl-4 mb-4">
                <div class="card h-100" style="transition: transform 0.2s; border-left: 3px solid #0d6efd;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            @if($body['id'] === 'sun')
                                <i class="bi bi-brightness-high text-warning"></i>
                            @elseif($body['id'] === 'moon')
                                <i class="bi bi-moon text-info"></i>
                            @else
                                <i class="bi bi-globe text-primary"></i>
                            @endif
                            {{ $body['name'] ?? 'Unknown' }}
                        </h5>
                        <span class="badge bg-primary">{{ strtoupper($body['id'] ?? 'N/A') }}</span>
                    </div>
                    
                    <div class="card-body">
                        @if($firstCell)
                            {{-- Current Position Date --}}
                            @if(isset($firstCell['date']))
                                <p class="mb-3">
                                    <i class="bi bi-clock"></i>
                                    <small class="text-muted">Time:</small>
                                    <strong class="ms-1">{{ date('M j, H:i', strtotime($firstCell['date'])) }}</strong>
                                </p>
                            @endif

                            {{-- Distance from Earth --}}
                            @if(isset($firstCell['distance']['fromEarth']))
                                <div class="mb-3 p-2" style="background-color: rgba(0, 123, 255, 0.15); border-radius: 0.25rem; border: 1px solid rgba(0, 123, 255, 0.3);">
                                    <small class="text-white-50">Distance from Earth:</small>
                                    <div class="d-flex justify-content-between mt-1">
                                        <span class="text-white"><strong>{{ $firstCell['distance']['fromEarth']['km'] ?? 'N/A' }}</strong> km</span>
                                        <span class="text-white"><strong>{{ $firstCell['distance']['fromEarth']['au'] ?? 'N/A' }}</strong> AU</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Horizontal Position --}}
                            @if(isset($firstCell['position']['horizontal']))
                                <div class="mb-3">
                                    <div class="text-white mb-1"><i class="bi bi-compass"></i> <strong>Horizontal Position:</strong></div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <small class="text-white-50">Altitude:</small><br>
                                            <strong class="text-white">{{ $firstCell['position']['horizontal']['altitude']['string'] ?? 'N/A' }}</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-white-50">Azimuth:</small><br>
                                            <strong class="text-white">{{ $firstCell['position']['horizontal']['azimuth']['string'] ?? 'N/A' }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Equatorial Position --}}
                            @if(isset($firstCell['position']['equatorial']))
                                <div class="mb-3">
                                    <div class="text-white mb-1"><i class="bi bi-stars"></i> <strong>Equatorial Position:</strong></div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <small class="text-white-50">Right Ascension:</small><br>
                                            <strong class="text-white">{{ $firstCell['position']['equatorial']['rightAscension']['string'] ?? 'N/A' }}</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-white-50">Declination:</small><br>
                                            <strong class="text-white">{{ $firstCell['position']['equatorial']['declination']['string'] ?? 'N/A' }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Constellation --}}
                            @if(isset($firstCell['position']['constellation']))
                                <div class="mb-3 p-2" style="background-color: rgba(255, 193, 7, 0.15); border-radius: 0.25rem; border: 1px solid rgba(255, 193, 7, 0.3);">
                                    <small class="text-white-50">Constellation:</small><br>
                                    <strong class="text-white">{{ $firstCell['position']['constellation']['name'] ?? 'N/A' }}</strong>
                                    <span class="text-white-50">({{ $firstCell['position']['constellation']['short'] ?? 'N/A' }})</span>
                                </div>
                            @endif

                            {{-- Extra Info (Moon Phase, Magnitude) --}}
                            @if(isset($firstCell['extraInfo']))
                                @if(isset($firstCell['extraInfo']['phase']))
                                    <div class="mb-2">
                                        <small class="text-white-50">Moon Phase:</small>
                                        <span class="badge bg-info text-dark ms-1">{{ $firstCell['extraInfo']['phase']['string'] ?? 'N/A' }}</span>
                                    </div>
                                @endif
                                @if(isset($firstCell['extraInfo']['magnitude']))
                                    <div class="mb-2">
                                        <small class="text-white-50">Magnitude:</small>
                                        <strong class="text-white ms-1">{{ number_format($firstCell['extraInfo']['magnitude'], 2) }}</strong>
                                    </div>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@elseif(!isset($error))
    {{-- No events available --}}
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-moon-stars" style="font-size: 4rem; opacity: 0.3;"></i>
            <h4 class="mt-3 text-white">No Positions Available</h4>
            <p class="text-muted">Unable to fetch celestial body positions at this time.</p>
            <small class="text-muted">Check your API credentials or try again later.</small>
        </div>
    </div>
@endif

{{-- API Info Card --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> About Astronomy API</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Real-time celestial body positions calculated using precise astronomical algorithms.
                    Data includes horizontal and equatorial coordinates, distances, constellations, and more.
                </p>
                <p class="mb-0 text-muted">
                    <small>
                        <strong>Astronomy API</strong> provides accurate astronomical data for planets, sun, moon, and other celestial bodies.
                        Position data is cached for 1 hour to improve performance.
                    </small>
                </p>
                <hr class="my-3">
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted">Source:</small>
                        <p class="mb-0"><strong>Astronomy API</strong></p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Cache Duration:</small>
                        <p class="mb-0"><strong>1 hour</strong></p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Update Interval:</small>
                        <p class="mb-0"><strong>Real-time</strong></p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Bodies Tracked:</small>
                        <p class="mb-0"><strong>{{ isset($events['data']['table']['rows']) ? count($events['data']['table']['rows']) : 0 }}</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    }
</style>
@endsection
