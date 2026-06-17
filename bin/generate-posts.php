#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;

function to_relative_path(string $path): string
{
    $from = explode('/', rtrim(dirname(__FILE__), '/'));
    $to   = explode('/', rtrim($path, '/'));

    while ($from && $to && $from[0] === $to[0]) {
        array_shift($from);
        array_shift($to);
    }

    return str_repeat('../', count($from)) . implode('/', $to);
}

function error_exit(string $message) {
    fprintf(STDERR, "%s%s", rtrim($message), PHP_EOL);
    exit(1);
}

function get_video_date(object $video): DateTime
{
    if (preg_match('/^📅 (\d{8})\b/', $video->description, $m)) {
        return DateTime::createFromFormat('Ymd', $m[1]) ?: null;
    }

    if (!empty($video->recordingDate)) {
        return new DateTime($video->recordingDate);
    }

    error_exit(sprintf("Cannot parse date from video %s: %s", $video->id, $video-title));
}

function get_video_basename(object $video): string
{
    $date = get_video_date($video);

    // strip Chinese characters in parentheticals
    $title = trim(preg_replace('/\s*\(\p{Han}+\)\s*/u', ' ', $video->title));

    // strip prefix and single/double quotes before generating slugs
    $strip = ['Jmikola Reporting Live', '\'', '"', "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"];

    return $date->format('Y-m-d-') . trim(str_replace($strip, '', $title));
}


$inFile = $argv[1] ?? null;
$outDir = realpath(__DIR__ . '/../_posts/jrl/video');

if ($inFile === null || !is_file($inFile) || !is_readable($inFile)) {
    error_exit(sprintf("%s is not a readable file", $inFile ?? '(null)'));
}

if (!is_dir($outDir) || !is_writable($outDir)) {
    error_exit(sprintf("%s is not a writable directory", $outFile));
}

$videos = json_decode(file_get_contents($inFile));
$slugger = new AsciiSlugger('en');

foreach ($videos as $video) {
    $frontMatter = Yaml::dump([
        'title' => $video->title,
        'youtube_id' => $video->id,
    ]);

    $output = <<<END
        ---
        {$frontMatter}
        ---
        {$video->description}
        END;

    $basename = get_video_basename($video);
    $outFile = $outDir . '/' . $slugger->slug($basename)->lower() . '.md';

    printf("→ %s%s", to_relative_path($outFile), PHP_EOL);
    file_put_contents($outFile, $output);
}
