@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="bi bi-database"></i> NASA OSDR Datasets
        </h1>
    </div>
</div>

{{-- Error Handling --}}
@if(isset($error))
<div class="alert error-alert" role="alert">
    <i class="bi bi-exclamation-triangle"></i> {{ $error }}
</div>
@endif

{{-- Sync Button --}}
<div class="row mb-4">
    <div class="col-12">
        <button class="btn btn-primary" onclick="syncDatasets()">
            <i class="bi bi-arrow-repeat"></i> Sync with NASA OSDR
        </button>
        <span id="syncStatus" class="ms-3 text-muted"></span>
    </div>
</div>

{{-- Datasets Table --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> Available Datasets ({{ count($datasets) }})
                </h5>
            </div>
            <div class="card-body">
                @if(count($datasets) > 0)
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Dataset ID</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Release Date</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($datasets as $dataset)
                                <tr>
                                    <td><code>{{ $dataset->datasetId }}</code></td>
                                    <td>{{ Str::limit($dataset->title, 40) }}</td>
                                    <td>{{ $dataset->description ? Str::limit($dataset->description, 60) : 'N/A' }}</td>
                                    <td>{{ $dataset->releaseDate ? \Carbon\Carbon::parse($dataset->releaseDate)->format('Y-m-d') : 'N/A' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($dataset->updatedAt)->diffForHumans() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-white">No datasets available. Click "Sync with NASA OSDR" to fetch data.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function syncDatasets() {
    const statusEl = document.getElementById('syncStatus');
    statusEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    
    try {
        const response = await fetch('{{ route('osdr.api.sync') }}');
        const data = await response.json();
        
        if (data.ok) {
            statusEl.innerHTML = '<span class="text-success">✓ Synced successfully!</span>';
            setTimeout(() => location.reload(), 1500);
        } else {
            statusEl.innerHTML = '<span class="text-danger">✗ Error: ' + data.error.message + '</span>';
        }
    } catch (error) {
        statusEl.innerHTML = '<span class="text-danger">✗ Failed: ' + error.message + '</span>';
    }
}
</script>
@endpush
