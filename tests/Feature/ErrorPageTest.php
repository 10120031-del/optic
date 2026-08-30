<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * With APP_DEBUG off — as it is in production and in phpunit.xml — a visitor
 * who hits a bad URL should get the shop's own error page, never a stack
 * trace. These pages are deliberately standalone (no cart lookup, no Vite
 * manifest) so they still render when the thing that broke is the database
 * or a half-finished deploy.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_urls_get_the_branded_404(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $response->assertSee('Lucent Optics');
        $response->assertSee("find that page", false);
        $response->assertDontSee('Whoops');
    }

    public function test_a_customer_hitting_the_admin_area_gets_the_branded_403(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $response->assertForbidden();
        $response->assertSee('This area is for shop staff only.');
    }

    public function test_error_pages_do_not_depend_on_the_built_assets(): void
    {
        // A 500 raised mid-deploy, while public/build is being replaced, must
        // still render — so no @vite anywhere in the error views.
        $this->get('/no-such-page')->assertDontSee('/build/assets/', false);
    }
}
