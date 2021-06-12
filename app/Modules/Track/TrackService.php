<?php

class TrackService
{
    public function identify()
    {
    }

    public function track(array $payload): void
    {
        Segment::track($payload);
    }
}
