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

use function array_key_last;
use function array_slice;
use function count;
use function implode;
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

        $totalScore = $this->getTotalScore();
        $length = count($sentence);

        for ($i = 1; $i < $length; $i++) {
            $score = -$totalScore;
            if ($i - 2 > 0) {
                $score += 2 * $this->getScore("UW1", $sentence[$i - 3]);
            }
            if ($i - 1 > 0) {
                $score += 2 * $this->getScore("UW2", $sentence[$i - 2]);
            }
            $score += 2 * $this->getScore("UW3", $sentence[$i - 1]);
            $score += 2 * $this->getScore("UW4", $sentence[$i]);
            if ($i + 1 < $length) {
                $score += 2 * $this->getScore("UW5", $sentence[$i + 1]);
            }
            if ($i + 2 < $length) {
                $score += 2 * $this->getScore("UW6", $sentence[$i + 2]);
            }
            if ($i > 1) {
                $score += 2 * $this->getScore("BW1", implode(array_slice($sentence, $i - 2, 2)));
            }
            $score += 2 * $this->getScore("BW2", implode(array_slice($sentence, $i - 1, 2)));
            if ($i + 1 < $length) {
                $score += 2 * $this->getScore("BW3", implode(array_slice($sentence, $i, 2)));
            }
            if ($i - 2 > 0) {
                $score += 2 * $this->getScore("TW1", implode(array_slice($sentence, $i - 3, 3)));
            }
            if ($i - 1 > 0) {
                $score += 2 * $this->getScore("TW2", implode(array_slice($sentence, $i - 2, 3)));
            }
            if ($i + 1 < $length) {
                $score += 2 * $this->getScore("TW3", implode(array_slice($sentence, $i - 1, 3)));
            }
            if ($i + 2 < $length) {
                $score += 2 * $this->getScore("TW4", implode(array_slice($sentence, $i, 3)));
            }
            if ($score > 0) {
                $result[] = '';
            }

            $result[array_key_last($result)] .= $sentence[$i];
        }

        return $result;
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
