<?php

/*
 * Copyright 2023 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Budoux;

use function count;
use function file_get_contents;
use function json_decode;
use function mb_str_split;
use function strlen;

/**
 * The BudouX parser that translates the input sentence into phrases.
 *
 * You can create a parser instance by invoking `new Parser(model)` with the model data you
 * want to use. You can also create a parser by specifying the model file path with
 * `Parser.loadByFileName(modelFileName)`.
 *
 * In most cases, it's sufficient to use the default parser for the language. For example, you
 * can create a default Japanese parser as follows.
 *
 *     $parser = Parser::loadDefaultJapaneseParser();
 *
 * @phpstan-type FeatureScores array<array-key, int>
 * @phpstan-type Model array{
 *     UW1?: FeatureScores,
 *     UW2?: FeatureScores,
 *     UW3?: FeatureScores,
 *     UW4?: FeatureScores,
 *     UW5?: FeatureScores,
 *     UW6?: FeatureScores,
 *     BW1?: FeatureScores,
 *     BW2?: FeatureScores,
 *     BW3?: FeatureScores,
 *     TW1?: FeatureScores,
 *     TW2?: FeatureScores,
 *     TW3?: FeatureScores,
 *     TW4?: FeatureScores,
 * }
 */
abstract class Parser
{
    /**
     * Loads the default Japanese parser.
     */
    public static function loadDefaultJapaneseParser(): Parser
    {
        return new Parser\Japanese();
    }

    /**
     * Loads the default Simplified Chinese parser. Parser
     */
    public static function loadDefaultSimplifiedChineseParser(): Parser
    {
        return new Parser\SimplifiedChinese();
    }

    /**
     * Loads the default Traditional Chinese parser.
     */
    public static function loadDefaultTraditionalChineseParser(): Parser
    {
        return new Parser\TraditionalChinese();
    }

    /**
     * Loads the default Traditional Chinese parser.
     */
    public static function loadDefaultThaiParser(): Parser
    {
        return new Parser\Thai();
    }

    /**
     * Loads a parser by specifying the model file path.
     *
     * @param string $modelFileName the model file path.
     * @phpstan-param non-empty-string $modelFileName
     */
    public static function loadByFileName(string $modelFileName): Parser\File
    {
        $content = file_get_contents($modelFileName);
        assert($content !== false);

        /** @var Model $model */
        $model = json_decode($content, true);

        return new Parser\File($model);
    }

    /**
     * Gets the score for the specified feature of the given sequence.
     *
     * @param key-of<Model> $featureKey the feature key to examine.
     * @param array-key $sequence the sequence to look up the score.
     * @return int the contribution score to support a phrase break.
     */
    protected function getScore(string $featureKey, int|string $sequence): int
    {
        return $this->getModel()[$featureKey][$sequence] ?? 0;
    }

    protected abstract function getTotalScore(): int;

    /**
     * Feature maps for the active model (UW1–UW6, BW1–BW3, TW1–TW4 → score).
     *
     * Cached as locals in {@see parse()} (same idea as the Java Parser).
     * Each feature group is optional. Sequence keys are array-key because pure
     * digit characters become int keys under PHP array semantics.
     *
     * @return Model
     */
    protected abstract function getModel(): array;

    /**
     * Parses a sentence into phrases.
     *
     * @param string $sentence the sentence to break by phrase.
     * @return string[] a list of phrases.
     * @phpstan-return list<string>
     */
    public function parse(string $sentence): array
    {
        if (strlen($sentence) === 0) {
            return [];
        }

        $sentence = mb_str_split($sentence, 1, 'UTF-8');

        $result = [
            $sentence[0],
        ];
        $resultIndex = 0;

        $totalScore = $this->getTotalScore();
        $length = count($sentence);

        // Resolve feature maps once (Java caches Map locals the same way).
        $model = $this->getModel();
        $uw1 = $model['UW1'] ?? null;
        $uw2 = $model['UW2'] ?? null;
        $uw3 = $model['UW3'] ?? null;
        $uw4 = $model['UW4'] ?? null;
        $uw5 = $model['UW5'] ?? null;
        $uw6 = $model['UW6'] ?? null;
        $bw1 = $model['BW1'] ?? null;
        $bw2 = $model['BW2'] ?? null;
        $bw3 = $model['BW3'] ?? null;
        $tw1 = $model['TW1'] ?? null;
        $tw2 = $model['TW2'] ?? null;
        $tw3 = $model['TW3'] ?? null;
        $tw4 = $model['TW4'] ?? null;

        for ($i = 1; $i < $length; $i++) {
            $score = -$totalScore;
            if ($i - 2 > 0 && $uw1 !== null) {
                $score += 2 * ($uw1[$sentence[$i - 3]] ?? 0);
            }
            if ($i - 1 > 0 && $uw2 !== null) {
                $score += 2 * ($uw2[$sentence[$i - 2]] ?? 0);
            }
            if ($uw3 !== null) {
                $score += 2 * ($uw3[$sentence[$i - 1]] ?? 0);
            }
            if ($uw4 !== null) {
                $score += 2 * ($uw4[$sentence[$i]] ?? 0);
            }
            if ($i + 1 < $length && $uw5 !== null) {
                $score += 2 * ($uw5[$sentence[$i + 1]] ?? 0);
            }
            if ($i + 2 < $length && $uw6 !== null) {
                $score += 2 * ($uw6[$sentence[$i + 2]] ?? 0);
            }
            // Prefer direct concatenation over array_slice()+implode() — the latter
            // dominated samples under reli (array_slice ~9% self-time).
            if ($i > 1 && $bw1 !== null) {
                $score += 2 * ($bw1[$sentence[$i - 2] . $sentence[$i - 1]] ?? 0);
            }
            if ($bw2 !== null) {
                $score += 2 * ($bw2[$sentence[$i - 1] . $sentence[$i]] ?? 0);
            }
            if ($i + 1 < $length && $bw3 !== null) {
                $score += 2 * ($bw3[$sentence[$i] . $sentence[$i + 1]] ?? 0);
            }
            if ($i - 2 > 0 && $tw1 !== null) {
                $score += 2 * ($tw1[$sentence[$i - 3] . $sentence[$i - 2] . $sentence[$i - 1]] ?? 0);
            }
            if ($i - 1 > 0 && $tw2 !== null) {
                $score += 2 * ($tw2[$sentence[$i - 2] . $sentence[$i - 1] . $sentence[$i]] ?? 0);
            }
            if ($i + 1 < $length && $tw3 !== null) {
                $score += 2 * ($tw3[$sentence[$i - 1] . $sentence[$i] . $sentence[$i + 1]] ?? 0);
            }
            if ($i + 2 < $length && $tw4 !== null) {
                $score += 2 * ($tw4[$sentence[$i] . $sentence[$i + 1] . $sentence[$i + 2]] ?? 0);
            }
            if ($score > 0) {
                $result[] = '';
                $resultIndex++;
            }

            $result[$resultIndex] .= $sentence[$i];
        }

        // $resultIndex only advances when appending, so keys stay 0..n; array_values
        // keeps the phpdoc list<> contract for PHPStan.
        return array_values($result);
    }

    /**
     * Translates an HTML string with phrases wrapped in no-breaking markup.
     *
     * Requires the {@code ext-dom} extension (Composer {@code suggest}).
     *
     * @param string $html an HTML string
     * @return string the translated HTML string with no-breaking markup
     */
    public function translateHTMLString(string $html): string
    {
        $sentence = HTMLProcessor::getText($html);
        $phrases = $this->parse($sentence);

        return HTMLProcessor::resolve($phrases, $html, "\u{200B}");
    }
}
