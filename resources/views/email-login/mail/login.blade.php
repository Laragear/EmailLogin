<?php
    /** @var string $url */
    /** @var \Illuminate\Support\Carbon $expiration */
?>

<x-mail::message>
# Login to {{ config('app.name') }}

Hi {{ $user->name }},

You're trying to authenticate to {{ config('app.name') }}. To proceed, click the button below.

<x-mail::button :url="$url" color="success">
    Login to {{ config('app.name') }}
</x-mail::button>

<small>This link will last for {{ $expiration->until('now', 1) }}. If you didn't attempt to log in, you can safely disregard this mail.</small>
</x-mail::message>>
