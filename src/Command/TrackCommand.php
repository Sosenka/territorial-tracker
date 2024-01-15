<?php

declare(strict_types=1);

namespace App\Command;

use App\Controller\Scraper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('track')]
final class TrackCommand extends Command
{
    public function __construct(
        private Scraper $scraper
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->scraper->scrap();

        return Command::SUCCESS;
    }
}