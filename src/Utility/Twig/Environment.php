<?php

declare(strict_types=1);

namespace SilverStripe\Cow\Utility\Twig;

use Twig\Loader\LoaderInterface;
use Twig;

class Environment extends Twig\Environment
{
    public function __construct(LoaderInterface $loader, array $options = [])
    {
        if (!isset($options['cache'])) {
            $options['cache'] = implode(
                DIRECTORY_SEPARATOR,
                [
                    sys_get_temp_dir(),
                    'cow',
                    'twig',
                    'cache'
                ]
            );
        }

        parent::__construct($loader, $options);
    }
}
