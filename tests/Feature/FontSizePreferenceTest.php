<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PortalThemeService;
use Tests\Support\EventModuleTestCase;

class FontSizePreferenceTest extends EventModuleTestCase
{
    public function test_user_can_update_font_size_from_profile(): void
    {
        $user = $this->createUser(['email' => 'font-size@example.com']);

        $this->actingAs($user)
            ->put(route('profile.preferences.update'), ['font_size' => User::FONT_SIZE_LARGE])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame(User::FONT_SIZE_LARGE, $user->resolvedFontSize());
    }

    public function test_font_size_is_applied_in_layout(): void
    {
        $user = $this->createUser([
            'email' => 'font-size-layout@example.com',
            'font_size_preference' => User::FONT_SIZE_XLARGE,
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('data-font-size="xlarge"', false);
    }

    public function test_invalid_font_size_is_rejected(): void
    {
        $user = $this->createUser(['email' => 'font-size-invalid@example.com']);

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.preferences.update'), ['font_size' => 'huge'])
            ->assertSessionHasErrors('font_size');
    }
}
