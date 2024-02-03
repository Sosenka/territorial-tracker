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
use function MongoDB\BSON\toJSON;

final class Scraper extends AbstractController
{
    private string $defaultDir;
    private string $discordToken;
    private string $discordWebhook;

    public function __construct(
        private readonly TrackerInterface        $tracker,
        private readonly TrackerManagerInterface $trackerManager,
        private readonly Image                   $image,
        private ParameterBagInterface            $parameterBag,
        private EndpointEnvUtils                 $endpointEnvUtils,
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
        return $this->render('test-board.html.twig', [
            'ranks' => [],
            'wins' => [
                'normalCount' => [
                    "CORGI" => 51.29,
                    "OG" => 49.4,
                    "VOID" => 18.186,
                    "FR" => 10.764,
                    "PG" => 9.768,
                ],
                'contestCount' => [
                    "CORGI" => 4.517,
                    "OG" => 1.913,
                    "PG" => 0.965,
                    "VOID" => 0.423,
                    "YUKARI" => 0.359,

                ],
                'normalPoints' => [
                    "CORGI" => 1178.411,
                    "OG" => 1079.508,
                    "PG" => 374.125,
                    "VOID" => 362.433,
                    "FR" => 182.417,
                ],
                'contestPoints' => [
                    "CORGI" => 782.364,
                    "OG" => 431.72,
                    "PG" => 169.84,
                    "VOID" => 63.45,
                    "YUKARI" => 45.234,
                ],
            ],
        ]);
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
                            'text' => 'version 0.2.0',
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
        $ourDate->setTime(0, 0, 0);
        $messages = [];


        $lastMessageId = "";
        $still = true;

        do {
            $response = $this->getOlderMessages($lastMessageId);

            foreach ($response as $message) {
                $discordMessageDate = new \DateTime($message->timestamp);
                if ($discordMessageDate > $ourDate) {

                    $pattern = '/Player Count:\s+(\d+)/';
                    preg_match($pattern, $message->content, $playerCount);

                    $pattern = '/Game Mode:\s+(.+)\n/';
                    preg_match($pattern, $message->content, $gameMode);

                    if (!$gameMode && !$playerCount) {
                        continue;
                    }

                    $pattern = '/\[([A-Z]+)\]:\s+\d+\.\d+\s+\+\s+\d+\.\d+\s+=\s+(\d+\.\d+),\s+T\s+=\s+\d+\s+\((\s*\d+\.\d+)\s*%\)/';
                    preg_match_all($pattern, json_encode($message->content), $matches, PREG_SET_ORDER);

                    $outputArray = [
                        'playerCount' => $playerCount[1],
                        'gameMode' => $gameMode[1],
                        'clans' => [],
                    ];

                    foreach ($matches as $match) {
                        $multiplier = str_contains($gameMode[1], 'Contest') ? 2 : 1;

                        $outputArray['clans'][] = [
                            'clan' => $match[1],
                            'percent' => $match[3],
                            'points' => ((float)$match[3] !== 0.0 ? (int)$playerCount[1] * $match[3] / 100 : 0) * $multiplier,
                            'winRate' => 1 * $match[3] / 100,
                            'contest' => $multiplier === 2,
                        ];
                    }

                    $messages[] = $outputArray;

                    $lastMessageId = $message->id;
                } else {
                    $still = false;

                }
            }

        } while ($still);

        return $this->calculateCountOfWins($messages);
    }

    public function calculateCountOfWins(array $wins): array
    {
        $normalWinCount = $contestWinCount = $normalWinPointsCount = $contestWinPointsCount = [];

        foreach ($wins as $win) {
            foreach ($win['clans'] as $clan) {

                switch ($clan['contest']) {
                    case true:
                        if (array_key_exists($clan['clan'], $contestWinCount)) {
                            $contestWinCount[$clan['clan']] += round($clan['winRate'], 1);
                        } else {
                            $contestWinCount[$clan['clan']] = round($clan['winRate'], 1);
                        }

                        if (array_key_exists($clan['clan'], $contestWinPointsCount)) {
                            $contestWinPointsCount[$clan['clan']] += round($clan['points'], 2);
                        } else {
                            $contestWinPointsCount[$clan['clan']] =  round($clan['points'], 2);
                        }
                        break;
                    case false:
                        if (array_key_exists($clan['clan'], $normalWinCount)) {
                            $normalWinCount[$clan['clan']] += round($clan['winRate'], 1);
                        } else {
                            $normalWinCount[$clan['clan']] = round($clan['winRate'], 1);
                        }

                        if (array_key_exists($clan['clan'], $normalWinPointsCount)) {
                            $normalWinPointsCount[$clan['clan']] +=  round($clan['points'], 2);
                        } else {
                            $normalWinPointsCount[$clan['clan']] =  round($clan['points'], 2);
                        }
                        break;
                }
            }

        }


        arsort($normalWinCount, SORT_REGULAR);
        arsort($contestWinCount, SORT_REGULAR);
        arsort($contestWinPointsCount, SORT_REGULAR);
        arsort($normalWinPointsCount, SORT_REGULAR);

        return [
            'normalCount' => array_slice($normalWinCount, 0, 5),
            'contestCount' => array_slice($contestWinCount, 0, 5),
            'normalPoints' => array_slice($normalWinPointsCount, 0, 5),
            'contestPoints' => array_slice($contestWinPointsCount, 0, 5),
        ];
    }

    private function getOlderMessages(string $lastMessageId = "", int $limit = 100): array
    {
        $client = new Client();

        if ($lastMessageId === "") {
            $url = 'https://discord.com/api/v9/channels/917537295261913159/messages?limit=' . $limit;
        } else {
            $url = 'https://discord.com/api/v9/channels/917537295261913159/messages?before=' . $lastMessageId . '&limit=' . $limit;
        }

        $request = $client->request('GET', $url, [
            'headers' => [
                'authorization' => $this->discordToken,
                'accept' => '*/*',
            ],
        ]);

        return json_decode($request->getBody()->getContents());
    }
}