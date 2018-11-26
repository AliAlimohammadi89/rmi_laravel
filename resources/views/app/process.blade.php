@extends('layout')

@section('content')
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>

<script>
$(function () {
    $.ajax({
        url: "{{ url('run') }}",
        success: function(data) {
            $('#progress_all').html(data);
            window.location = '{{ url("result/?id=$process_id&code={$tracking_code}") }}';
        }
    });
});
</script>


    <div>
        <h4>Your request is processing...</h4>
    </div>

@endsection

