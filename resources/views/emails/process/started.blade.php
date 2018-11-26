@component('mail::message')
# Process is importing

{{ $body  }}

@component('mail::button', ['url' => $button_url])
{{ $button  }}
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
