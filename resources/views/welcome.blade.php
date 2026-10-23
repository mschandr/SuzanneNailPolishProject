@extends('layouts.app')

@section('title', 'Keep track of your nail polish')

@section('content')
    <section class="hero">
        <h1>Never buy the same polish twice</h1>
        <p>Keep a list of every bottle you own and where it lives. Check a shade in the shop in seconds. Share your manicures and see what other people wore.</p>
        <div class="actions center">
            <a class="btn btn-primary" href="/login">Sign in or create an account</a>
            <a class="btn" href="/gallery">Look at the Gallery</a>
        </div>
        <p><small>No password: we email you a sign-in link. Places are limited.</small></p>
    </section>

    <div class="features">
        <section class="card">
            <h2>Your collection</h2>
            <p>Tick the shades you own in the <a href="/catalog">Catalog</a>, add where you keep them, and search "do I already have this?" from your phone.</p>
        </section>
        <section class="card">
            <h2>Your manicures</h2>
            <p>Photograph your nails, list the polishes you wore, and keep them private or share them in the Gallery.</p>
        </section>
        <section class="card">
            <h2>Your own shades</h2>
            <p>Limited editions and indie polishes not in the Catalog? Add them as private polishes only you can see.</p>
        </section>
    </div>
@endsection
