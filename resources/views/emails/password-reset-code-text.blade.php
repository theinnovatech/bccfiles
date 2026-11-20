{{-- Plain-text email: output as-is (no HTML escaping, so the link keeps a plain "&"). --}}
OBIMS - {!! $organizationName !!}

Hello {!! $user->name !!},

We received a request to reset the password of your OBIMS account ({!! $user->email !!}).

Your code: {!! $code !!}

Or open this link to choose a new password:
{!! $link !!}

The code and the link expire in {!! $minutes !!} minutes and work only once.
If you did not ask to reset your password, ignore this email. Your password stays the same.
