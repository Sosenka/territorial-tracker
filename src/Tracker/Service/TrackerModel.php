<?php

declare(strict_types=1);

namespace App\Tracker\Service;

use App\Tracker\Domain\Repository\TrackerInterface;

final class TrackerModel implements TrackerInterface
{
    public function getUrl(): string
    {
        return 'https://territorial.io/clans';
    }

    public function getContent(): string
    {
        return file_get_contents($this->getUrl());
    }
}