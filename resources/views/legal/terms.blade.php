@extends('layouts.app')

@section('title', 'Terms')

@section('content')
    <div class="prose">
        <h1>Terms of use</h1>
        <p class="legal-date">A few friendly ground rules for a small shared site.</p>

        <h2>Your photos</h2>
        <ul>
            <li>Only upload photos you took yourself, or have permission to share.</li>
            <li>Your photos stay yours. By making a manicure public you let this site show it to visitors.</li>
            <li>Please don't upload anything illegal, hateful, or showing people who haven't agreed to it.</li>
        </ul>

        <h2>Moderation</h2>
        <p>Anyone signed in can report a public manicure. The site owner may hide content, or suspend an account, if it breaks these rules.</p>

        <h2>The catalog</h2>
        <p>Polish names, brands and images are listed for reference. Brands and their products belong to their owners, and nothing here is endorsed by them.</p>

        <h2>No guarantees</h2>
        <p>This is a hobby project provided as is. It may change, be offline now and then, or close. Keep your own list if your collection is precious.</p>

        <h2>Leaving</h2>
        <p>You can <a href="/account/delete">delete your account</a> whenever you like.</p>
    </div>
@endsection
