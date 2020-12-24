<?php

namespace App\Utils\WebsiteExtraction;

use andreskrey\Readability\ParseException;

class Website
{
    private string $rawContent;
    private ?string $image = null;
    private ?string $screenshot = null;
    private ?string $author;
    private ?string $excerpt;
    private ?string $title;
    private ?string $content;

    public function setRawContent(string $rawContent): Website
    {
        $this->rawContent = $rawContent;

        return $this;
    }

    public function getRawContent(): string
    {
        return $this->rawContent;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): Website
    {
        $this->content = $content;

        return $this;
    }

    public function setTitle(?string $title): Website
    {
        $this->title = $title;

        return $this;
    }

    public function setScreenshot(?string $screenshot): Website
    {
        $this->screenshot = $screenshot;

        return $this;
    }

    public function getScreenshot(): ?string
    {
        return $this->screenshot;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setImage(?string $image): Website
    {
        $this->image = $image;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setExcerpt(?string $excerpt): Website
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setAuthor(?string $author): Website
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * @throws ParseException
     */
    public function parseContent(): Website
    {
        $parsedContent = app(WebsiteExtractionHelper::class)->parseHtml($this->rawContent);
        $this->image = $parsedContent->get('image');
        $this->author = $parsedContent->get('author');
        $this->excerpt = $parsedContent->get('excerpt');
        $this->title = $parsedContent->get('title');
        $this->content = $parsedContent->get('html');

        return $this;
    }
}
