<?php

namespace SilverStripe\Cow\Utility;

use SilverStripe\Cow\Model\Release\Version;

/**
 * This service is responsible for (re-)rendering a complete changelog using
 * a precompiled log of changes, and against a template if provided.
 *
 * @package SilverStripe\Cow\Utility
 */
class ChangelogRenderer
{
    /**
     * Below this line, any text will be automatically regenerated
     */
    public const TOP_DELIMITER = '<!--- Changes below this line will be automatically regenerated -->';

    /**
     * Above this line, any text will be automatically regenerated
     */
    public const BOTTOM_DELIMITER = '<!--- Changes above this line will be automatically regenerated -->';

    /**
     * This prevents certain linting rules from being run against the commits
     */
    public const SKIP_LINTING = '<!-- markdownlint-disable proper-names enhanced-proper-names -->';

    /**
     * Renders a basic changelog, with a version title and the provided logs.
     *
     * @param Version $version
     * @param string $logs
     * @return string
     */
    public function renderChangelog(Version $version, string $logs)
    {
        $logs = $this->delimitLogs($logs);

        return sprintf(
            "# %s\n\n%s",
            $version->getValue(),
            $logs
        );
    }

    /**
     * Renders a changelog using the provided template, which can access the logs and version information.
     *
     * @param string $template A Twig-compatible, Markdown-formatted template
     * @param Version $version
     * @param string $logs
     * @return string
     */
    public function renderChangelogWithTemplate(string $template, Version $version, string $logs): string
    {
        $logs = $this->delimitLogs($logs);

        return (new Template())->renderTemplateStringWithContext(
            $template,
            [
                'logs' => $logs,
                'version' => $version,
            ]
        );
    }

    /**
     * Replaces the logs in an existing changelog using the delimiters applied during initial render.
     *
     * @param string $existingChangelog
     * @param string $newLogs
     * @return string
     */
    public function updateChangelog(string $existingChangelog, string $newLogs): string
    {
        $topDelimiterPos = strpos($existingChangelog, self::TOP_DELIMITER);
        $bottomDelimiterPos = strpos($existingChangelog, self::BOTTOM_DELIMITER);

        // If the top delimiter doesn't exist, fall back to appending the logs
        if ($topDelimiterPos === false) {
            return $existingChangelog
                . self::TOP_DELIMITER
                . "\n"
                . self::SKIP_LINTING
                . $newLogs
                . self::BOTTOM_DELIMITER;
        }

        // Extract the content preceding the logs (including the top delimiter itself)
        $beforeLogs = substr($existingChangelog, 0, $topDelimiterPos + strlen(self::TOP_DELIMITER));

        // If the bottom delimiter doesn't exist, add it - otherwise extract it and everything beyond it
        $afterLogs = ($bottomDelimiterPos === false)
            ? self::BOTTOM_DELIMITER
            : substr($existingChangelog, $bottomDelimiterPos);

        return implode([
            $beforeLogs . "\n" . self::SKIP_LINTING,
            "\n\n",
            $newLogs,
            $afterLogs
        ]);
    }

    /**
     * Wraps logs in delimiters so they can be updated later.
     *
     * @param string $logs
     * @return string
     */
    private function delimitLogs(string $logs): string
    {
        return implode("\n\n", [
            self::TOP_DELIMITER . "\n" . self::SKIP_LINTING,
            $logs,
            self::BOTTOM_DELIMITER
        ]);
    }
}
