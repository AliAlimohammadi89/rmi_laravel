@extends('layout')

@section('content')

    <div>
        @if( isset($error) )
            <div class="alert alert-danger">
                {{ $error }}
            </div>
        @endif
    </div>


<form action="{{ url('import') }}" method="post">
    <input type="hidden" name="_method" value="post" />
    {{ csrf_field() }}
    <input type="hidden" name="x" value="1">
    <div class="form-group">
        @if( isset($submit) && $submit && $errors->any() )
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="form-group">
        <label for="store_id">Datalink API:</label>
        <input type="text" required name="api" value="{{ $api }}" class="form-control" placeholder="Your Datalink API">
    </div>
    <div class="form-group">
        <label for="store_id">Bucket ID:</label>
        <input type="text" name="bucket_id" class="form-control" value="{{ old('bucket_id') }}" placeholder="Bucket ID">
    </div>
    <div class="form-group">
        <label for="store_id">key ID:</label>
        <input type="text" name="keyId" class="form-control" value="{{ old('keyId') }}" placeholder="key ID">
    </div>
    <div class="form-group">
        <label for="store_id">Email: (Optional)</label>
        <input type="text" name="email" class="form-control" value="{{ old('email') }}" placeholder="Email">
        <small id="emailHelp" class="form-text text-muted">Send you an email after completed process. (Optional)</small>
    </div>

    <button type="submit" class="btn btn-primary">Submit</button>
</form>
@if( isset($process) && $process )
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script>
        $(function () {
            $.ajax({
                url: "{{ url('run') }}",
                success: function(data) {
                    $('#progress_all').html(data);
                }
            });

            setTimeout(function(){
                window.location = '{!! url("result/?id=$process_id&code={$tracking_code}")  !!}';
            }, 2000);
        });
    </script>

    <div class="clearfix"><br /></div>
    <div class="alert alert-info">
        Your request is processing...
    </div>


@endif




@endsection

