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

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMComment;
use RuntimeException;

use function count;
use function extension_loaded;
use function htmlspecialchars;
use function implode;
use function in_array;
use function mb_str_split;
use function preg_match;
use function sprintf;
use function strtoupper;

/**
 * Processes phrases into an HTML string wrapping them in no-breaking markup.
 *
 * Ported from the Java {@code HTMLProcessor} implementation.
 *
 * Requires the {@code ext-dom} extension (listed under Composer {@code suggest}).
 *
 * @internal
 */
final class HTMLProcessor
{
    private const STYLE = 'word-break: keep-all; overflow-wrap: anywhere;';

    /**
     * HTML element names whose text content must not be split.
     *
     * Mirrors {@code budoux/skip_nodes.json}.
     */
    private const SKIP_NODES = [
        'ABBR',
        'BUTTON',
        'CODE',
        'IFRAME',
        'INPUT',
        'META',
        'NOBR',
        'SCRIPT',
        'STYLE',
        'TEXTAREA',
        'TIME',
        'VAR',
    ];

    /** Separator used while scanning joined phrases (U+FFFF). */
    private const SEP = "\u{FFFF}";

    /** HTML void elements (no end tag). */
    private const VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    private function __construct()
    {
    }

    /**
     * Gets the text content from the input HTML string.
     *
     * {@code <br>} is converted to {@code \n}, matching the Java implementation.
     */
    public static function getText(string $html): string
    {
        self::assertDomExtension();
        $body = self::parseBodyFragment($html);
        $extractor = new TextContentExtractor();
        self::textize($body, $extractor);

        return $extractor->output;
    }

    /**
     * Wraps phrases in the HTML string with non-breaking markup.
     *
     * @param list<string> $phrases the phrases included in the HTML string
     */
    public static function resolve(array $phrases, string $html, string $separator = "\u{200B}"): string
    {
        self::assertDomExtension();
        $body = self::parseBodyFragment($html);
        $state = new PhraseResolvingState(
            mb_str_split(implode(self::SEP, $phrases), 1, 'UTF-8'),
            $separator,
        );
        self::resolveChildren($body, $state);

        return sprintf('<span style="%s">%s</span>', self::STYLE, $state->output);
    }

    private static function assertDomExtension(): void
    {
        if (!extension_loaded('dom')) {
            throw new RuntimeException(
                'ext-dom is required for HTML processing. Install the PHP DOM extension.',
            );
        }
    }

    private static function parseBodyFragment(string $html): DOMElement
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        // Wrap as a full document so libxml builds a predictable body tree,
        // analogous to Jsoup.parseBodyFragment().
        // The meta charset declaration is required: without it, libxml treats
        // the input as ISO-8859-1 and corrupts multi-byte text.
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . $html
            . '</body></html>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

        $body = $dom->getElementsByTagName('body')->item(0);
        assert($body instanceof DOMElement);

        return $body;
    }

    private static function textize(DOMNode $node, TextContentExtractor $extractor): void
    {
        if ($node instanceof DOMElement) {
            if ($node->nodeName === 'br') {
                $extractor->output .= "\n";
            }
            foreach ($node->childNodes as $child) {
                self::textize($child, $extractor);
            }

            return;
        }

        if ($node instanceof DOMText) {
            $extractor->output .= $node->wholeText;
        }
    }

    private static function resolveChildren(DOMNode $parent, PhraseResolvingState $state): void
    {
        foreach ($parent->childNodes as $child) {
            self::resolveNode($child, $state);
        }
    }

    private static function resolveNode(DOMNode $node, PhraseResolvingState $state): void
    {
        if ($node instanceof DOMComment) {
            return;
        }

        if ($node instanceof DOMElement) {
            $state->elementStack[] = $state->toSkip;
            $attributesEncoded = self::encodeAttributes($node);
            $nodeName = $node->nodeName;
            $phrasesJoinedLength = count($state->phrasesJoinedChars);

            if ($nodeName === 'br') {
                // `<br>` is converted to `\n` in getText(); advance past that char.
                $state->scanIndex++;
            } elseif (in_array(strtoupper($nodeName), self::SKIP_NODES, true)) {
                if (
                    !$state->toSkip
                    && $state->scanIndex < $phrasesJoinedLength
                    && $state->phrasesJoinedChars[$state->scanIndex] === self::SEP
                ) {
                    $state->output .= $state->separator;
                    $state->scanIndex++;
                }
                $state->toSkip = true;
            }

            $state->output .= sprintf('<%s%s>', $nodeName, $attributesEncoded);

            self::resolveChildren($node, $state);

            $state->toSkip = array_pop($state->elementStack) ?? false;

            if (!isset(self::VOID_ELEMENTS[$nodeName])) {
                $state->output .= sprintf('</%s>', $nodeName);
            }

            return;
        }

        if ($node instanceof DOMText) {
            foreach (mb_str_split($node->wholeText, 1, 'UTF-8') as $c) {
                $joinedChar = $state->phrasesJoinedChars[$state->scanIndex] ?? '';
                if ($c !== $joinedChar) {
                    // Assume phrasesJoined[scanIndex] == SEP.
                    $prevWasWhitespace = $state->scanIndex > 0
                        && self::isWhitespace($state->phrasesJoinedChars[$state->scanIndex - 1]);
                    if (!$state->toSkip && !self::isWhitespace($c) && !$prevWasWhitespace) {
                        $state->output .= $state->separator;
                    }
                    $state->scanIndex++;
                }
                $state->scanIndex++;
                $state->output .= $c;
            }
        }
    }

    private static function encodeAttributes(DOMElement $element): string
    {
        if (!$element->hasAttributes()) {
            return '';
        }

        $parts = [];
        foreach ($element->attributes as $attribute) {
            $parts[] = sprintf(
                ' %s="%s"',
                $attribute->name,
                htmlspecialchars($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
        }

        return implode('', $parts);
    }

    private static function isWhitespace(string $char): bool
    {
        return preg_match('/^\s$/u', $char) === 1;
    }
}
