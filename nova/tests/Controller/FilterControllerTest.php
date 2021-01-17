<?php

namespace Laravel\Nova\Tests\Controller;

use Laravel\Nova\Tests\Fixtures\AdditionalOptionsFilter;
use Laravel\Nova\Tests\Fixtures\CreateDateFilter;
use Laravel\Nova\Tests\Fixtures\IdFilter;
use Laravel\Nova\Tests\IntegrationTest;

class FilterControllerTest extends IntegrationTest
{
    public function setUp(): void
    {
        parent::setUp();

        $this->authenticate();
    }

    public function testCanRetrieveFiltersForAResource()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/filters');

        $response->assertStatus(200);
        $this->assertInstanceOf(IdFilter::class, $response->original[0]);
    }

    public function testFilterConfigurationOptionsCanBeSet()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/filters');

        $this->assertInstanceOf(CreateDateFilter::class, $response->original[3]);
        $this->assertEquals(4, $response->original[3]->meta['firstDayOfWeek']);
    }

    public function testUnauthorizedFiltersAreNotIncluded()
    {
        $_SERVER['nova.idFilter.canSee'] = false;
        $_SERVER['nova.customKeyFilter.canSee'] = false;
        $_SERVER['nova.columnFilter.canSee'] = false;
        $_SERVER['nova.dateFilter.canSee'] = false;

        $response = $this->withExceptionHandling()
                        ->get('/nova-api/users/filters');

        unset($_SERVER['nova.idFilter.canSee']);
        unset($_SERVER['nova.customKeyFilter.canSee']);
        unset($_SERVER['nova.columnFilter.canSee']);
        unset($_SERVER['nova.dateFilter.canSee']);

        $response->assertStatus(200);
        $this->assertEmpty($response->original);
    }

    public function testEmptyFilterListReturnedIfNoFilters()
    {
        $response = $this->withExceptionHandling()
                        ->get('/nova-api/addresses/filters');

        $response->assertStatus(200);
        $this->assertEmpty($response->original);
    }

    public function testJsonForAlternativeDeclaration()
    {
        $filter = new AdditionalOptionsFilter();
        $json = json_encode($filter);
        $expected = json_encode([
            'class'     => AdditionalOptionsFilter::class,
            'name'      => $filter->name(),
            'component' => $filter->component(),
            'options'   => [
                [
                    'name'  => 'label 1',
                    'value' => 'value 1',
                ],
                [
                    'name'  => 'label 2',
                    'value' => 'value 2',
                ],
                [
                    'value' => 'value 3',
                    'name'  => 'label 3',
                ],
                [
                    'value' => 'value 4',
                    'name'  => 'label 4',
                    'group' => 'group 1',
                ],
            ],
            'currentValue' => '',
        ]);

        $this->assertJsonStringEqualsJsonString($expected, $json);
    }
}
