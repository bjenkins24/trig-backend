<?php

namespace App\Modules\CardType;

use App\Models\Card;
use App\Models\CardType;
use Illuminate\Support\Str;

class CardTypeRepository
{
    public const CARD_TYPE_MAP = [
        ['display' => 'Image', 'types' => ['image', 'cdr', 'corel']],
        ['display' => 'Video', 'types' => ['video']],
        ['display' => 'Audio', 'types' => ['audio']],
        ['display' => 'Audio', 'types' => ['audio']],
        ['display' => 'Spreadsheet', 'types' => ['excel', 'xls', 'iwork-numbers', 'apple.numbers', 'officedocument.spreadsheetml', 'comma-separated-values']],
        ['display' => 'Presentation', 'types' => ['powerpoint', 'ms-office', 'officedocument.presentationml']],
        ['display' => 'Document', 'types' => ['wordprocessingml.document', 'msword', 'pdf', 'octet-stream', 'apple.pages', 'richtext', 'rtf', 'text/plain', 'text/vtt']],
        ['display' => 'Zip/RARA/Tar', 'types' => ['zip', 'compressed', 'tar']],
        ['display' => 'PHP', 'types' => ['php']],
        ['display' => 'Javascript', 'types' => ['javascript', 'typescript']],
        ['display' => 'JSON', 'types' => ['json']],
        ['display' => 'SQL', 'types' => ['sql']],
        ['display' => 'ZSH', 'types' => ['zsh']],
        ['display' => 'XML', 'types' => ['application/xml', 'text/xml', 'xspf+xml']],
        ['display' => 'HTML', 'types' => ['html']],
        ['display' => 'CSS', 'types' => ['css']],
        ['display' => 'Flash', 'types' => ['flash']],
    ];

    public const LINK_TYPE_MAP = [
        ['display' => 'Google Analytics', 'type' => 'url', 'matches' => ['analytics.google.com']],
        ['display' => 'Gmail', 'type' => 'url', 'matches' => ['mail.google.com']],
        ['display' => 'Google Doc', 'type' => 'url', 'conditional' => 'AND', 'matches' => ['docs.google', 'document']],
        ['display' => 'Google Sheet', 'type' => 'url', 'conditional' => 'AND', 'matches' => ['docs.google', 'spreadsheets']],
        ['display' => 'Google Slide', 'type' => 'url', 'conditional' => 'AND', 'matches' => ['docs.google', 'presentation']],
        ['display' => 'Google Form', 'type' => 'url', 'conditional' => 'AND', 'matches' => ['docs.google', 'forms']],
        // Needs Icon
        ['display' => 'Amazon', 'type' => 'host', 'matches' => ['amazon']],
        ['display' => 'Amplitude', 'type' => 'host', 'matches' => ['amplitude']],
        ['display' => 'Airtable', 'type' => 'host', 'matches' => ['airtable']],
        ['display' => 'Audible', 'type' => 'host', 'matches' => ['audible']],
        // Needs Icon
        ['display' => 'Customer.io', 'type' => 'host', 'matches' => ['customer.io']],
        ['display' => 'Figma', 'type' => 'host', 'matches' => ['figma']],
        // Needs Icon
        ['display' => 'Basecamp', 'type' => 'host', 'matches' => ['basecamp.com']],
        ['display' => 'Bitbucket', 'type' => 'host', 'matches' => ['bitbucket']],
        // Needs Icon
        ['display' => 'Chartio', 'type' => 'host', 'matches' => ['chartio']],
        // Needs Icon
        ['display' => 'Clubhouse', 'type' => 'host', 'matches' => ['clubhouse.io']],
        ['display' => 'Confluence', 'type' => 'host', 'matches' => ['confluence']],
        ['display' => 'Github', 'type' => 'host', 'matches' => ['github']],
        // Needs Icon
        ['display' => 'Helpscout', 'type' => 'host', 'matches' => ['helpscout']],
        // Needs Icon
        ['display' => 'Hubspot', 'type' => 'host', 'matches' => ['hubspot']],
        ['display' => 'Jira', 'type' => 'host', 'matches' => ['jira']],
        ['display' => 'LinkedIn', 'type' => 'host', 'matches' => ['linkedin']],
        // Needs Icon
        ['display' => 'Mailchimp', 'type' => 'host', 'matches' => ['mailchimp']],
        ['display' => 'Notion', 'type' => 'host', 'matches' => ['notion']],
        ['display' => 'Quora', 'type' => 'host', 'matches' => ['quora']],
        ['display' => 'Stack Overflow', 'type' => 'host', 'matches' => ['stackoverflow']],
        // Needs Icon
        ['display' => 'SurveyMonkey', 'type' => 'host', 'matches' => ['surveymonkey']],
        ['display' => 'Reddit', 'type' => 'host', 'matches' => ['reddit']],
        ['display' => 'Trello', 'type' => 'host', 'matches' => ['trello']],
        ['display' => 'Typeform', 'type' => 'host', 'matches' => ['typeform']],
        ['display' => 'YouTube', 'type' => 'host', 'conditional' => 'OR', 'matches' => ['youtube', 'youtu.be']],
        ['display' => 'Zendesk', 'type' => 'host', 'matches' => ['zendesk']],
    ];

    /**
     * Create a card type or return it.
     */
    public function firstOrCreate(string $name): CardType
    {
        return CardType::firstOrCreate(['name' => $name]);
    }

    public function findByName(string $name): CardType
    {
        return CardType::where('name', '=', $name)->first();
    }

    public function mapCardTypeToWords(Card $card): string
    {
        $cardTypeName = CardType::find($card->card_type_id)->name;
        if ('link' === $cardTypeName && ! empty($card->url)) {
            return $this->mapLinkToType($card->url);
        }
        foreach (self::CARD_TYPE_MAP as $map) {
            if (Str::contains($cardTypeName, $map['types'])) {
                return $map['display'];
            }
        }

        return 'Unknown';
    }

    public function mapLinkToType(string $url): string
    {
        $type = 'Link';
        if (empty(parse_url($url)['host'])) {
            return $type;
        }

        $host = parse_url($url)['host'];

        foreach (self::LINK_TYPE_MAP as $map) {
            $linkType = $host;
            if ('url' === $map['type']) {
                $linkType = $url;
            }
            // And conditional
            if (isset($map['conditional']) && 'AND' === $map['conditional']) {
                $isMatching = true;
                foreach ($map['matches'] as $match) {
                    if ($isMatching) {
                        // One false will end it
                        $isMatching = Str::contains($linkType, $match);
                    }
                }
                if ($isMatching) {
                    return $map['display'];
                }
            }
            // OR conditional
            if (isset($map['conditional']) && 'OR' === $map['conditional']) {
                foreach ($map['matches'] as $match) {
                    if (Str::contains($linkType, $match)) {
                        return $map['display'];
                    }
                }
            }
            // No conditional
            if (! isset($map['conditional']) && Str::contains($linkType, $map['matches'][0])) {
                return $map['display'];
            }
        }

        return $type;
    }
}
