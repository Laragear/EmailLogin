@component('mail::message')
# Login to {{ config('app.name') }}

Hi {{ $user->name }},

You're trying to authenticate to {{ config('app.name') }}. To proceed, click the button below.

@component('mail::button', ['url' => $url])
    Login to {{ config('app.name') }}
@endcomponent

<small>This link will last for {{ config('passmail.minutes') }} minutes. If you didn't attempt to log in, you can safely disregard this mail.</small>
@endcomponent
