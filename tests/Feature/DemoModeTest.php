<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    public function test_demo_mode_shows_the_code_and_blocks_uploads(): void
    {
        config(['family.demo_mode' => true]);
        $user = $this->makeUser();
        $this->get('/login')->assertSee('0700 000 001');
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $code = json_decode(\DB::table('dev_outbox')->latest('id')->value('payload'), true)['button']['personalisation']['copy_otp_code']['parameters'][0];
        $this->get('/verify')->assertSee($code);

        $this->actingAs($user)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg')])->assertSessionHas('error');
        $this->assertNull($user->member->fresh()->photo_path);
    }

    public function test_outside_demo_mode_the_code_is_never_shown(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $code = json_decode(\DB::table('dev_outbox')->latest('id')->value('payload'), true)['button']['personalisation']['copy_otp_code']['parameters'][0];
        $this->get('/verify')->assertDontSee($code);
        $this->get('/login')->assertDontSee('0700 000 001');
    }
}
