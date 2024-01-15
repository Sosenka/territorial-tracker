<?php

declare(strict_types=1);

namespace App\Controller;

use App\Tracker\Domain\Repository\TrackerFinderInterface;
use App\Tracker\Domain\Repository\TrackerInterface;
use App\Tracker\Domain\Repository\TrackerManagerInterface;
use App\Utils\EndpointEnvUtils;
use GuzzleHttp\Client;
use Imagick;
use Knp\Bundle\SnappyBundle\Snappy\Response\JpegResponse;
use Knp\Snappy\Image;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class Scraper extends AbstractController
{
    private string $defaultDir;
    private string $discordToken;
    private string $discordWebhook;

    public function __construct(
        private readonly TrackerInterface $tracker,
        private readonly TrackerManagerInterface $trackerManager,
        private readonly Image            $image,
        private ParameterBagInterface     $parameterBag,
        private EndpointEnvUtils          $endpointEnvUtils,
    )
    {
        $this->defaultDir = $this->parameterBag->get('kernel.project_dir') . '/public/tracker/';
        $this->discordToken = $this->parameterBag->get('discord_token');
        $this->discordWebhook = $this->endpointEnvUtils->getWebhook();
    }

    /**
     * @throws \JsonException
     */
    public function scrap(): Response
    {
        $text = $this->tracker->getContent();
        $header = ['rank', 'clan', 'points'];
        $result = [];

        $text = substr($text, strpos($text, '1,'));
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $fields = explode(', ', $line);
            $result[] = array_combine($header, $fields);
        }

        $this->trackerManager->save($result);

        return new Response('OK');
    }

    /**
     * @throws \ImagickException
     * testing imagick library to generate image by html
     */
    #[Route('/board')]
    public function board(): Response
    {
        $html = $this->renderView('board.html.twig', [
            'ranks' => [],
            'wins' => ['contest' => [], 'normal' => []],
        ]);

        $htmlFile = '/home/pawsos/Sites/territorial/public/test/test.html';
        file_put_contents($htmlFile, $html);

        $image = new Imagick();
        $image->readImageBlob($html);
        $image->setImageFormat("jpg");

        $image->setImageBackgroundColor('white');
        $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OPAQUE);
        $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        $image->setImageCompressionQuality(90);
        $image->writeImage('obrazu.jpg');

        return $this->render('board.html.twig');
    }

    public function createImage(array $ranks, $wins): Response
    {
        $html = $this->renderView('board.html.twig', [
            'ranks' => $ranks,
            'wins' => $wins,
        ]);

        $output = new JpegResponse($this->image->getOutputFromHtml($html, [
            'enable-javascript' => true,
            'javascript-delay' => 0,
            'no-stop-slow-scripts' => true,
            'use-xserver' => true,
            'encoding' => 'utf-8',
            'width' => 700,
            'images' => true,
            'enable-smart-width' => true,
            'enable-local-file-access' => true,
            'quality' => 100,

        ]));

        $filesystem = new Filesystem();
        $name = Uuid::v7();

        $filesystem->dumpFile($this->defaultDir . $name->jsonSerialize() . '.jpg', $output->getContent());
        $this->sendAnnouncement($name->jsonSerialize());

        return new Response('OK');
    }

    public function sendAnnouncement(string $imageName): Response
    {
        $client = new Client();

        $client->request('POST', $this->discordWebhook, [
            'json' => [
                "avatar_url" => 'https://upload.wikimedia.org/wikipedia/commons/8/82/Poland_Countryball.png',
                "username" => "DASHBOARD",
                "content" => "<@&1192855356238479450>",
                "embeds" => [
                    [
                        'type' => 'rich',
                        "title" => 'Dashboard',
                        'description' => 'Daily update of ranking in territorial.io',
                        'color' => 1127128,
                        "image" => [
                            "url" => 'https://territorial-tracker.fun/tracker/' . $imageName . '.jpg',
                        ],
                        'author' => [
                            'name' => 'yonkish',
                            'icon_url' => 'https://cdn.discordapp.com/avatars/750224063640633348/e874c95379614e38225427ce17e705e8.png?size=1024',
                        ],
                        'footer' => [
                            'text' => 'version 0.1.1 abandoned',
                        ],
                    ],
                ],
            ],
        ]);

        return new Response('OK');
    }

    public function fetchDiscordMessages(int $limit = 100): array
    {
        $ourDate = new \DateTime();
        $ourDate->setTime(1, 0, 0);
        $messages = [];


        $client = new Client();
        $lastMessageId = "";
        $still = true;

        do {
            $response = $this->getOlderMessages($lastMessageId);

            foreach ($response as $message) {
                $discordMessageDate = new \DateTime($message->timestamp);
                if ($discordMessageDate > $ourDate) {
                    $content = array_values(explode('   ', $message->content));

                    $messages[] = [
                        'clan' => explode(' ', trim($content[2]))[0],
                        'map' => $content[0],
                    ];

                    $lastMessageId = $message->id;
                } else {
                    $still = false;

                }
            }

        } while ($still);

        return $this->calculateCountOfWins($messages);
    }

    public function calculateCountOfWins(array $wins)
    {
        $normalWinCount = [];
        $contestWinCount = [];

        foreach ($wins as $win) {
            $clan = $win['clan'];

            if ($win['map'][0] === '*') {
                $contestWinCount[$clan] = ($contestWinCount[$clan] ?? 0) + 1;
            } else {
                $normalWinCount[$clan] = ($normalWinCount[$clan] ?? 0) + 1;
            }
        }


        arsort($normalWinCount, SORT_REGULAR);
        arsort($contestWinCount, SORT_REGULAR);
        $normalWinCount = array_slice($normalWinCount, 0, 5);
        $contestWinCount = array_slice($contestWinCount, 0, 5);
        return ['normal' => $normalWinCount, 'contest' => $contestWinCount];
    }

    private function getOlderMessages(string $lastMessageId = "", int $limit = 100): array
    {
        $client = new Client();

        $request = $client->request('GET', 'https://discord.com/api/v9/channels/917537295261913159/messages?before=' . $lastMessageId . '&limit=' . $limit, [
            'headers' => [
                'authorization' => $this->discordToken,
                'accept' => '*/*',
            ],
        ]);

        return json_decode($request->getBody()->getContents());
    }
}