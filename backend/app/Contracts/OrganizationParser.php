<?php

namespace App\Contracts;

use App\Data\ParsedOrganization;

interface OrganizationParser
{
    public function parse(string $url): ParsedOrganization;
}
