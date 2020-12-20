<?php

namespace Tests\Feature\Modules\CardType;

use App\Models\Card;
use App\Modules\CardType\CardTypeRepository;
use Tests\TestCase;

class CardTypeRepositoryTest extends TestCase
{
    /**
     * @dataProvider cardTypeProvider
     */
    public function testMapCardTypeToWords(string $cardType, string $expected, string $url): void
    {
        $card = Card::find(1);
        if ($url) {
            $card->url = $url;
        }
        $card->card_type_id = app(CardTypeRepository::class)->firstOrCreate($cardType)->id;
        $card->save();

        $result = app(CardTypeRepository::class)->mapCardTypeToWords($card);
        self::assertEquals($result, $expected);
    }

    public function cardTypeProvider(): array
    {
        return [
            ['video/3gpp2', 'Video', ''],
            ['link', 'YouTube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ['link', 'Google Doc', 'https://docs.google.com/document/d/2L39XrmRn5MSiokVXc4c5ipEkdhDYYZUdbHJltaasdViPT0/edit'],
            ['link', 'Link', 'https://medium.com/@noah_weiss/50-articles-and-books-that-will-make-you-a-great-product-manager-aad5babee2f7'],
            ['noway', 'Unknown', ''],
        ];
    }
}
