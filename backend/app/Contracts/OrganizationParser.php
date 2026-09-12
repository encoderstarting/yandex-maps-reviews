<?php

namespace App\Contracts;

use App\Data\ParsedOrganization;
use Closure;

interface OrganizationParser
{
    /** @param null|Closure(int, int, int): void $onProgress */
    public function parse(string $url, ?Closure $onProgress = null): ParsedOrganization;
}
