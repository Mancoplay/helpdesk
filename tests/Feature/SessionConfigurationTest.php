<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionConfigurationTest extends TestCase
{
    public function test_sessions_do_not_expire_after_short_inactivity_and_close_with_browser(): void
    {
        $this->assertSame(5256000, (int) config('session.lifetime'));
        $this->assertTrue((bool) config('session.expire_on_close'));
        $this->assertSame(30, (int) config('session.concurrent_window_seconds'));
    }
}
