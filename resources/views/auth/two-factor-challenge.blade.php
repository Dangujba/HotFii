@extends('layouts.auth')

@section('title', 'Two-factor verification')

@section('content')
<h1 class="h3 fw-bold">Verify your sign-in</h1>
<p class="text-secondary mb-4">Enter the six-digit code from your authenticator app or one recovery code.</p>
<form method="POST" action="{{ route('two-factor.verify') }}">
    @csrf
    <div class="mb-4">
        <label class="form-label" for="code">Authenticator or recovery code</label>
        <input
            id="code"
            type="text"
            name="code"
            value="{{ old('code') }}"
            class="form-control form-control-lg @error('code') is-invalid @enderror"
            inputmode="text"
            autocomplete="one-time-code"
            required
            autofocus
        >
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-hotfii btn-lg w-100">Verify and sign in</button>
</form>
<p class="text-center mt-4 mb-0"><a href="{{ route('login') }}">Use another account</a></p>
@endsection
