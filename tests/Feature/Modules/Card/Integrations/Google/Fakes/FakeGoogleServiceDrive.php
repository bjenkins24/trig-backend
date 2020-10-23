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
    public function listFiles(array $params)
    {
        if (false !== strpos($params['fields'], 'nextPageToken')) {
            return new FileFake();
        }

        return [new FileFake(), new FileFake()];
    }
}
