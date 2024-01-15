<?php

declare(strict_types=1);

namespace App\Tracker\Application;

use App\Tracker\Domain\Repository\TrackerManagerInterface;
use App\Tracker\Domain\Tracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class TrackerFactory extends AbstractController implements TrackerManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ){}

    public function save(array $result): void
    {
        $tracker = new Tracker(
            data: json_encode($result, JSON_THROW_ON_ERROR),
        );

        $this->entityManager->persist($tracker);
        $this->entityManager->flush();
    }

}