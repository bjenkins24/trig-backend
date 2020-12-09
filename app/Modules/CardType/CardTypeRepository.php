<?php

namespace App\Modules\CardType;

use App\Models\Card;
use App\Models\CardType;
use Illuminate\Support\Str;

class CardTypeRepository
{
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
        $cardTypeName = CardType::where('id', $card->card_type_id)->get()->name;
        if ('link' === $cardTypeName && ! empty($card->url)) {
            return $this->mapLinkToType($card->url);
        }
        if (Str::contains($cardTypeName, ['image', 'cdr', 'corel'])) {
            return 'Image';
        }
        if (Str::contains($cardTypeName, 'video')) {
            return 'Video';
        }
        if (Str::contains($cardTypeName, 'audio')) {
            return 'Audio';
        }
        if (Str::contains($cardTypeName, ['excel', 'xls', 'iwork-numbers', 'apple.numbers', 'officedocument.spreadsheetml', 'comma-separated-values'])) {
            return 'Spreadsheet';
        }
        if (Str::contains($cardTypeName, ['powerpoint', 'ms-office', 'officedocument.presentationml'])) {
            return 'Presentation';
        }
        if (Str::contains($cardTypeName, ['wordprocessingml.document', 'msword', 'pdf', 'octet-stream', 'apple.pages', 'richtext', 'rtf', 'text/plain', 'text/vtt'])) {
            return 'Document';
        }
        if (Str::contains($cardTypeName, ['zip', 'compressed', 'tar'])) {
            return 'Zip/RAR/Tar';
        }
        if (Str::contains($cardTypeName, ['php'])) {
            return 'PHP';
        }
        if (Str::contains($cardTypeName, ['javascript', 'typescript'])) {
            return 'Javascript';
        }
        if (Str::contains($cardTypeName, ['json'])) {
            return 'JSON';
        }
        if (Str::contains($cardTypeName, ['sql'])) {
            return 'SQL';
        }
        if (Str::contains($cardTypeName, ['zsh'])) {
            return 'ZSH';
        }
        if (Str::contains($cardTypeName, ['application/xml', 'text/xml', 'xspf+xml'])) {
            return 'XML';
        }
        if (Str::contains($cardTypeName, ['html'])) {
            return 'HTML';
        }
        if (Str::contains($cardTypeName, ['css'])) {
            return 'CSS';
        }
        if (Str::contains($cardTypeName, ['flash'])) {
            return 'Flash';
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

        // Needs icon
        if (Str::contains($url, 'analytics.google.com')) {
            return 'Google Analytics';
        }
        // Needs icon
        if (Str::contains($url, 'mail.google.com')) {
            return 'Gmail';
        }
        if (Str::contains($url, 'docs.google') && Str::contains($url, 'document')) {
            return 'Google Doc';
        }
        if (Str::contains($url, 'docs.google') && Str::contains($url, 'spreadsheets')) {
            return 'Google Sheet';
        }
        if (Str::contains($url, 'docs.google') && Str::contains($url, 'presentation')) {
            return 'Google Slide';
        }
        if (Str::contains($url, 'docs.google') && Str::contains($url, 'forms')) {
            return 'Google Form';
        }
        if (Str::contains($host, 'amplitude')) {
            return 'Amplitude';
        }
        if (Str::contains($host, 'airtable')) {
            return 'Airtable';
        }
        if (Str::contains($host, 'asana')) {
            return 'Asana';
        }
        // Needs icon
        if (Str::contains($host, 'basecamp.com')) {
            return 'Basecamp';
        }
        if (Str::contains($host, 'bitbucket')) {
            return 'Bitbucket';
        }
        // Need icon
        if (Str::contains($host, 'chartio')) {
            return 'Chartio';
        }
        // Need icon
        if (Str::contains($host, 'clubhouse.io')) {
            return 'Clubhouse';
        }
        if (Str::contains($host, 'confluence')) {
            return 'Confluence';
        }
        if (Str::contains($url, 'customer.io')) {
            return 'Customer.io';
        }
        if (Str::contains($host, 'figma')) {
            return 'Figma';
        }
        if (Str::contains($host, 'github')) {
            return 'Github';
        }
        if (Str::contains($host, 'helpscout')) {
            return 'Helpscout';
        }
        // Need Icon
        if (Str::contains($host, 'hubspot')) {
            return 'Hubspot';
        }
        if (Str::contains($host, 'jira')) {
            return 'Jira';
        }
        if (Str::contains($host, 'linkedin')) {
            return 'LinkedIn';
        }
        // Need Icon
        if (Str::contains($host, 'mailchimp')) {
            return 'Mailchimp';
        }
        if (Str::contains($host, 'notion')) {
            return 'Notion';
        }
        if (Str::contains($host, 'quora')) {
            return 'Quora';
        }
        if (Str::contains($host, 'stackoverflow')) {
            return 'Stack Overflow';
        }
        // Need icon
        if (Str::contains($host, 'surveymonkey')) {
            return 'SurveyMonkey';
        }
        if (Str::contains($host, 'reddit')) {
            return 'Reddit';
        }
        if (Str::contains($host, 'trello')) {
            return 'Trello';
        }
        if (Str::contains($host, 'typeform')) {
            return 'Typeform';
        }
        if (Str::contains($host, 'youtube') || Str::contains($host, 'youtu.be')) {
            return 'YouTube';
        }
        if (Str::contains($host, 'zendesk')) {
            return 'Zendesk';
        }

        return $type;
    }
}
