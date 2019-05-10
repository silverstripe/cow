<?php

namespace SilverStripe\Cow\Utility;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

class Template
{
    /**
     * Renders a template string with Twig, applying the supplied variables
     *
     * @param $template
     * @param $context
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderTemplateWithContext($template, $context)
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]));

        return $twig->render('template', $context);
    }
}
