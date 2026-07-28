#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Micro-benchmark for BudouX parse / translateHTMLString hot paths.
 *
 * Usage:
 *   php php/script/bench_parse.php [seconds=15] [mode=parse|html|both]
 *
 * Designed for reli sampling:
 *   php php/script/bench_parse.php 20 parse &
 *   reli inspector:trace -p "$(pgrep -nfx 'php php/script/bench_parse.php 20 parse')" -o /tmp/budoux.rbt
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Budoux\Parser;

$seconds = max(1, (int) ($argv[1] ?? 15));
$mode = $argv[2] ?? 'both';
if (!in_array($mode, ['parse', 'html', 'both'], true)) {
    fwrite(STDERR, "mode must be parse|html|both\n");
    exit(1);
}

$samples = [
    '今日は天気です。',
    '本日は晴天なり。ありがとうございます。',
    '私はその人を常に先生と呼んでいた。だからここでもただ先生と書くだけで本名は打ち明けない。',
    'あのイーハトーヴォのすきとおった風、夏でも底に冷たさをもつ青いそら、うつくしい森で飾られたモリーオ市、郊外のぎらぎらひかる草の波。',
    'BudouX is a machine learning powered line break organizer tool. 日本語とEnglishが混在する文も扱います。',
];

$htmlSamples = [
    '今日は<b>とても天気</b>です。',
    '<p>本日は晴天なり。<a href="https://example.com">リンク</a>もあります。</p>',
    '<div>あのイーハトーヴォのすきとおった風、<span>夏でも底に冷たさをもつ青いそら</span>。</div>',
];

$parser = Parser::loadDefaultJapaneseParser();
$deadline = microtime(true) + $seconds;
$iterations = 0;
$ops = 0;

fwrite(STDERR, sprintf(
    "bench start: seconds=%d mode=%s pid=%d\n",
    $seconds,
    $mode,
    getmypid(),
));

while (microtime(true) < $deadline) {
    if ($mode === 'parse' || $mode === 'both') {
        foreach ($samples as $sentence) {
            $parser->parse($sentence);
            $ops++;
        }
    }
    if ($mode === 'html' || $mode === 'both') {
        foreach ($htmlSamples as $html) {
            $parser->translateHTMLString($html);
            $ops++;
        }
    }
    $iterations++;
}

$elapsed = $seconds; // wall budget; actual may be slightly over
fwrite(STDERR, sprintf(
    "bench done: iterations=%d ops=%d ops_per_sec=%.1f\n",
    $iterations,
    $ops,
    $ops / max(0.001, microtime(true) - ($deadline - $seconds)),
));
