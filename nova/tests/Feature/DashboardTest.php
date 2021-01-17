<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Http\Request;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\IntegrationTest;

class DashboardTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testAuthorizationCallbackIsExecuted()
    {
        Nova::dashboards([
            new class() extends Dashboard {
                public function authorize(Request $request)
                {
                    return false;
                }
            },
        ]);

        $this->assertCount(0, Nova::availableDashboards(Request::create('/')));
    }
}
