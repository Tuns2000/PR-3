@extends('layouts.app')

@section('content')
<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .animate-fade-in {
        animation: fadeIn 0.6s ease-out forwards;
    }
    .animate-pulse {
        animation: pulse 2s ease-in-out infinite;
    }
    .animate-slide-in {
        animation: slideIn 0.4s ease-out forwards;
    }
    .card {
        transition: all 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 212, 255, 0.3);
    }
    .stat-card {
        border-left: 3px solid #00d4ff;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        border-left-width: 5px;
        background-color: rgba(0, 212, 255, 0.05);
    }
    .btn {
        transition: all 0.2s ease;
    }
    .btn:hover {
        transform: scale(1.05);
    }
    .table-hover tbody tr {
        transition: all 0.2s ease;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(0, 212, 255, 0.1) !important;
        transform: scale(1.01);
    }
    .cursor-pointer {
        cursor: pointer;
        user-select: none;
    }
    .sortable:hover {
        background-color: rgba(0, 212, 255, 0.1);
    }
    #searchInput {
        background-color: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(0, 212, 255, 0.3);
        color: #fff;
    }
    #searchInput:focus {
        background-color: rgba(255, 255, 255, 0.15);
        border-color: #00d4ff;
        box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
    }
    #searchInput::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }
</style>

<div class="row animate-fade-in">
    <div class="col-12">
        <h1 class="mb-4 text-white">
            <i class="bi bi-globe animate-pulse"></i> ISS Tracker
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
            <div class="card-body" style="position: relative; height: 400px;">
                <canvas id="historyChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- History Table with Filters --}}
<div class="row animate-fade-in" style="animation-delay: 0.2s;">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table"></i> Position History
                    </h5>
                    <div class="d-flex gap-2">
                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="🔍 Search..." style="max-width: 200px;">
                        <button class="btn btn-sm btn-outline-info" onclick="resetFilters()">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                @if(count($history) > 0)
                    <div class="mb-3">
                        <small class="text-white-50">
                            Showing <span id="rowCount">{{ min(count($history), 50) }}</span> of {{ count($history) }} records
                        </small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover" id="historyTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-column="0">
                                        <span class="d-flex align-items-center cursor-pointer" onclick="sortTable(0)">
                                            Timestamp <i class="bi bi-arrow-down-up ms-1"></i>
                                        </span>
                                    </th>
                                    <th class="sortable" data-column="1">
                                        <span class="d-flex align-items-center cursor-pointer" onclick="sortTable(1)">
                                            Latitude <i class="bi bi-arrow-down-up ms-1"></i>
                                        </span>
                                    </th>
                                    <th class="sortable" data-column="2">
                                        <span class="d-flex align-items-center cursor-pointer" onclick="sortTable(2)">
                                            Longitude <i class="bi bi-arrow-down-up ms-1"></i>
                                        </span>
                                    </th>
                                    <th class="sortable" data-column="3">
                                        <span class="d-flex align-items-center cursor-pointer" onclick="sortTable(3)">
                                            Altitude (km) <i class="bi bi-arrow-down-up ms-1"></i>
                                        </span>
                                    </th>
                                    <th class="sortable" data-column="4">
                                        <span class="d-flex align-items-center cursor-pointer" onclick="sortTable(4)">
                                            Velocity (km/h) <i class="bi bi-arrow-down-up ms-1"></i>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                @foreach(array_slice($history, 0, 50) as $record)
                                <tr class="animate-slide-in" style="animation-delay: {{ $loop->index * 0.02 }}s;">
                                    <td data-timestamp="{{ strtotime($record->timestamp) }}">{{ \Carbon\Carbon::parse($record->timestamp)->format('Y-m-d H:i:s') }}</td>
                                    <td data-value="{{ $record->latitude }}">{{ number_format($record->latitude, 4) }}°</td>
                                    <td data-value="{{ $record->longitude }}">{{ number_format($record->longitude, 4) }}°</td>
                                    <td data-value="{{ $record->altitude }}">{{ number_format($record->altitude, 2) }}</td>
                                    <td data-value="{{ $record->velocity }}">{{ number_format($record->velocity, 2) }}</td>
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
        const response = await fetch('{{ route('iss.api.fetch') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json'
            }
        });
        
        // Проверка Content-Type
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Received non-JSON response:', text.substring(0, 200));
            throw new Error('Server returned HTML instead of JSON. Check server logs.');
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        // Используем "ok" вместо "success" (унифицированный формат)
        if (data.ok === true && data.data) {
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
        } else if (data.ok === false && data.error) {
            // Обработка унифицированного формата ошибок
            const errorMsg = data.error.message || data.error.code || 'Unknown error';
            alert('Error: ' + errorMsg);
        } else {
            alert('Unexpected response format');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        alert('Failed to fetch position: ' + error.message);
    }
}

// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const tbody = document.getElementById('tableBody');
    const rows = tbody.getElementsByTagName('tr');
    let visibleCount = 0;
    
    Array.from(rows).forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('rowCount').textContent = visibleCount;
});

// Sort functionality
let sortDirection = {};
function sortTable(columnIndex) {
    const table = document.getElementById('historyTable');
    const tbody = document.getElementById('tableBody');
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    // Toggle sort direction
    sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
    const isAscending = sortDirection[columnIndex] === 'asc';
    
    rows.sort((a, b) => {
        let aValue, bValue;
        
        if (columnIndex === 0) {
            // Timestamp sorting
            aValue = parseInt(a.cells[columnIndex].getAttribute('data-timestamp'));
            bValue = parseInt(b.cells[columnIndex].getAttribute('data-timestamp'));
        } else {
            // Numeric sorting
            aValue = parseFloat(a.cells[columnIndex].getAttribute('data-value'));
            bValue = parseFloat(b.cells[columnIndex].getAttribute('data-value'));
        }
        
        if (isAscending) {
            return aValue - bValue;
        } else {
            return bValue - aValue;
        }
    });
    
    // Reappend sorted rows
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    document.querySelectorAll('.sortable i').forEach(icon => {
        icon.className = 'bi bi-arrow-down-up ms-1';
    });
    const currentHeader = document.querySelector(`.sortable[data-column="${columnIndex}"] i`);
    if (currentHeader) {
        currentHeader.className = isAscending ? 'bi bi-arrow-up ms-1' : 'bi bi-arrow-down ms-1';
    }
}

// Reset filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    const tbody = document.getElementById('tableBody');
    const rows = tbody.getElementsByTagName('tr');
    Array.from(rows).forEach(row => row.style.display = '');
    document.getElementById('rowCount').textContent = rows.length;
    
    // Reset sort indicators
    document.querySelectorAll('.sortable i').forEach(icon => {
        icon.className = 'bi bi-arrow-down-up ms-1';
    });
    sortDirection = {};
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
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: false,
                    pointRadius: 2,
                    pointHoverRadius: 4
                },
                {
                    label: 'Longitude',
                    data: history.map(h => h.longitude),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: false,
                    pointRadius: 2,
                    pointHoverRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            plugins: {
                legend: { 
                    position: 'top',
                    labels: {
                        color: '#fff',
                        font: { size: 12 },
                        usePointStyle: true,
                        padding: 15
                    },
                    onClick: function(e, legendItem, legend) {
                        const index = legendItem.datasetIndex;
                        const chart = legend.chart;
                        const meta = chart.getDatasetMeta(index);

                        meta.hidden = meta.hidden === null ? !chart.data.datasets[index].hidden : null;
                        chart.update();
                    }
                },
                tooltip: {
                    enabled: true,
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(255, 255, 255, 0.3)',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    ticks: { 
                        color: '#fff',
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: { 
                        color: 'rgba(255, 255, 255, 0.1)',
                        drawOnChartArea: true
                    }
                },
                y: {
                    beginAtZero: false,
                    ticks: { 
                        color: '#fff',
                        callback: function(value) {
                            return value.toFixed(2) + '°';
                        }
                    },
                    grid: { 
                        color: 'rgba(255, 255, 255, 0.1)',
                        drawOnChartArea: true
                    }
                }
            },
            animation: {
                duration: 750,
                easing: 'easeInOutQuart',
                onComplete: null,
                onProgress: null
            },
            transitions: {
                show: {
                    animations: {
                        x: { from: 0 },
                        y: { from: 0 }
                    }
                },
                hide: {
                    animations: {
                        x: { to: 0 },
                        y: { to: 0 }
                    }
                }
            },
            hover: {
                animationDuration: 0
            },
            responsiveAnimationDuration: 0
        }
    });
}
</script>
@endpush
