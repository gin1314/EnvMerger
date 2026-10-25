<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Enums;

enum LineType: string
{
    case VARIABLE = 'variable';
    case COMMENT = 'comment';
    case EMPTY = 'empty';
}
