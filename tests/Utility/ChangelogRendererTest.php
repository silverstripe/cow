<?php

namespace SilverStripe\Cow\Tests\Utility;

use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Utility\ChangelogRenderer;

class ChangelogRendererTest extends TestCase
{
    /**
     * @var ChangelogRenderer
     */
    private $renderer;

    public function setUp(): void
    {
        parent::setUp();

        $this->renderer = new ChangelogRenderer();
    }

    public function testRenderChangelog()
    {
        $version = new Version('1.0.0');
        $logs = '(logs here)';

        $output = $this->renderer->renderChangelog($version, $logs);

        $this->assertStringContainsString('# 1.0.0', $output);
        $this->assertStringContainsString(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertStringContainsString($logs, $output);
        $this->assertStringContainsString(ChangelogRenderer::BOTTOM_DELIMITER, $output);
    }

    public function testRenderChangelogWithTemplate()
    {
        $template = "# {{ version }}\n\nBeforeLogs\n\n{{ logs }}\n\nAfterLogs";
        $version = new Version('1.0.0');
        $logs = '(logs here)';

        $output = $this->renderer->renderChangelogWithTemplate($template, $version, $logs);

        $this->assertStringContainsString('# 1.0.0', $output);
        $this->assertStringContainsString('BeforeLogs', $output);
        $this->assertStringContainsString(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertStringContainsString($logs, $output);
        $this->assertStringContainsString(ChangelogRenderer::BOTTOM_DELIMITER, $output);
        $this->assertStringContainsString('AfterLogs', $output);
    }

    public function testUpdateChangelog()
    {
        $template = "# {{ version }}\n\nBeforeLogs\n\n{{ logs }}\n\nAfterLogs";
        $version = new Version('1.0.0');
        $logs = '(logs here)';

        $existingContent = $this->renderer->renderChangelogWithTemplate($template, $version, $logs);

        $newLogs = '(new logs here)';

        $output = $this->renderer->updateChangelog($existingContent, $newLogs);

        $this->assertStringContainsString('# 1.0.0', $output);
        $this->assertStringContainsString('BeforeLogs', $output);
        $this->assertStringContainsString(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertStringNotContainsString($logs, $output);
        $this->assertStringContainsString($newLogs, $output);
        $this->assertStringContainsString(ChangelogRenderer::BOTTOM_DELIMITER, $output);
        $this->assertStringContainsString('AfterLogs', $output);
    }

    /**
     * Test behaviour when an existing changelog is missing delimiters for the logs (should append)
     */
    public function testUpdateChangelogWithoutDelimiters()
    {
        $existingContent = "# 1.0.0\n\nBeforeLogs\n\n(logs here)";
        $newLogs = '(new logs here)';

        $output = $this->renderer->updateChangelog($existingContent, $newLogs);

        $this->assertStringContainsString('# 1.0.0', $output);
        $this->assertStringContainsString('BeforeLogs', $output);
        $this->assertStringContainsString('(logs here)', $output);
        $this->assertStringContainsString(ChangelogRenderer::TOP_DELIMITER, $output);
        $this->assertStringContainsString($newLogs, $output);
        $this->assertStringContainsString(ChangelogRenderer::BOTTOM_DELIMITER, $output);
    }
}
