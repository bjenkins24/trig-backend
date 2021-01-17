<?php

namespace Laravel\Nova\Tests\Feature;

use Exception;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\DateTime;
use PHPUnit\Framework\TestCase;

class DateTimeTest extends TestCase
{
    public function testFieldCanBeResolved()
    {
        Carbon::setTestNow(Carbon::parse('Oct 14 1984'));

        tap($this->dateTimeField(), function ($field) {
            $field->resolve((object) ['created_at' => Carbon::now()]);

            $this->assertEquals('1984-10-14 00:00:00.000000', $field->value);

            Carbon::setTestNow();
        });
    }

    public function testFieldCanBeResolvedForDisplay()
    {
        Carbon::setTestNow(Carbon::parse('Oct 14 1984'));

        tap($this->dateTimeField(), function ($field) {
            $field->resolveForDisplay((object) ['created_at' => Carbon::now()]);

            $this->assertEquals('1984-10-14 00:00:00.000000', $field->value);

            Carbon::setTestNow();
        });
    }

    public function testFieldCanBeResolvedDatetimeWithFractionalSecondsForDisplay()
    {
        tap($this->dateTimeField(), function ($field) {
            $field->resolveForDisplay((object) ['created_at' => new \DateTime('1984-10-14 10:10:30.889342')]);

            $this->assertEquals('1984-10-14 10:10:30.889342', $field->value);

            Carbon::setTestNow();
        });
    }

    public function testFieldCanBeResolvedWithNullValue()
    {
        tap($this->dateTimeField(), function ($field) {
            tap((object) ['created_at' => null], function ($resource) use ($field) {
                $field->resolve($resource);
                $field->resolveForDisplay($resource);

                $this->assertNull($field->value);
            });
        });
    }

    public function testFieldThrowsWhenResolvingWithNonDatetimeValue()
    {
        $this->expectException(Exception::class);

        tap($this->dateTimeField(), function ($field) {
            tap((object) ['created_at' => 'wew'], function ($resource) use ($field) {
                $field->resolve($resource);
            });
        });
    }

    public function testFieldThrowsWhenResolvingForDisplayWithNonDatetimeValue()
    {
        $this->expectException(Exception::class);

        tap($this->dateTimeField(), function ($field) {
            tap((object) ['created_at' => 'wew'], function ($resource) use ($field) {
                $field->resolveForDisplay($resource);
            });
        });
    }

    /**
     * Return a new DateTime field instance.
     *
     * @return \Laravel\Nova\Fields\DateTime
     */
    protected function dateTimeField()
    {
        return DateTime::make('Created At');
    }
}
