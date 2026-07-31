<x-mail::message>
# Welcome to {{ config('app.name') }}!

Hello {{ $user->name }},

An account has been created for you at {{ config('app.name') }}.

You can log in using the following credentials:

**Email:** {{ $user->email }}
**Temporary Password:** **{{ $password }}**

<x-mail::button :url="route('login')">
Login to Your Account
</x-mail::button>

**Important Security Notice:**
For your security, we highly recommend changing this temporary password immediately after your first login. You can do this in your profile settings or by using the "Forgot Password" link on the login page.

If you did not request this account or believe this is an error, please ignore this email or contact us immediately.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>