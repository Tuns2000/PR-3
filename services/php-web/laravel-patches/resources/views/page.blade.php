{{-- filepath: services/php-web/laravel-patches/resources/views/cms/page.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">{{ $title }}</h1>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                @if($slug === 'about')
                    <h3>About Cassiopeia Space Dashboard</h3>
                    <p>
                        The Cassiopeia Space Dashboard is a comprehensive platform for tracking and visualizing space data from various NASA APIs.
                    </p>
                    <ul>
                        <li>Real-time ISS position tracking</li>
                        <li>NASA OSDR (Open Science Data Repository) datasets</li>
                        <li>JWST (James Webb Space Telescope) imagery</li>
                        <li>Astronomy events and data</li>
                    </ul>
                @elseif($slug === 'contact')
                    <h3>Contact Us</h3>
                    <p>For inquiries, please reach out to:</p>
                    <p>Email: <a href="mailto:support@cassiopeia.space">support@cassiopeia.space</a></p>
                @elseif($slug === 'privacy')
                    <h3>Privacy Policy</h3>
                    <p>
                        We respect your privacy. This dashboard uses only publicly available NASA data.
                        No personal information is collected or stored.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
