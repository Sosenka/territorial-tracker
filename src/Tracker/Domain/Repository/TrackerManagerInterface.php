<?php

namespace App\Tracker\Domain\Repository;

interface TrackerManagerInterface
{
    public function save(array $result): void;
}