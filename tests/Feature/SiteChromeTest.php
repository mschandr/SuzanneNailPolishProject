<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\InviteRequest;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteChromeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_has_a_skip_link_a_labelled_nav_and_footer_legal_links(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('href="#main"', false)
            ->assertSee('aria-label="Main"', false)
            ->assertSee('aria-label="Footer"', false)
            ->assertSee('href="/privacy"', false)
            ->assertSee('href="/terms"', false);
    }

    public function test_the_privacy_and_terms_pages_are_public(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('<h1>Privacy</h1>', false)->assertSee('public by default');
        $this->get('/terms')->assertOk()->assertSee('<h1>Terms of use</h1>', false);
    }

    public function test_the_landing_page_says_what_the_site_is_and_offers_sign_in(): void
    {
        $this->get('/')->assertOk()->assertSee('<h1>', false)->assertSee('href="/login"', false)->assertSee('No password');
    }

    public function test_page_titles_name_the_page(): void
    {
        $this->get('/privacy')->assertSee('<title>Privacy · Nail Polish Collector</title>', false);
        $this->get('/login')->assertSee('<title>Sign in · Nail Polish Collector</title>', false);
        $this->get('/catalog')->assertSee('<title>Catalog · Nail Polish Collector</title>', false);
    }

    public function test_signed_in_pages_each_have_one_h1_and_a_distinct_title(): void
    {
        $user = User::factory()->create();
        $titles = [];

        foreach (['/home', '/collection', '/locations', '/manicures', '/liked', '/help', '/my-polishes', '/settings/manicures', '/gallery', '/catalog'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), "$url should have exactly one h1");
            preg_match('/<title>(.*?)<\/title>/', $html, $m);
            $titles[$url] = $m[1];
        }

        $this->assertSame($titles, array_unique($titles), 'titles are all different');
    }

    public function test_the_current_page_is_marked_in_the_navigation(): void
    {
        $this->actingAs(User::factory()->create())->get('/collection')
            ->assertSee('href="/collection" aria-current="page"', false);
    }

    public function test_a_missing_page_gets_a_friendly_error_with_a_way_back(): void
    {
        $this->get('/nope/at/all/really')->assertNotFound()
            ->assertSee('Go to the home page')->assertSee('Browse the Catalog');
    }

    public function test_pagination_has_a_named_nav_and_no_forbidden_aria(): void
    {
        $brand = Brand::create(['name' => 'Holo Taco']);
        foreach (range(1, 30) as $i) {
            Polish::create(['brand_id' => $brand->id, 'name' => "Shade $i"]);
        }

        $this->get('/catalog/holo-taco')->assertSee('<nav class="pagination" aria-label="Pages">', false)
            ->assertSee('aria-current="page"', false)->assertDontSee('aria-disabled="true" aria-label', false);
    }

    public function test_the_admin_dashboard_counts_what_needs_attention(): void
    {
        InviteRequest::create(['email' => 'one@example.com']);
        InviteRequest::create(['email' => 'two@example.com']);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
            ->assertSee('invite requests waiting')->assertSee('nothing to review')->assertSee('open reports');
    }

    public function test_the_home_page_has_the_collection_search_and_quick_actions(): void
    {
        $this->actingAs(User::factory()->create())->get('/home')->assertOk()
            ->assertSee('action="/collection"', false)->assertSee('Do I already own this?')->assertSee('New manicure');
    }

    public function test_sign_in_page_explains_there_is_no_password(): void
    {
        $this->get('/login')->assertSee('No password needed');
    }

    public function test_the_check_your_email_page_has_a_heading_and_help(): void
    {
        $this->get('/login/sent')->assertSee('<h1>Check your email</h1>', false)->assertSee('spam');
    }
}
