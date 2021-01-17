<?php

namespace Laravel\Nova\Tests\Feature;

use Brick\Money\Context\CustomContext;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Tests\IntegrationTest;

class CurrencyTest extends IntegrationTest
{
    public function testComputedCurrencyFieldCanBeResolvedForDisplay()
    {
        $field = Currency::make('Cost', function () {
            return 777;
        });

        $field->resolveForDisplay((object) []);

        $this->assertEquals('$777.00', $field->value);
    }

    public function testTheFieldIsDisplayedWithCurrencyCharacter()
    {
        $field = Currency::make('Cost');

        $field->resolveForDisplay((object) ['cost' => 200]);

        $this->assertEquals('$200.00', $field->value);
    }

    public function testTheFieldIsDisplayedWithSymbol()
    {
        $field = Currency::make('Cost')->symbol('USD');

        $field->resolveForDisplay((object) ['cost' => 200]);

        $this->assertEquals('USD 200.00', $field->value);
    }

    public function testTheFieldWithLargeValueIsDisplayedWithCurrencyCharacterOn()
    {
        $field = Currency::make('Cost');

        $field->resolveForDisplay((object) ['cost' => 2000000]);

        $this->assertEquals('$2,000,000.00', $field->value);
    }

    public function testTheFieldWithLargeValueIsDisplayedWithSymbol()
    {
        $field = Currency::make('Cost')->symbol('USD');

        $field->resolveForDisplay((object) ['cost' => 2000000]);

        $this->assertEquals('USD 2,000,000.00', $field->value);
    }

    public function testTheFieldCanSetCurrency()
    {
        $field = Currency::make('Cost')->currency('GBP');

        $field->resolveForDisplay((object) ['cost' => 200]);

        $this->assertEquals('£200.00', $field->value);
    }

    public function testTheFieldCanSetCurrencyAndSymbol()
    {
        $field = Currency::make('Cost')->currency('GBP')->symbol('$');

        $field->resolveForDisplay((object) ['cost' => 200]);

        $this->assertEquals('$200.00', $field->value);
    }

    public function testTheFieldCanChangeLocale()
    {
        $field = Currency::make('Cost')->currency('EUR')->locale('nl_NL');

        $field->resolveForDisplay((object) ['cost' => 200]);

        $this->assertEquals('€ 200,00', $field->value);
    }

    public function testTheFieldCanChangeLocaleAndSymbol()
    {
        $field = Currency::make('Cost')->currency('EUR')->locale('nl_NL')->symbol('EUR');

        $field->resolveForDisplay((object) ['cost' => 200]);

        $this->assertEquals('EUR 200,00', $field->value);
    }

    public function testTheFieldWithLargeValueCanChangeLocale()
    {
        $field = Currency::make('Cost')->currency('EUR')->locale('nl_NL');

        $field->resolveForDisplay((object) ['cost' => 2000000]);

        $this->assertEquals('€ 2.000.000,00', $field->value);
    }

    public function testTheFieldWithLargeValueCanChangeLocaleAndSymbol()
    {
        $field = Currency::make('Cost')->currency('EUR')->locale('nl_NL')->symbol('EUR');

        $field->resolveForDisplay((object) ['cost' => 2000000]);

        $this->assertEquals('EUR 2.000.000,00', $field->value);
    }

    public function testTheFieldHandlesNull()
    {
        $field = Currency::make('Cost')->nullable();

        $field->resolveForDisplay((object) ['cost' => null]);

        $this->assertNull($field->value);
    }

    public function testTheFieldHandlesNullWithoutSettingAsNullable()
    {
        $field = Currency::make('Cost');

        $field->resolveForDisplay((object) ['cost' => null]);

        $this->assertNull($field->value);
    }

    public function testTheFieldCanUseMinorUnits()
    {
        $field = Currency::make('Cost')->asMinorUnits();

        $field->resolve((object) ['cost' => 200]);
        $this->assertEquals(200, $field->value);

        $field->resolveForDisplay((object) ['cost' => 200]);
        $this->assertEquals('$2.00', $field->value);
    }

    public function testTheFieldCanHaveNullValues()
    {
        $field = Currency::make('Cost')
            ->nullable()
            ->asMinorUnits();

        $field->resolve((object) ['cost' => null]);
        $this->assertEquals(null, $field->value);

        $field->resolveForDisplay((object) ['cost' => null]);
        $this->assertEquals(null, $field->value);
    }

    public function testTheFieldCanSetContext()
    {
        $field = Currency::make('Cost')->context(new CustomContext(8));

        $field->resolveForDisplay((object) ['cost' => 200.12345678]);

        $this->assertEquals('$200.12345678', $field->value);
    }
}
