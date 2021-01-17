<?php

namespace Laravel\Nova\Tests\Controller;

use Illuminate\Support\Carbon;
use Laravel\Nova\Metrics\Metric;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\PostCountTrend;
use Laravel\Nova\Tests\Fixtures\TotalUsers;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\Fixtures\UserGrowth;
use Laravel\Nova\Tests\IntegrationTest;

class MetricControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testAvailableCardsCanBeRetrieved()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/cards');

        $response->assertStatus(200);
        $this->assertEquals('value-metric', $response->original[0]->jsonSerialize()['component']);
        $this->assertEquals(TotalUsers::class, $response->original[0]->jsonSerialize()['class']);
        $this->assertEquals((new TotalUsers())->uriKey(), $response->original[0]->jsonSerialize()['uriKey']);
        $this->assertFalse($response->original[0]->jsonSerialize()['onlyOnDetail']);
    }

    public function testAvailableMetricsCanBeRetrieved()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics');

        $response->assertStatus(200);
        $this->assertEquals('value-metric', $response->original[0]->jsonSerialize()['component']);
        $this->assertEquals(TotalUsers::class, $response->original[0]->jsonSerialize()['class']);
        $this->assertEquals((new TotalUsers())->uriKey(), $response->original[0]->jsonSerialize()['uriKey']);
        $this->assertFalse($response->original[0]->jsonSerialize()['onlyOnDetail']);
    }

    public function testAvailableMetricsCantBeRetrievedIfNotAuthorizedToViewResource()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/forbidden-users/metrics');

        $response->assertStatus(403);
    }

    public function testUnauthorizedMetricsAreNotReturned()
    {
        $_SERVER['nova.totalUsers.canSee'] = false;

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics');

        unset($_SERVER['nova.totalUsers.canSee']);

        $response->assertStatus(200);
        $this->assertCount(2, $response->original);
        $this->assertEquals(UserGrowth::class, $response->original[0]->jsonSerialize()['class']);
    }

    public function testCanRetrieveMetricValue()
    {
        factory(User::class, 2)->create();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/total-users');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->original['value']->value);
        $this->assertEquals(1, $response->original['value']->previous);
    }

    public function testCanRetrieveDetailOnlyMetricValue()
    {
        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/'.$user->id.'/metrics/customer-revenue');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->original['value']);
        $this->assertEquals(1, $_SERVER['nova.customerRevenue.user']->id);

        unset($_SERVER['nova.customerRevenue.user']);
    }

    public function testCantRetrieveUnauthorizedMetricValues()
    {
        $_SERVER['nova.totalUsers.canSee'] = false;

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/total-users');

        unset($_SERVER['nova.totalUsers.canSee']);

        $response->assertStatus(404);
    }

    public function testAvailableDashboardCardsCanBeRetrieved()
    {
        Nova::cards([new TotalUsers()]);

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/cards');

        $response->assertStatus(200);
        $this->assertInstanceOf(Metric::class, $response->original[0]);
        $this->assertEquals(TotalUsers::class, $response->original[0]->jsonSerialize()['class']);
    }

    public function testAvailableDashboardMetricsCanBeRetrieved()
    {
        Nova::cards([new TotalUsers()]);

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/metrics');

        $response->assertStatus(200);
        $this->assertInstanceOf(Metric::class, $response->original[0]);
        $this->assertEquals(TotalUsers::class, $response->original[0]->jsonSerialize()['class']);
    }

    public function testCanRetrieveDashboardMetricValue()
    {
        Nova::cards([new TotalUsers()]);

        $user = factory(User::class)->create();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/metrics/total-users');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['value']->value);
    }

    public function testCanRetrieveCountCalculations()
    {
        factory(User::class, 2)->create();

        $user = User::find(2);
        $user->created_at = now()->subDays(31);
        $user->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/user-growth?range=30');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['value']->value);
        $this->assertEquals(1, $response->original['value']->previous);
    }

    public function testCanRetrieveCustomColumnCountCalculations()
    {
        factory(User::class, 2)->create();

        $user = User::find(2);
        $user->updated_at = now()->subDays(31);
        $user->save();

        $_SERVER['__nova.userGrowthColumn'] = 'updated_at';

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/user-growth?range=30');

        unset($_SERVER['__nova.userGrowthColumn']);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['value']->value);
        $this->assertEquals(1, $response->original['value']->previous);
    }

    public function testCanRetrieveTodayCountCalculations()
    {
        Carbon::setTestNow('Oct 1 12:00 PM');

        factory(User::class, 3)->create();

        $user = User::find(1);
        $user->created_at = now()->setTime(1, 0, 0);
        $user->save();

        $user = User::find(2);
        $user->created_at = now()->setTime(3, 0, 0);
        $user->save();

        $user = User::find(3);
        $user->created_at = now()->yesterday();
        $user->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/user-growth?range=TODAY');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->original['value']->value);
        $this->assertEquals(1, $response->original['value']->previous);

        Carbon::setTestNow();
    }

    public function testCanRetrieveMtdCountCalculations()
    {
        factory(User::class, 2)->create();

        $user = User::find(2);
        $user->created_at = now()->subMonthsNoOverflow(1)->firstOfMonth();
        $user->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/user-growth?range=MTD');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['value']->value);
        $this->assertEquals(1, $response->original['value']->previous);
    }

    public function testCanRetrieveQtdCountCalculations()
    {
        factory(User::class, 3)->create();

        $user = User::find(2);
        $user->created_at = $this->getFirstDayOfPreviousQuarter();
        $user->save();

        $user = User::find(3);
        $user->created_at = $this->getFirstDayOfPreviousQuarter();
        $user->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/user-growth?range=QTD');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['value']->value);
        $this->assertEquals(2, $response->original['value']->previous);
    }

    public function testCanRetrieveYtdCountCalculations()
    {
        factory(User::class, 2)->create();

        $user = User::find(2);
        $user->created_at = now()->subYearsNoOverflow(1)->firstOfYear();
        $user->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/metrics/user-growth?range=YTD');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->original['value']->value);
        $this->assertEquals(1, $response->original['value']->previous);
    }

    public function testCanRetrieveAverageCalculations()
    {
        factory(Post::class, 2)->create(['word_count' => 100]);

        $post = Post::find(2);
        $post->created_at = now()->subDays(35);
        $post->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/posts/metrics/post-word-count?range=30');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->original['value']->value);
        $this->assertEquals(100, $response->original['value']->previous);
    }

    public function testCanRetrieveTodayAverageCalculations()
    {
        factory(Post::class, 3)->create(['word_count' => 100]);

        $post = Post::find(2);
        $post->word_count = 50;
        $post->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/posts/metrics/post-word-count?range=TODAY');

        $response->assertStatus(200);
        $this->assertEquals(83, $response->original['value']->value);
        $this->assertEquals(0, $response->original['value']->previous);
    }

    public function testCanRetrieveMtdAverageCalculations()
    {
        factory(Post::class, 2)->create(['word_count' => 100]);

        $post = Post::find(2);
        $post->word_count = 50;
        $post->created_at = now()->subMonthsNoOverflow(1)->firstOfMonth();
        $post->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/posts/metrics/post-word-count?range=MTD');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->original['value']->value);
        $this->assertEquals(50, $response->original['value']->previous);
    }

    public function testCanRetrieveQtdAverageCalculations()
    {
        factory(Post::class, 2)->create(['word_count' => 100]);

        $post = Post::find(2);
        $post->word_count = 50;
        $post->created_at = $this->getFirstDayOfPreviousQuarter();
        $post->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/posts/metrics/post-word-count?range=QTD');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->original['value']->value);
        $this->assertEquals(50, $response->original['value']->previous);
    }

    public function testCanRetrieveYtdAverageCalculations()
    {
        factory(Post::class, 2)->create(['word_count' => 100]);

        $post = Post::find(2);
        $post->word_count = 50;
        $post->created_at = now()->subYearsNoOverflow(1)->firstOfYear();
        $post->save();

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/posts/metrics/post-word-count?range=YTD');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->original['value']->value);
        $this->assertEquals(50, $response->original['value']->previous);
    }

    public function testCanRetrieveSumTrendValue()
    {
        Nova::cards([new PostCountTrend()]);

        factory(Post::class, 2)->create([
            'published_at' => now()->subMonth(),
        ]);
        factory(Post::class, 1)->create([
            'published_at' => now()->subMonths(2),
        ]);
        factory(Post::class, 1)->create([
            'published_at' => now()->subMonths(5),
        ]);
        $response = $this->withExceptionHandling()
                         ->get('/nova-api/metrics/post-count-trend?range=30')
                         ->assertStatus(200);

        $this->assertEquals(4, $response->json('value.value'));
    }

    protected function getFirstDayOfPreviousQuarter()
    {
        return Carbon::firstDayOfPreviousQuarter();
    }
}
