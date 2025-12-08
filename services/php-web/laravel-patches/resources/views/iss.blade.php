@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="bi bi-globe"></i> ISS Tracker
        </h1>
    </div>
</div>

{{-- Error Handling --}}
@if(isset($error))
<div class="alert error-alert" role="alert">
    <i class="bi bi-exclamation-triangle"></i> {{ $error }}
</div>
@endif

{{-- Current Position --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-geo-alt"></i> Current Position
                </h5>
                <button class="btn btn-sm btn-success" onclick="refreshPosition()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                @if($issPosition)
                    <div id="map"></div>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <small class="text-muted">Latitude</small>
                            <h6 id="lat">{{ number_format($issPosition->latitude, 4) }}°</h6>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Longitude</small>
                            <h6 id="lon">{{ number_format($issPosition->longitude, 4) }}°</h6>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Altitude</small>
                            <h6 id="alt">{{ number_format($issPosition->altitude, 2) }} km</h6>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Velocity</small>
                            <h6 id="vel">{{ number_format($issPosition->velocity, 2) }} km/h</h6>
                        </div>
                    </div>
                @else
                    <p class="text-white">No position data available.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- History Chart --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up"></i> Position History (Last 100 records)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="historyChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- History Table --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-table"></i> Recent Positions
                </h5>
            </div>
            <div class="card-body">
                @if(count($history) > 0)
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th>Altitude (km)</th>
                                    <th>Velocity (km/h)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($history, 0, 20) as $record)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($record->timestamp)->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ number_format($record->latitude, 4) }}°</td>
                                    <td>{{ number_format($record->longitude, 4) }}°</td>
                                    <td>{{ number_format($record->altitude, 2) }}</td>
                                    <td>{{ number_format($record->velocity, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted">No history data available.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let map, marker;

// Инициализация карты
@if($issPosition)
document.addEventListener('DOMContentLoaded', function() {
    map = L.map('map').setView([{{ $issPosition->latitude }}, {{ $issPosition->longitude }}], 3);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    const issIcon = L.icon({
        iconUrl: 'https://upload.wikimedia.org/wikipedia/commons/d/d0/International_Space_Station.svg',
        iconSize: [50, 32],
        iconAnchor: [25, 16]
    });
    
    marker = L.marker([{{ $issPosition->latitude }}, {{ $issPosition->longitude }}], {icon: issIcon}).addTo(map);
    
    // График истории
    renderHistoryChart();
});
@endif

// Обновление позиции
async function refreshPosition() {
    try {
        const response = await fetch('{{ route('iss.api.fetch') }}');
        const data = await response.json();
        
        if (data.success) {
            const pos = data.data;
            document.getElementById('lat').textContent = pos.latitude.toFixed(4) + '°';
            document.getElementById('lon').textContent = pos.longitude.toFixed(4) + '°';
            document.getElementById('alt').textContent = pos.altitude.toFixed(2) + ' km';
            document.getElementById('vel').textContent = pos.velocity.toFixed(2) + ' km/h';
            
            if (marker) {
                marker.setLatLng([pos.latitude, pos.longitude]);
                map.setView([pos.latitude, pos.longitude]);
            }
            
            alert('Position updated successfully!');
            setTimeout(() => location.reload(), 1000);
        } else {
            const errorMsg = (typeof data.error === 'object' && data.error.message) 
                ? data.error.message 
                : (data.error || 'Unknown error');
            alert('Error: ' + errorMsg);
        }
    } catch (error) {
        alert('Failed to fetch position: ' + error.message);
    }
}

// График истории
function renderHistoryChart() {
    const ctx = document.getElementById('historyChart').getContext('2d');
    const history = @json($history);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: history.map(h => new Date(h.timestamp).toLocaleTimeString()),
            datasets: [
                {
                    label: 'Latitude',
                    data: history.map(h => h.latitude),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                },
                {
                    label: 'Longitude',
                    data: history.map(h => h.longitude),
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    });
}
</script>
@endpush
