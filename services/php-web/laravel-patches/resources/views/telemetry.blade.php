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
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .file-icon {
            font-size: 3rem;
            color: #4CAF50;
        }
        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-view:hover {
            opacity: 0.9;
            color: white;
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
                <a class="nav-link active" href="/telemetry">Telemetry</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-4">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Pascal Legacy Telemetry
                </h1>
                <p class="lead">CSV файлы телеметрии, генерируемые Pascal Legacy каждые 5 минут</p>
            </div>
        </div>

        @if($files->isEmpty())
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Нет доступных CSV файлов
            </div>
        @else
            <div class="row">
                @foreach($files as $file)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="file-icon mb-3">
                                    <i class="bi bi-filetype-csv"></i>
                                </div>
                                <h5 class="card-title">{{ $file['name'] }}</h5>
                                <p class="card-text text-white-50">
                                    <small>
                                        <i class="bi bi-hdd"></i> {{ number_format($file['size'] / 1024, 2) }} KB<br>
                                        <i class="bi bi-clock"></i> {{ date('Y-m-d H:i:s', $file['modified']) }}
                                    </small>
                                </p>
                                <a href="/telemetry/{{ $file['name'] }}" class="btn btn-view w-100">
                                    <i class="bi bi-eye"></i> Просмотреть
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
