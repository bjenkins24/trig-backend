<?php

namespace Tests\Feature\Modules\Card\Integrations\Google\Fakes;

class FakeGoogleServiceDrive
{
    public FakeGoogleServiceDriveFiles $files;

    public function __construct()
    {
        $this->files = new FakeGoogleServiceDriveFiles();
    }
}

class FakeGoogleServiceDriveFiles
{
    public const EXPORTED = 'EXPORTED';
    public const GET = 'GET';

    public function listFiles(array $params)
    {
        if (false !== strpos($params['fields'], 'nextPageToken')) {
            return new FileFake();
        }

        return [new FileFake(), new FileFake()];
    }

    public function export($id, $mimeType): FakeContent
    {
        return new FakeContent(self::EXPORTED);
    }

    public function get($id, $params): FakeContent
    {
        return new FakeContent(self::GET);
    }
}
