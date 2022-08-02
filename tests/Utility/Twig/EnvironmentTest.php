<?php

namespace SilverStripe\Cow\Tests\Utility\Twig;

use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Utility\Twig\Environment;
use SilverStripe\Cow\Application;

/**
 * Rudimentary Twig functional tests
 */
class EnvironmentTest extends TestCase
{
    private $app;
    private $twig;

    public function setUp(): void
    {
        parent::setUp();

        $this->app = new Application();
        $this->twig = $this->app->createTwigEnvironment();
    }

    public function testTwigInitialization()
    {
        $this->assertNotNull($this->twig);
    }

    public function testTwigTemplateLookup()
    {
        $baseTplContent = $this->twig->render('@tests/base.twig');

        $this->assertEquals('base template', $baseTplContent);
    }
}
