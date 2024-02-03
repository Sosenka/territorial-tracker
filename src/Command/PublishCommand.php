<?php

declare(strict_types=1);

namespace App\Command;

use App\Controller\Scraper;
use App\Tracker\Infrasctructure\Repository\TrackerRepository;
use Knp\Snappy\Image;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand('publish')]
final class PublishCommand extends Command
{
    public function __construct(
        private Scraper           $scraper,
        private TrackerRepository $trackerRepository,
        private readonly Image    $image,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->analize();
    }

    private function analize(): int
    {
        $elements = $this->trackerRepository->returnLastElements();

        if (count($elements) < 2 || !isset($elements[0], $elements[1])) {
            return 0;
        }

        $before = $elements[1];
        $after = $elements[0];

        $result = [];

        $afterData = json_decode($after->getData());
        $beforeData = json_decode($before->getData());

        if (!$afterData || !$beforeData) {
            return 0;
        }

        $foundMatch = false;

        foreach ($afterData as $e1) {
            foreach ($beforeData as $e0) {
                if ($e1->clan === $e0->clan) {
                    $result[] = [
                        "rank" => (int)$e0->rank - (int)$e1->rank,
                        "clan" => $e1->clan,
                        "points" => (float)$e1->points,
                        "ratio" => round((float)$e1->points - (float)$e0->points, 3),
                    ];

                    $foundMatch = true;
                    continue 2;
                }
            }

            if ($foundMatch) {
                break;
            }
        }

        $wins = $this->scraper->fetchDiscordMessages();

        $this->scraper->createImage(array_slice($result, 0, 21), $wins);
        return Command::SUCCESS;

    }
}