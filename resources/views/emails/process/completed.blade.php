@component('mail::message')
# Process Import Completed

{{ $body  }}.

<div>
    <ul>
        <li>Imported Successfully: {{ $success }}</li>
        <li>Imported Failure: {{ $fail }}</li>
        <li>Total Products: {{ $total }}</li>
    </ul>
</div>


Thanks,<br>
{{ config('app.name') }}
@endcomponent
