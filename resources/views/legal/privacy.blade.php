@extends('layouts.app')

@section('title', 'Privacy')

@section('content')
    <div class="prose">
        <h1>Privacy</h1>
        <p class="legal-date">This is a small hobby site run by one person. Here is, in plain words, what it keeps and who can see it.</p>

        <h2>What we keep</h2>
        <ul>
            <li><strong>Your email address.</strong> Used only to send you sign-in links. It is never shown to other people.</li>
            <li><strong>Your display name.</strong> The one name other people see.</li>
            <li><strong>Your collection:</strong> which polishes you own, where you keep them, and your private polishes. Only you can see these.</li>
            <li><strong>Your manicures:</strong> photos, dates, captions and the polishes you list. These are <strong>public by default</strong> (anyone, signed in or not, can see them in the Gallery and on your profile). You can make any manicure, or all of them, private from the manicure page or <a href="/settings/manicures">Privacy setting</a>.</li>
            <li><strong>Hearts and reports</strong> you make. Only the site owner can see who sent a report.</li>
        </ul>

        <h2>Photos</h2>
        <p>Location and camera details (EXIF data) are removed from every photo when you upload it. Private photos are only served to you.</p>

        <h2>Cookies</h2>
        <p>One cookie keeps you signed in, and one protects forms from forgery. There is no advertising, no analytics and no tracking.</p>

        <h2>Who we share with</h2>
        <p>Nobody. We don't sell or share your data. Email is sent through a mail service only to deliver your sign-in links.</p>

        <h2>Deleting your data</h2>
        <p>You can delete your account at any time from <a href="/account/delete">Delete my account</a>. That permanently removes your collection, private polishes, manicures, photos, hearts and reports.</p>

        <h2>Questions</h2>
        <p>Ask the person who invited you to the site.</p>
    </div>
@endsection
