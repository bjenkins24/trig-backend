<?php

namespace Laravel\Nova\Tests\Feature;

use Illuminate\Support\Str;
use Laravel\Nova\Metrics\PartitionResult;
use Laravel\Nova\Tests\IntegrationTest;

class PartitionTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testPartitionKeysAndValuesAreFormattedCorrectlyWhenSerialized()
    {
        $result = new PartitionResult(['Monthly' => 60, 'Yearly' => 90]);

        $this->assertEquals([
            'value' => [
                ['label' => 'Monthly', 'value' => 60],
                ['label' => 'Yearly', 'value' => 90],
            ],
        ], $result->jsonSerialize());
    }

    public function testColorsArePresentInResultsWhenSetWithStringLabels()
    {
        $result = new PartitionResult(['Monthly' => 60, 'Yearly' => 90]);
        $result->colors(['Monthly' => '#fff', 'Yearly' => '#000']);

        $this->assertEquals([
            'value' => [
                ['label' => 'Monthly', 'value' => 60, 'color' => '#fff'],
                ['label' => 'Yearly', 'value' => 90, 'color' => '#000'],
            ],
        ], $result->jsonSerialize());
    }

    public function testColorsArePresentInResultsWhenSetWithStringLabelsWithCustomLabel()
    {
        $result = new PartitionResult(['weekly' => 10, 'monthly' => 60, 'yearly' => 90]);
        $result->label(function ($label) {
            return Str::title($label);
        })->colors([
            'weekly'  => '#fff',
            'monthly' => '#000',
        ]);

        $this->assertEquals([
            'value' => [
                ['label' => 'Weekly', 'value' => 10, 'color' => '#fff'],
                ['label' => 'Monthly', 'value' => 60, 'color' => '#000'],
                ['label' => 'Yearly', 'value' => 90],
            ],
        ], $result->jsonSerialize());
    }

    public function testColorsArePresentInResultsWhenProvidedColorMap()
    {
        $result = new PartitionResult(['Weekly' => 10, 'Monthly' => 60, 'Yearly' => 90]);
        $result->colors(['#fff', '#000']);

        $this->assertEquals([
            'value' => [
                ['label' => 'Weekly', 'value' => 10, 'color' => '#fff'],
                ['label' => 'Monthly', 'value' => 60, 'color' => '#000'],
                ['label' => 'Yearly', 'value' => 90, 'color' => '#fff'],
            ],
        ], $result->jsonSerialize());
    }

    public function testColorsArePresentInResultsWhenSetWithStringLabelsWithCustomLabelAfterSerialization()
    {
        $result = new PartitionResult(['weekly' => 10, 'monthly' => 60, 'yearly' => 90]);
        $result->label(function ($label) {
            return Str::title($label);
        })->colors([
            'weekly'  => '#fff',
            'monthly' => '#000',
        ]);

        $this->assertEquals([
            'value' => [
                ['label' => 'Weekly', 'value' => 10, 'color' => '#fff'],
                ['label' => 'Monthly', 'value' => 60, 'color' => '#000'],
                ['label' => 'Yearly', 'value' => 90],
            ],
        ], unserialize(serialize($result))->jsonSerialize());
    }
}
