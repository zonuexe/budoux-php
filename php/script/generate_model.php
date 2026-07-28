#!/usr/bin/env php
<?php

declare(strict_types=1);

const FILES = [
    'Japanese' => 'ja.json',
    'SimplifiedChinese' => 'zh-hans.json',
    'TraditionalChinese' => 'zh-hant.json',
    'Thai' => 'th.json',
];

const ParserNameSpace = 'Budoux\\parser\\';

$dest_dir = realpath(__DIR__ . "/../Parser");
assert($dest_dir !== false);

foreach (FILES as $class => $file):
    /** @var array<string, array<string|int, int>> $model */
    $model = json_decode(file_get_contents(__DIR__ . "/../../budoux/models/{$file}") ?: '', true);
    $dest = "{$dest_dir}/{$class}.php";

    $totalScore = array_sum(array_map(array_sum(...), $model));

    ob_start();
    echo '<?php';
?>


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

namespace Budoux\Parser;

use Budoux\Parser;

final class <?= $class ?> extends Parser
{
    public function getTotalScore(): int
    {
        return <?= $totalScore ?>;
    }

    protected function getModel(): array
    {
        return self::MODEL;
    }

private const MODEL = [
<?php
foreach ($model as $key => $chars): ?>
'<?= $key ?>' => [
<?php foreach ($chars as $char => $score): ?><?= is_int($char) ? $char : "'" . addcslashes($char, "'") . "'" ?>=><?= $score ?>,<?php endforeach ?>

],
<?php endforeach ?>
];
}
<?php
    $code = ob_get_clean();
    assert($code !== false);
    echo "write {$dest} ...";
    file_put_contents($dest, $code);
    echo " done;", PHP_EOL;

endforeach;
