

@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="bi bi-file-earmark-text"></i> {{ $filename }}
        </h1>
        <a href="{{ route('legacy.index') }}" class="btn btn-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <span class="badge bg-info">{{ $type }}</span> File Content
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover">
                        <thead>
                            <tr>
                                @foreach($headers as $header)
                                <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @if(!empty(array_filter($row)))
                                <tr>
                                    @foreach($row as $cell)
                                    <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection