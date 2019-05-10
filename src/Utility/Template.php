<?php

namespace SilverStripe\Cow\Utility;

use Exception;
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
    public function renderTemplateWithContext(string $template, array $context): string
    {
        try {
            $twig = new Environment(new ArrayLoader(['template' => $template]), ['autoescape' => false]);

            return $twig->render('template', $context);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return '';
        }
    }
}
