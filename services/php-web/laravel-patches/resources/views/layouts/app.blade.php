<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Cassiopeia Space Dashboard' }}</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%230a0e27'/%3E%3Ccircle cx='50' cy='50' r='35' fill='none' stroke='%2300d4ff' stroke-width='2'/%3E%3Cpath d='M30 50 L50 30 L70 50 L50 70 Z' fill='%2300d4ff' opacity='0.6'/%3E%3Ccircle cx='50' cy='50' r='5' fill='%23fff'/%3E%3C/svg%3E">
    
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Leaflet для карт -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            color: #e0e0e0;
            min-height: 100vh;
        }
        .navbar {
            background: rgba(15, 20, 40, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        .card {
            background: rgba(30, 35, 60, 0.8);
            border: 1px solid rgba(100, 150, 255, 0.2);
            color: #e0e0e0;
        }
        .card-header {
            background: rgba(50, 60, 100, 0.6);
            border-bottom: 1px solid rgba(100, 150, 255, 0.3);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4169E1, #1E90FF);
            border: none;
        }
        .badge {
            padding: 0.5em 0.8em;
        }
        #map {
            height: 400px;
            border-radius: 8px;
        }
        .error-alert {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="bi bi-stars"></i> Cassiopeia
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('iss.*') ? 'active' : '' }}" href="{{ route('iss.index') }}">
                            <i class="bi bi-globe"></i> ISS Tracker
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('osdr.*') ? 'active' : '' }}" href="{{ route('osdr.index') }}">
                            <i class="bi bi-database"></i> OSDR Datasets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('astro.*') ? 'active' : '' }}" href="{{ route('astro.index') }}">
                            <i class="bi bi-moon-stars"></i> Astronomy
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="text-center py-4 mt-5">
        <p class="text-muted">
            <small>© {{ date('Y') }} Cassiopeia Space Dashboard | Powered by NASA Open APIs</small>
        </p>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    @stack('scripts')
</body>
</html>
