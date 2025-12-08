{{-- filepath: services/php-web/laravel-patches/resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="bi bi-stars"></i> Space Dashboard
        </h1>
    </div>
</div>

{{-- Error Handling --}}
@if(isset($error))
<div class="alert error-alert" role="alert" style="color: #fff; background-color: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; padding: 1rem; border-radius: 0.5rem;">
    <i class="bi bi-exclamation-triangle"></i> {{ $error }}
</div>
@endif

{{-- ISS Position Card --}}
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-geo-alt"></i> ISS Current Position
                </h5>
                <span class="badge bg-success">Live</span>
            </div>
            <div class="card-body">
                @if($issPosition)
                    <div id="map"></div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <small class="text-muted">Latitude</small>
                            <h6>{{ number_format($issPosition->latitude, 4) }}°</h6>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Longitude</small>
                            <h6>{{ number_format($issPosition->longitude, 4) }}°</h6>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Altitude</small>
                            <h6>{{ number_format($issPosition->altitude, 2) }} km</h6>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Velocity</small>
                            <h6>{{ number_format($issPosition->velocity, 2) }} km/h</h6>
                        </div>
                    </div>
                    <small class="text-muted">
                        Last updated: {{ \Carbon\Carbon::parse($issPosition->timestamp)->format('Y-m-d H:i:s') }} UTC
                    </small>
                @else
                    <p class="text-white">No ISS position data available.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-rocket-takeoff"></i> Quick Stats
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>OSDR Datasets</span>
                    <span class="badge bg-primary">{{ count($osdrDatasets) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>JWST Images</span>
                    <span class="badge bg-primary">{{ count($jwstImages) }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>ISS Orbits/day</span>
                    <span class="badge bg-success">~16</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- OSDR Datasets Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-database"></i> Recent OSDR Datasets
                </h5>
                <a href="{{ route('osdr.index') }}" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                @if(count($osdrDatasets) > 0)
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Release Date</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($osdrDatasets as $dataset)
                                <tr>
                                    <td><code>{{ $dataset->datasetId }}</code></td>
                                    <td>{{ Str::limit($dataset->title, 50) }}</td>
                                    <td>{{ $dataset->releaseDate ? \Carbon\Carbon::parse($dataset->releaseDate)->format('Y-m-d') : 'N/A' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($dataset->updatedAt)->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-white">No OSDR datasets available.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- JWST Images Gallery --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-image"></i> JWST Image Gallery
                </h5>
            </div>
            <div class="card-body">
                @if(count($jwstImages) > 0)
                    <div class="row g-3">
                        @foreach($jwstImages as $image)
                        <div class="col-md-4">
                            <div class="card">
                                <img src="{{ $image->thumbnail }}" class="card-img-top" alt="JWST {{ $image->observation_id }}" loading="lazy">
                                <div class="card-body">
                                    <h6 class="card-title">{{ Str::limit($image->observation_id, 20) }}</h6>
                                    <p class="card-text">
                                        <small class="text-muted">Program: {{ $image->program }}</small>
                                    </p>
                                    <a href="{{ $image->location }}" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-up-right"></i> View Full
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-white">No JWST images available.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Инициализация карты Leaflet (только если есть позиция МКС)
@if($issPosition)
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('map').setView([{{ $issPosition->latitude }}, {{ $issPosition->longitude }}], 3);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    const issIcon = L.icon({
        iconUrl: 'https://upload.wikimedia.org/wikipedia/commons/d/d0/International_Space_Station.svg',
        iconSize: [50, 32],
        iconAnchor: [25, 16]
    });
    
    L.marker([{{ $issPosition->latitude }}, {{ $issPosition->longitude }}], {icon: issIcon})
        .addTo(map)
        .bindPopup('<b>ISS Position</b><br>Lat: {{ number_format($issPosition->latitude, 4) }}<br>Lon: {{ number_format($issPosition->longitude, 4) }}')
        .openPopup();
});
@endif
</script>
@endpush
