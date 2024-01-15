<?php

declare(strict_types=1);

namespace App\Utils;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class EndpointEnvUtils extends AbstractController
{
    public function __construct(
        private readonly ParameterBagInterface $bag
    )
    {
    }

    public function getWebhook()
    {
        return match ($_ENV['APP_ENV']) {
            'dev' => $this->bag->get('DISCORD_WEBHOOK_DEV'),
            default => $this->bag->get('DISCORD_WEBHOOK_PROD'),
        };
    }
}