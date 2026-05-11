<?php

namespace App\Enums;

enum ContentType: string
{
    case Live = 'live';
    case Vod = 'vod';
    case Series = 'series';
}
