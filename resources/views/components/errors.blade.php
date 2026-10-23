@props(['except' => []])
@php($messages = collect($errors->getMessages())->except($except)->flatten())
@if ($messages->isNotEmpty())
    <div class="error-summary" role="alert">
        <strong>Please fix the following:</strong>
        <ul>@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
