<?php

namespace Tests\Feature;

use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminValueListTest extends TestCase
{
    use RefreshDatabase;

    public static function lists(): array
    {
        return [
            'finishes' => ['/admin/finishes', Finish::class],
            'color families' => ['/admin/color-families', ColorFamily::class],
        ];
    }

    #[DataProvider('lists')]
    public function test_an_admin_can_add_a_value(string $path, string $model): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($path, ['name' => '  Duochrome '])
            ->assertRedirect($path);

        $this->assertSame(['Duochrome'], $model::pluck('name')->all());
    }

    #[DataProvider('lists')]
    public function test_a_duplicate_ignoring_case_is_refused(string $path, string $model): void
    {
        $model::create(['name' => 'Holographic']);

        $this->actingAs(User::factory()->admin()->create())
            ->post($path, ['name' => 'holographic'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $model::count());
    }

    #[DataProvider('lists')]
    public function test_a_blank_name_is_refused(string $path, string $model): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post($path, ['name' => '   '])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $model::count());
    }

    #[DataProvider('lists')]
    public function test_an_admin_can_rename_a_value(string $path, string $model): void
    {
        $value = $model::create(['name' => 'Glitter']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("{$path}/{$value->id}", ['name' => 'Chunky glitter'])
            ->assertRedirect($path);

        $this->assertSame('Chunky glitter', $value->fresh()->name);
    }

    #[DataProvider('lists')]
    public function test_renaming_to_another_values_name_is_refused(string $path, string $model): void
    {
        $model::create(['name' => 'Matte']);
        $value = $model::create(['name' => 'Jelly']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("{$path}/{$value->id}", ['name' => 'MATTE'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Jelly', $value->fresh()->name);
    }

    #[DataProvider('lists')]
    public function test_a_value_can_be_resaved_with_its_own_name_in_different_case(string $path, string $model): void
    {
        $value = $model::create(['name' => 'Matte']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("{$path}/{$value->id}", ['name' => 'matte'])
            ->assertRedirect($path);

        $this->assertSame('matte', $value->fresh()->name);
    }

    #[DataProvider('lists')]
    public function test_the_list_page_shows_the_values(string $path, string $model): void
    {
        $model::create(['name' => 'Shimmer']);

        $this->actingAs(User::factory()->admin()->create())
            ->get($path)->assertOk()->assertSee('Shimmer');
    }
}
