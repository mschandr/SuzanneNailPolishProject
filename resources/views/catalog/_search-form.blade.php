<form method="get" action="/catalog" role="search" class="form-row mb-md">
    <div class="field grow-field">
        <label for="catalog-q" class="sr-only">Search the Catalog</label>
        <input id="catalog-q" type="search" name="q" value="{{ $q ?? '' }}" maxlength="100" placeholder="Search every polish by shade or brand" class="full">
    </div>
    <button class="btn-primary">Search</button>
</form>
