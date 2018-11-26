@extends('layout')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <table class="table">
                <tr>
                    <th>#</th>
                    <th>Bucket ID</th>
                    <th>Process Code</th>
                    <th>Email</th>
                    <th>Count of Products</th>
                    <th>Status</th>
                    <th>Started At</th>
                    <th>Completed At</th>
                </tr>
                @if($processes->count() > 0)
                    @foreach($processes as $process)
                    <tr>
                        <td>{{ ++$loop->index }}</td>
                        <td>{{ $process->bucket_id }}</td>
                        <td>{{ $process->tracking_code }}</td>
                        <td>{{ $process->email }}</td>
                        <td>{{ $process->count_products }}</td>
                        <td>
                            <a href="{!! url("result/?id=$process->id&code=$process->tracking_code") !!}">
                            @if( $process->status == 1 )
                                <span class="badge badge-info">Pending</span>
                            @elseif($process->status == 2)
                                <span class="badge badge-success">Running</span>
                            @elseif($process->status == 3)
                                <span class="badge badge-primary">Complete</span>
                            @endif
                            </a>
                        </td>
                        <td>{{ $process->created_at }}</td>
                        <td>{{ $process->updated_at }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="8">Don't have any request</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>
</div>

<style>
    main{ width: 100%}
</style>


@endsection