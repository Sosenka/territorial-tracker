<?php

namespace App\Tracker\Domain\Repository;

interface TrackerInterface
{
    public function getContent(): string;
}