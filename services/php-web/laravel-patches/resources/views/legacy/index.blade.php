
@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="bi bi-file-earmark-spreadsheet"></i> Legacy CSV/XLSX Files
        </h1>
    </div>
</div>

{{-- Error Handling --}}
@if(isset($error))
<div class="alert error-alert" role="alert">
    <i class="bi bi-exclamation-triangle"></i> {{ $error }}
</div>
@endif

{{-- Stats --}}
<div class="row mb-3">
    <div class="col-md-6">
        <p class="text-muted">Total files: <strong>{{ $total }}</strong></p>
    </div>
    <div class="col-md-6 text-end">
        <p class="text-muted">Directory: <code>{{ $csvDir }}</code></p>
    </div>
</div>

{{-- Files Table --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> Files List
                </h5>
            </div>
            <div class="card-body">
                @if(count($files) > 0)
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($files as $file)
                                <tr>
                                    <td><code>{{ $file['name'] }}</code></td>
                                    <td>
                                        @if($file['type'] === 'XLSX')
                                            <span class="badge bg-success">XLSX</span>
                                        @else
                                            <span class="badge bg-info">CSV</span>
                                        @endif
                                    </td>
                                    <td>{{ $file['size'] }}</td>
                                    <td>{{ $file['modified'] }}</td>
                                    <td>
                                        <a href="{{ route('legacy.view', $file['name']) }}" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Пагинация --}}
                    @if($lastPage > 1)
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            @for($i = 1; $i <= $lastPage; $i++)
                            <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
                            </li>
                            @endfor
                        </ul>
                    </nav>
                    @endif
                @else
                    <p class="text-white">No CSV/XLSX files found.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection