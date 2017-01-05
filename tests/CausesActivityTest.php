<?php

namespace Spatie\Activitylog\Test;

use Spatie\Activitylog\Test\Models\User;

class CausesActivityTest extends TestCase
{
    /** @test */
    public function it_can_get_all_activity_for_the_causer()
    {
        $causer = User::first();

        activity()->by($causer)->log('perform activity');
        activity()->by($causer)->log('perform another activity');

        $this->assertCount(2, $causer->activity);
    }

    /** @test */
    public function the_causer_can_be_set_in_the_configuration()
    {
        $causer = User::first();
        config(['laravel-activitylog.caused_by' => $causer]);

        activity()->log('perform activity');

        $this->assertCount(1, $causer->activity);
    }
}
