<?php

namespace SilverStripe\Cow\Tests\Utility;

use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Utility\ChangelogRenderer;

class ChangelogRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ChangelogRenderer
     */
    private $renderer;

    public function setUp()
    {
        parent::setUp();

        $this->renderer = new ChangelogRenderer();
    }

    public function testRenderChangelog()
    {
        $version = new Version('1.0.0');
        $logs = '(logs here)';

        $output = $this->renderer->renderChangelog($version, $logs);

        $this->assertContains('# 1.0.0', $output);
        $this->assertContains(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertContains($logs, $output);
        $this->assertContains(ChangelogRenderer::BOTTOM_DELIMITER, $output);
    }

    public function testRenderChangelogWithTemplate()
    {
        $template = "# {{ version }}\n\nBeforeLogs\n\n{{ logs }}\n\nAfterLogs";
        $version = new Version('1.0.0');
        $logs = '(logs here)';

        $output = $this->renderer->renderChangelogWithTemplate($template, $version, $logs);

        $this->assertContains('# 1.0.0', $output);
        $this->assertContains('BeforeLogs', $output);
        $this->assertContains(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertContains($logs, $output);
        $this->assertContains(ChangelogRenderer::BOTTOM_DELIMITER, $output);
        $this->assertContains('AfterLogs', $output);
    }

    public function testUpdateChangelog()
    {
        $template = "# {{ version }}\n\nBeforeLogs\n\n{{ logs }}\n\nAfterLogs";
        $version = new Version('1.0.0');
        $logs = '(logs here)';

        $existingContent = $this->renderer->renderChangelogWithTemplate($template, $version, $logs);

        $newLogs = '(new logs here)';

        $output = $this->renderer->updateChangelog($existingContent, $newLogs);

        $this->assertContains('# 1.0.0', $output);
        $this->assertContains('BeforeLogs', $output);
        $this->assertContains(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertNotContains($logs, $output);
        $this->assertContains($newLogs, $output);
        $this->assertContains(ChangelogRenderer::BOTTOM_DELIMITER, $output);
        $this->assertContains('AfterLogs', $output);
    }

    /**
     * Test behaviour when an existing changelog is missing delimiters for the logs (should append)
     */
    public function testUpdateChangelogWithoutDelimiters()
    {
        $existingContent = "# 1.0.0\n\nBeforeLogs\n\n(logs here)";
        $newLogs = '(new logs here)';

        $output = $this->renderer->updateChangelog($existingContent, $newLogs);

        $this->assertContains('# 1.0.0', $output);
        $this->assertContains('BeforeLogs', $output);
        $this->assertContains('(logs here)', $output);
        $this->assertContains(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertContains($newLogs, $output);
        $this->assertContains(ChangelogRenderer::BOTTOM_DELIMITER, $output);
    }
}
