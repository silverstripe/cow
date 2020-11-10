<?php

declare(strict_types=1);

namespace SilverStripe\Cow\Utility\Twig;

use Twig\Loader\FilesystemLoader;
use SilverStripe\Cow\Application;

class Loader extends FilesystemLoader
{
    public function __construct(Application $cow)
    {
        $templateDir = $cow->getTwigTemplateDir();

        parent::__construct(['cow'], $templateDir);
        $this->setPaths(['tests'], 'tests');
    }
}
