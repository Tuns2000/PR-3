<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #fff;
        }
        .table-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .table {
            color: #fff;
        }
        .table thead th {
            background: rgba(102, 126, 234, 0.3);
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        .table tbody tr:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: scale(1.01);
        }
        .badge {
            font-size: 0.9rem;
            padding: 5px 10px;
        }
        .search-box {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
        }
        .search-box:focus {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            outline: none;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <i class="bi bi-rocket-takeoff"></i> ISS Tracker
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/iss">ISS</a>
                <a class="nav-link" href="/astronomy">Astronomy</a>
                <a class="nav-link" href="/telemetry">Telemetry</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <a href="/telemetry" class="btn btn-outline-light mb-3">
                    <i class="bi bi-arrow-left"></i> Назад к списку
                </a>
                <h1 class="display-5">
                    <i class="bi bi-table"></i> {{ $filename }}
                </h1>
                <p class="lead">
                    <span class="badge bg-success">{{ count($data) }} записей</span>
                </p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control search-box" 
                       placeholder="🔍 Поиск по таблице...">
            </div>
            <div class="col-md-6 text-end">
                <span class="text-white-50">Найдено строк: <span id="rowCount">{{ count($data) }}</span></span>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover" id="dataTable">
                    <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data as $row)
                            <tr>
                                @foreach($row as $key => $value)
                                    <td>
                                        @if($key === 'IsActive')
                                            @if($value === 'TRUE')
                                                <span class="badge bg-success">✓ TRUE</span>
                                            @else
                                                <span class="badge bg-danger">✗ FALSE</span>
                                            @endif
                                        @elseif($key === 'Timestamp')
                                            <i class="bi bi-clock"></i> {{ $value }}
                                        @elseif($key === 'Voltage')
                                            <i class="bi bi-lightning-charge"></i> {{ $value }}V
                                        @elseif($key === 'Temperature')
                                            <i class="bi bi-thermometer-half"></i> {{ $value }}°C
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live search
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#dataTable tbody tr');
            
            let visibleCount = 0;
            rows.forEach(row => {
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
    </script>
</body>
</html>
