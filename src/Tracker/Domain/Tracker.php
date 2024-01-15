<?php

namespace App\Tracker\Domain;

use App\Tracker\Infrasctructure\Repository\TrackerRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TrackerRepository::class)]
class Tracker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $data;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $date;

    #[ORM\Column]
    private string $uuid;

    public function __construct(
        string $data,
    )
    {
        $this->data = $data;
        $this->date = new DateTime('now');
        $this->uuid = Uuid::v7();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
