<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ColorFamily;
use App\Models\Finish;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ValueListController extends Controller
{
    private const LISTS = [
        'finishes' => ['model' => Finish::class, 'title' => 'Finishes'],
        'color-families' => ['model' => ColorFamily::class, 'title' => 'Color families'],
    ];

    public function index(Request $request)
    {
        [$list, $meta] = $this->resolve($request);

        return view('admin.value-list', [
            'title' => $meta['title'],
            'path' => "/admin/{$list}",
            'values' => $meta['model']::orderBy('name_key')->get(),
        ]);
    }

    public function store(Request $request)
    {
        [$list, $meta] = $this->resolve($request);

        $meta['model']::create($this->validated($request, $meta['model']));

        return redirect("/admin/{$list}");
    }

    public function update(Request $request, string $id)
    {
        [$list, $meta] = $this->resolve($request);

        $value = $meta['model']::findOrFail($id);
        $value->update($this->validated($request, $meta['model'], $value->id));

        return redirect("/admin/{$list}");
    }

    private function resolve(Request $request): array
    {
        $list = $request->route()->defaults['list'];

        return [$list, self::LISTS[$list]];
    }

    private function validated(Request $request, string $model, ?string $ignoreId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                function ($attribute, $value, $fail) use ($model, $ignoreId) {
                    $taken = $model::where('name_key', Str::lower($value))
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($taken) {
                        $fail('That name already exists.');
                    }
                },
            ],
        ]);
    }
}
