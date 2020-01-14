<?php

namespace SilverStripe\Cow\Utility;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

class Template
{
    /**
     * Renders a template string with Twig, applying the supplied variables
     *
     * @param $template
     * @param $context
     * @return string
     */
    public function renderTemplateStringWithContext(string $template, array $context): string
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]), ['autoescape' => false]);

        return $twig->render('template', $context);
    }
}
