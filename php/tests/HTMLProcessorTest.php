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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Ported from Java {@code HTMLProcessorTest}.
 */
#[CoversClass(HTMLProcessor::class)]
class HTMLProcessorTest extends TestCase
{
    private const PRE = '<span style="word-break: keep-all; overflow-wrap: anywhere;">';
    private const POST = '</span>';

    private function wrap(string $input): string
    {
        return self::PRE . $input . self::POST;
    }

    public function testResolveWithSimpleTextInput(): void
    {
        $phrases = ['abc', 'def'];
        $html = 'abcdef';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('abc<wbr>def'), $result);
    }

    public function testResolveWithStandardHTMLInput(): void
    {
        $phrases = ['abc', 'def'];
        $html = 'ab<a href="http://example.com">cd</a>ef';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame(
            $this->wrap('ab<a href="http://example.com">c<wbr>d</a>ef'),
            $result,
        );
    }

    public function testResolveWithImg(): void
    {
        $phrases = ['abc', 'def'];
        $html = '<img>abcdef';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('<img>abc<wbr>def'), $result);
    }

    public function testResolveWithNodesToSkip(): void
    {
        $phrases = ['abc', 'def', 'ghi'];
        $html = 'a<button>bcde</button>fghi';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('a<button>bcde</button>f<wbr>ghi'), $result);
    }

    public function testResolveWithNodesBreakBeforeSkip(): void
    {
        $phrases = ['abc', 'def', 'ghi', 'jkl'];
        $html = 'abc<nobr>defghi</nobr>jkl';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('abc<wbr><nobr>defghi</nobr><wbr>jkl'), $result);
    }

    public function testResolveWithAfterSkip(): void
    {
        $phrases = ['abc', 'def', 'ghi', 'jkl'];
        $html = 'abc<nobr>def</nobr>ghijkl';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('abc<wbr><nobr>def</nobr><wbr>ghi<wbr>jkl'), $result);
    }

    public function testResolveWithAfterSkipWithImg(): void
    {
        $phrases = ['abc', 'def', 'ghi', 'jkl'];
        $html = 'abc<nobr>d<img>ef</nobr>ghijkl';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame(
            $this->wrap('abc<wbr><nobr>d<img>ef</nobr><wbr>ghi<wbr>jkl'),
            $result,
        );
    }

    public function testResolveWithNothingToSplit(): void
    {
        $phrases = ['abcdef'];
        $html = 'abcdef';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('abcdef'), $result);
    }

    public function testResolveBR(): void
    {
        $html = ' 1  <br>  2 ';
        $text = HTMLProcessor::getText($html);
        $this->assertSame(" 1  \n  2 ", $text);
        $phrases = [" 1  \n  2 "];
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap(' 1  <br>  2 '), $result);
    }

    public function testGetText(): void
    {
        $html = 'Hello <button><b>W</b>orld</button>!';
        $result = HTMLProcessor::getText($html);
        $this->assertSame('Hello World!', $result);
    }

    public function testGetTextWhiteSpace(): void
    {
        $html = ' H    e  ';
        $result = HTMLProcessor::getText($html);
        $this->assertSame(' H    e  ', $result);
    }

    public function testGetTextWhiteSpaceAcrossElements(): void
    {
        $html = '<div> 1 </div><div> 2 </div>';
        $result = HTMLProcessor::getText($html);
        $this->assertSame(' 1  2 ', $result);
    }

    public function testResolveSkipNodeAtTheEnd(): void
    {
        $phrases = ['abc', 'def', 'ghi', 'jkl'];
        $html = 'abcdefghijkl<img src="example.png">';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame(
            $this->wrap('abc<wbr>def<wbr>ghi<wbr>jkl<img src="example.png">'),
            $result,
        );
    }

    public function testResolveWithComments(): void
    {
        $phrases = ['abc', 'def', 'ghi', 'jkl'];
        $html = 'abcdef<!-- comments should be ignored-->ghijkl';
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap('abc<wbr>def<wbr>ghi<wbr>jkl'), $result);
    }

    public function testResolveWithListItemsAndWhitespace(): void
    {
        $phrases = ['abc', "\ndef"];
        $html = "<ul><li>abc</li>\n<li>def</li></ul>";
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap("<ul><li>abc</li>\n<li>def</li></ul>"), $result);
    }

    public function testResolveWithBreakAfterWhitespaceInList(): void
    {
        $phrases = ["abc\n", 'def'];
        $html = "<ul><li>abc</li>\n<li>def</li></ul>";
        $result = HTMLProcessor::resolve($phrases, $html, '<wbr>');
        $this->assertSame($this->wrap("<ul><li>abc</li>\n<li>def</li></ul>"), $result);
    }
}
