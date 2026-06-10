<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_switches_locale_and_remembers_it_in_session(): void
    {
        $this->get(route('locale.switch', 'fr'))->assertRedirect();

        $this->assertSame('fr', session('locale'));
    }

    #[Test]
    public function it_rejects_an_unsupported_locale(): void
    {
        $this->get(route('locale.switch', 'xx'))->assertNotFound();
    }

    #[Test]
    public function it_auto_detects_locale_from_accept_language_header(): void
    {
        config()->set('locale.auto_detect', true);

        $this->get('/', ['Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8'])
            ->assertOk();

        $this->assertSame('es', app()->getLocale());
    }

    #[Test]
    public function a_chosen_locale_translates_the_interface(): void
    {
        // French translation of the empty-state title should render. We assert
        // on a stable substring to avoid HTML-entity escaping of apostrophes /
        // em-dashes in the longer subtitle.
        $this->withSession(['locale' => 'fr'])
            ->get('/')
            ->assertOk()
            ->assertSee('Je fonctionne entièrement sur votre ordinateur', false);
    }

    #[Test]
    public function arabic_renders_right_to_left(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }
}
