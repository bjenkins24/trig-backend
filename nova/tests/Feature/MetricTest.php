<?php

namespace Laravel\Nova\Tests\Feature;

use Cake\Chronos\Chronos;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Nova;
use Laravel\Nova\Tests\Fixtures\AverageWordCount;
use Laravel\Nova\Tests\Fixtures\Post;
use Laravel\Nova\Tests\Fixtures\PostAverageTrend;
use Laravel\Nova\Tests\Fixtures\PostCountTrend;
use Laravel\Nova\Tests\Fixtures\PostWithCustomCreatedAt;
use Laravel\Nova\Tests\Fixtures\TotalUsers;
use Laravel\Nova\Tests\Fixtures\User;
use Laravel\Nova\Tests\IntegrationTest;

class MetricTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        DB::disableQueryLog();
        DB::flushQueryLog();

        parent::tearDown();
    }

    public function testMetricCanBeCalculated()
    {
        factory(User::class, 2)->create();

        $this->assertEquals(2, (new TotalUsers())->calculate(NovaRequest::create('/'))->value);
    }

    public function testMetricCalculationUsingUserTimezone()
    {
        $metric = new class() extends Value {
            public function calculate(NovaRequest $request)
            {
                return $this->count($request, User::class);
            }
        };

        factory(User::class)->create(['created_at' => Carbon::parse('Oct 14 2019 4 pm')]); // 11am Chicago, 12pm New York
        factory(User::class)->create(['created_at' => Carbon::parse('Oct 14 2019 5 pm')]); // 12pm Chicago, 1pm New York

        Carbon::setTestNow(Carbon::parse('Oct 14 2019 10 am', 'America/Chicago'));

        $request = NovaRequest::create('/', 'GET', ['timezone' => 'America/Chicago']);
        $this->assertEquals(0, $metric->calculate($request)->value);

        Carbon::setTestNow(Carbon::parse('Oct 14 2019 11 am', 'America/Chicago'));

        $request = NovaRequest::create('/', 'GET', ['timezone' => 'America/Chicago']);
        $this->assertEquals(1, $metric->calculate($request)->value);

        Carbon::setTestNow(Carbon::parse('Oct 14 2019 12 pm', 'America/Chicago'));

        $request = NovaRequest::create('/', 'GET', ['timezone' => 'America/Chicago']);
        $this->assertEquals(2, $metric->calculate($request)->value);

        Carbon::setTestNow(Carbon::parse('Oct 14 2019 11 am', 'America/New_York'));

        $request = NovaRequest::create('/', 'GET', ['timezone' => 'America/New_York']);
        $this->assertEquals(0, $metric->calculate($request)->value);

        Carbon::setTestNow(Carbon::parse('Oct 14 2019 12 pm', 'America/New_York'));

        $request = NovaRequest::create('/', 'GET', ['timezone' => 'America/New_York']);
        $this->assertEquals(1, $metric->calculate($request)->value);

        Carbon::setTestNow(null);
    }

    public function testMetricCalculationUsesCustomTimezone()
    {
        Nova::userTimezone(function () {
            return 'UTC';
        });

        $metric = new class() extends Value {
            public function calculate(NovaRequest $request)
            {
                return $this->count($request, User::class);
            }
        };

        $now = Carbon::parse('Oct 14 2019 5 pm'); // UTC (future time)
        $nowCentral = $now->copy()->tz('America/Chicago'); // Now for the user

        Carbon::setTestNow(Carbon::parse($nowCentral));

        factory(User::class)->create(['created_at' => $now]);
        factory(User::class)->create(['created_at' => $nowCentral]);

        // Note even if we send the user's timezone, it should still use UTC
        $request = NovaRequest::create('/', 'GET', ['timezone' => 'America/Chicago']);

        $this->assertEquals(2, $metric->calculate($request)->value);

        Carbon::setTestNow(null);

        Nova::userTimezone(null);
    }

    public function testTrendWithCustomCreatedAt()
    {
        factory(Post::class, 2)->create();

        $post = Post::find(1);
        $post->published_at = Chronos::now();
        $post->save();

        $post = Post::find(2);
        $post->published_at = Chronos::now()->subDay(1);
        $post->save();

        $this->assertEquals([1, 1], array_values((new PostCountTrend())->countByDays(NovaRequest::create('/?range=2'), new PostWithCustomCreatedAt())->trend));
    }

    public function testTrendCalculationUsingUserTimezone()
    {
        $metric = new class() extends Trend {
        };

        Chronos::setTestNow(Chronos::parse('Dec 14 2019', 'UTC'));

        $now = Chronos::parse('Nov 1 2019 6:30 AM', 'UTC');

        $nowCentral = Chronos::parse('Nov 2 2019 12 AM', 'UTC');

        factory(User::class, 2)->create(['created_at' => $now]);
        factory(User::class, 7)->create(['created_at' => $nowCentral]);

        $request = NovaRequest::create('/?range=2', 'GET', ['timezone' => 'America/Chicago']);
        $this->assertEquals([0, 9], array_values($metric->countByMonths($request, User::class)->trend));

        $request = NovaRequest::create('/?range=2', 'GET', ['timezone' => 'America/Los_Angeles']);
        $this->assertEquals([2, 7], array_values($metric->countByMonths($request, User::class)->trend));

        Chronos::setTestNow(Chronos::parse('Nov 2 2019 8 AM', 'Japan'));

        $request = NovaRequest::create('/?range=2', 'GET', ['timezone' => 'Japan']);
        $this->assertEquals([0, 2], array_values($metric->countByDays($request, User::class)->trend));

        Chronos::setTestNow(Chronos::parse('Nov 2 2019 9 AM', 'Japan'));

        $request = NovaRequest::create('/?range=2', 'GET', ['timezone' => 'Japan']);
        $this->assertEquals([2, 7], array_values($metric->countByDays($request, User::class)->trend));

        Chronos::setTestNow(null);
    }

    public function testTrendCalculationUsingCustomTimezone()
    {
        Nova::userTimezone(function () {
            return 'UTC';
        });

        $metric = new class() extends Trend {
        };

        Chronos::setTestNow(Chronos::parse('Dec 14 2019', 'UTC'));

        $now = Chronos::parse('Nov 1 2019 6:30 AM', 'UTC');

        $nowCentral = Chronos::parse('Nov 2 2019 12 AM', 'UTC');

        factory(User::class, 2)->create(['created_at' => $now]);
        factory(User::class, 7)->create(['created_at' => $nowCentral]);

        $request = NovaRequest::create('/?range=2', 'GET', ['timezone' => 'America/Chicago']);
        $this->assertEquals([9, 0], array_values($metric->countByMonths($request, User::class)->trend));

        $request = NovaRequest::create('/?range=2', 'GET', ['timezone' => 'America/Los_Angeles']);
        $this->assertEquals([9, 0], array_values($metric->countByMonths($request, User::class)->trend));

        Chronos::setTestNow(null);

        Nova::userTimezone(null);
    }

    public function testMetricsCanBeSetToRefreshAutomatically()
    {
        $metric = new PostCountTrend();

        $this->assertfalse($metric->jsonSerialize()['refreshWhenActionRuns']);

        $metric->refreshWhenActionRuns();

        $this->assertTrue($metric->jsonSerialize()['refreshWhenActionRuns']);

        $metric->refreshWhenActionRuns(false);

        $this->assertFalse($metric->jsonSerialize()['refreshWhenActionRuns']);
    }

    public function testValueMetricsDefaultPrecision()
    {
        $averageWordCount = factory(Post::class, 2)->create()->average('word_count');
        $this->assertEquals($averageWordCount, (new AverageWordCount())->calculate(NovaRequest::create('/'))->value);
    }

    public function testValueMetricsCustomPrecision()
    {
        $averageWordCount = factory(Post::class, 2)->create(['word_count' => 5.37894])->average('word_count');
        $this->assertEquals($averageWordCount, 5.37894);
        $this->assertEquals(5.38, (new AverageWordCount())->precision(2)->calculate(NovaRequest::create('/'))->value);
    }

    public function testTrendMetricsDefaultPrecision()
    {
        Carbon::setTestNow($now = now());

        factory(Post::class, 2)->create(['word_count' => 5.37894, 'published_at' => $now])->average('word_count');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertEquals(5, Arr::first((new PostAverageTrend())->calculate(NovaRequest::create('/', 'GET', ['range'=>1]))->trend));

        $this->assertSame([
            Carbon::today()->startOfMonth()->toDatetimeString(), $now->toDatetimeString(),
        ], array_map(function ($date) {
            return $date->toDatetimeString();
        }, DB::getQueryLog()[0]['bindings']));
    }

    public function testTrendMetricsCustomPrecision()
    {
        Carbon::setTestNow($now = now());

        factory(Post::class, 2)->create(['word_count' => 5.37894, 'published_at' => $now])->average('word_count');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertEquals(5.38, Arr::first((new PostAverageTrend())->precision(2)->calculate(NovaRequest::create('/', 'GET', ['range'=>1]))->trend));

        $this->assertSame([
            Carbon::today()->startOfMonth()->toDatetimeString(), $now->toDatetimeString(),
        ], array_map(function ($date) {
            return $date->toDatetimeString();
        }, DB::getQueryLog()[0]['bindings']));
    }

    public function testTrendMetricsExceedsRange()
    {
        Carbon::setTestNow($now = now());

        factory(Post::class, 2)->create(['word_count' => 5.37894, 'published_at' => $now])->average('word_count');

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertEquals(5, Arr::last((new PostAverageTrend())->calculate(NovaRequest::create('/', 'GET', ['range'=>24]))->trend));

        $this->assertSame([
            Carbon::today()->startOfMonth()->subMonths(11)->toDatetimeString(), $now->toDatetimeString(),
        ], array_map(function ($date) {
            return $date->toDatetimeString();
        }, DB::getQueryLog()[0]['bindings']));
    }

    public function testValueMetricsCanProvideADefaultRange()
    {
        $metric = new TotalUsers();

        $metric->ranges = [
            1 => 'January',
            2 => 'February',
        ];

        $metric->defaultRange(1);

        $this->assertEquals(1, $metric->jsonSerialize()['selectedRangeKey']);
    }

    public function testValueMetricsDefaultRangeDefaultsToNull()
    {
        $metric = new TotalUsers();

        $this->assertNull($metric->jsonSerialize()['selectedRangeKey']);
    }

    public function testPartitionMetricsCanProvideDataWithRawColumnExpression()
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $metric = new class() extends Partition {
            public function calculate(Request $request)
            {
                return $this->max($request, User::class, DB::raw('json_extract(meta, "$.value")'), 'id');
            }
        };

        $request = NovaRequest::create('/', 'GET', []);

        $metric->calculate($request);

        $this->assertSame(
            'select "id", max(json_extract(meta, "$.value")) as aggregate from "users" where "users"."deleted_at" is null group by "id"',
            DB::getQueryLog()[0]['query']
        );
    }

    public function testTrendMetricsCanProvideDataWithRawColumnExpression()
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $metric = new class() extends Trend {
            public function calculate(Request $request)
            {
                return $this->max($request, User::class, 'day', DB::raw('json_extract(meta, "$.value")'));
            }
        };

        $request = NovaRequest::create('/', 'GET', []);

        $metric->calculate($request);

        $this->assertSame(
            'select strftime(\'%Y-%m-%d\', datetime("users"."created_at", \'+0 hour\')) as date_result, max(json_extract(meta, "$.value")) as aggregate from "users" where "users"."created_at" between ? and ? and "users"."deleted_at" is null group by strftime(\'%Y-%m-%d\', datetime("users"."created_at", \'+0 hour\')) order by "date_result" asc',
            DB::getQueryLog()[0]['query']
        );
    }

    public function testValueMetricsCanProvideDataWithRawColumnExpression()
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $metric = new class() extends Value {
            public function calculate(Request $request)
            {
                return $this->max($request, User::class, DB::raw('json_extract(meta, "$.value")'));
            }
        };

        $request = NovaRequest::create('/', 'GET', []);

        $metric->calculate($request);

        $this->assertSame(
            'select max(json_extract(meta, "$.value")) as aggregate from "users" where "users"."created_at" between ? and ? and "users"."deleted_at" is null',
            DB::getQueryLog()[0]['query']
        );
    }
}
