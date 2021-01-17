<?php

namespace Laravel\Nova\Tests\Feature;

use Exception;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Date;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    public function testFieldCanBeResolved()
    {
        Carbon::setTestNow(Carbon::parse('Oct 14 1984'));

        tap($this->dateField(), function ($field) {
            $field->resolve((object) ['dob' => Carbon::now()]);

            $this->assertEquals('1984-10-14', $field->value);

            Carbon::setTestNow();
        });
    }

    public function testFieldCanBeResolvedForDisplay()
    {
        Carbon::setTestNow(Carbon::parse('Oct 14 1984'));

        tap($this->dateField(), function ($field) {
            $field->resolveForDisplay((object) ['dob' => Carbon::now()]);

            $this->assertEquals('1984-10-14', $field->value);

            Carbon::setTestNow();
        });
    }

    public function testFieldCanBeResolvedWithNullValue()
    {
        tap($this->dateField(), function ($field) {
            tap((object) ['dob' => null], function ($resource) use ($field) {
                $field->resolve($resource);
                $field->resolveForDisplay($resource);

                $this->assertNull($field->value);
            });
        });
    }

    public function testFieldThrowsWhenResolvingWithNonDatetimeValue()
    {
        $this->expectException(Exception::class);

        tap($this->dateField(), function ($field) {
            tap((object) ['dob' => 'wew'], function ($resource) use ($field) {
                $field->resolve($resource);
            });
        });
    }

    public function testFieldThrowsWhenResolvingForDisplayWithNonDatetimeValue()
    {
        $this->expectException(Exception::class);

        tap($this->dateField(), function ($field) {
            tap((object) ['dob' => 'wew'], function ($resource) use ($field) {
                $field->resolveForDisplay($resource);
            });
        });
    }

    /**
     * Return a new DateTime field instance.
     *
     * @return \Laravel\Nova\Fields\Date
     */
    protected function dateField()
    {
        return Date::make('Date Of Birth', 'dob');
    }
}
