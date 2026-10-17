<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangeBottleTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Bottle $bottle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create();
        $this->bottle = $this->makeBottle($this->me);
    }

    private function makeBottle(User $user): Bottle
    {
        $polish = Polish::firstOrCreate(
            ['name_key' => 'ember rising'],
            ['brand_id' => Brand::firstOrCreate(['name_key' => 'phoenix parlour'], ['name' => 'Phoenix Parlour'])->id, 'name' => 'Ember Rising'],
        );
        $bottle = new Bottle(['polish_id' => $polish->id]);
        $bottle->user_id = $user->id;
        $bottle->save();

        return $bottle;
    }

    public function test_a_bottle_can_be_moved_to_another_location(): void
    {
        $location = $this->me->locations()->create(['name' => 'Dining room']);

        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['location_id' => $location->id])
            ->assertRedirect();

        $this->assertSame($location->id, $this->bottle->fresh()->location_id);
    }

    public function test_clearing_the_location_leaves_the_bottle_unplaced(): void
    {
        $location = $this->me->locations()->create(['name' => 'Dining room']);
        $this->bottle->forceFill(['location_id' => $location->id])->save();

        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['location_id' => ''])->assertRedirect();

        $this->assertNull($this->bottle->fresh()->location_id);
    }

    public function test_a_bottle_cannot_be_moved_to_someone_elses_location(): void
    {
        $theirs = User::factory()->create()->locations()->create(['name' => 'Theirs']);

        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['location_id' => $theirs->id])
            ->assertSessionHasErrors('location_id');

        $this->assertNull($this->bottle->fresh()->location_id);
    }

    #[DataProvider('statuses')]
    public function test_the_status_can_be_changed_to_any_valid_value(string $status): void
    {
        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['status' => $status])->assertRedirect();

        $this->assertSame($status, $this->bottle->fresh()->status);
    }

    public static function statuses(): array
    {
        return array_map(fn ($s) => [$s], ['owned', 'used_up', 'given_away', 'discarded']);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['status' => 'borrowed'])
            ->assertSessionHasErrors('status');

        $this->assertSame('owned', $this->bottle->fresh()->status);
    }

    public function test_changing_one_field_does_not_reset_the_other(): void
    {
        $location = $this->me->locations()->create(['name' => 'Dining room']);
        $this->bottle->forceFill(['location_id' => $location->id])->save();

        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['status' => 'used_up']);

        $this->assertSame($location->id, $this->bottle->fresh()->location_id);
    }

    public function test_a_bottle_added_by_mistake_can_be_deleted_permanently(): void
    {
        $this->actingAs($this->me)->delete("/bottles/{$this->bottle->id}")->assertRedirect();

        $this->assertNull(Bottle::find($this->bottle->id));
    }

    public function test_someone_elses_bottle_is_404_to_change_or_delete(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->put("/bottles/{$this->bottle->id}", ['status' => 'discarded'])->assertNotFound();
        $this->actingAs($stranger)->delete("/bottles/{$this->bottle->id}")->assertNotFound();

        $this->assertSame('owned', $this->bottle->fresh()->status);
    }

    public function test_the_user_id_cannot_be_reassigned_through_the_request(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['user_id' => $stranger->id, 'status' => 'used_up']);

        $this->assertSame($this->me->id, $this->bottle->fresh()->user_id);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->put("/bottles/{$this->bottle->id}", ['status' => 'used_up'])->assertRedirect('/login');
        $this->delete("/bottles/{$this->bottle->id}")->assertRedirect('/login');
    }

    public function test_saving_a_bottle_says_so_and_mentions_history_when_it_leaves_the_owned_list(): void
    {
        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['status' => 'used_up'])
            ->assertSessionHas('status', 'Saved. This bottle is now in your history.');

        $this->actingAs($this->me)->put("/bottles/{$this->bottle->id}", ['status' => 'owned'])
            ->assertSessionHas('status', 'Saved.');
    }
}
