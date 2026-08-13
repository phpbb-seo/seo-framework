<?php
declare(strict_types=1);

namespace phpbbseo\framework\Url;

interface SlugGeneratorInterface
{
    public function generate(string $text): string;
}
