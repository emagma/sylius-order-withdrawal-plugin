<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->import('vendor/sylius-labs/coding-standard/ecs.php');

    $config->parallel();
    $config->paths(['src', 'spec', 'tests']);
    $config->skip([
        '**/var/*',
        'src/Migrations/*',
    ]);

    // License file header, enforced (and auto-fixed) on every PHP file. Edit the wording or
    // copyright holder here in one place; `ecs check --fix` then adds/repairs it everywhere.
    $header = <<<'HEADER'
        This file is part of the Emagma SyliusOrderWithdrawal Plugin.
        (c) Emagma
        For the full copyright and license information, please view the LICENSE
        file that was distributed with this source code.
        HEADER;

    $config->ruleWithConfiguration(HeaderCommentFixer::class, [
        'header' => $header,
        'comment_type' => 'comment',
        'location' => 'after_open',
        'separate' => 'both',
    ]);
};
