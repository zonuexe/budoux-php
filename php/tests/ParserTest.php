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

#[CoversClass(Parser::class)]
class ParserTest extends TestCase
{
    public function testParse(): void
    {
        $model = [
            'UW4' => [
                'a' => 100,
            ],
        ];

        $parser = new Parser\File($model);

        $this->assertSame(['xyz', 'abc'], $parser->parse('xyzabc'));
    }

    public function testLoadDefaultJapaneseParser(): void
    {
        $parser = Parser::loadDefaultJapaneseParser();

        $expected = ['今日は', '天気です。'];
        $actual = $parser->parse("今日は天気です。");

        $this->assertEquals($expected, $actual);
    }

    public function testNewline(): void
    {
        $parser = Parser::loadDefaultJapaneseParser();
        $this->assertSame([" 1  \n  2 "], $parser->parse(" 1  \n  2 "));
    }

    public function testTranslateHTMLString(): void
    {
        $model = [
            'UW4' => [
                'a' => 100,
            ],
        ];
        $parser = new Parser\File($model);
        $html = '<a href="http://example.com">xyza</a>bc';
        $result = $parser->translateHTMLString($html);
        $this->assertSame(
            '<span style="word-break: keep-all; overflow-wrap: anywhere;"><a href="http://example.com">xyz' . "\u{200B}" . 'a</a>bc</span>',
            $result,
        );
    }

    public function testTranslateHTMLStringJapanese(): void
    {
        $parser = Parser::loadDefaultJapaneseParser();
        $result = $parser->translateHTMLString('今日は<b>とても天気</b>です。');
        $zwsp = "\u{200B}";
        $this->assertSame(
            '<span style="word-break: keep-all; overflow-wrap: anywhere;">'
            . '今日は<b>' . $zwsp . 'とても' . $zwsp . '天気</b>です。'
            . '</span>',
            $result,
        );
    }
}
