#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\YouTube;

$outFile = sprintf('%s/%s_jrl.json', __DIR__, date('Ymd'));
$channelId = 'UCD7m8Q56w6XUnxzGhADHCxw'; // arsjerm
$playlistId = 'PLprF_YM2SJQGVtCiI0DkY9YlvVJWNLMgg'; // Jmikola Reporting Live

$client = new Client();
$client->setApplicationName("jmikola.net/bin");
$client->setDeveloperKey(getenv('GOOGLE_API_KEY'));
$service = new YouTube($client);

if (false) {
    $response = $service->channels->listChannels('id,snippet', ['forUsername' => 'arsjerm']);

    foreach ($response->items as $channel) {
        printf("Channel '%s' id: %s\n", $channel->snippet->title, $channel->id);
    }
}

if (false) {
    $response = $service->playlists->listPlaylists('id,snippet', ['channelId' => $channelId]);

    foreach ($response->items as $playlist) {
        printf("Playlist '%s' id: %s\n", $playlist->snippet->title, $playlist->id);
    }
}

$getVideoIdsForPlaylist = function(string $playlistId) use ($service) {
    $nextPageToken = null;

    do {
        $params = [
            'playlistId' => $playlistId,
            'maxResults' => 50,
        ];

        if ($nextPageToken !== null) {
            $params['pageToken'] = $nextPageToken;
        }

        $response = $service->playlistItems->listPlaylistItems('contentDetails', $params);
        $nextPageToken = $response->nextPageToken ?? null;

        foreach ($response->items as $item) {
            yield $item->contentDetails->videoId;
        }
    } while ($nextPageToken !== null);
};

$getVideos = function(array $videoIds) use ($service) {
    // The "id" filter is limited to 50 results at a time
    foreach (array_chunk($videoIds, 50) as $chunk) {
        $ids = implode(',', $chunk);

        $response = $service->videos->listVideos('id,recordingDetails,snippet', ['id' => $ids]);

        foreach ($response->items as $video) {
            yield [
                'id' => $video->id,
                'recordingDate' => $video->recordingDetails->recordingDate,
                'publishedAt' => $video->snippet->publishedAt,
                'title' => $video->snippet->title,
                'description' => $video->snippet->description,
            ];
        }
    }
};

$videoIds = iterator_to_array($getVideoIdsForPlaylist($playlistId));
$videos = iterator_to_array($getVideos($videoIds));

file_put_contents($outFile, json_encode($videos, JSON_PRETTY_PRINT));
