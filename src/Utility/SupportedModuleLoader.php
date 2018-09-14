<?php

namespace SilverStripe\Cow\Utility;

class SupportedModuleLoader
{
    /**
     * @var FilterInterface
     */
    protected $filter;

    /**
     * The URL to retrieve data for supported modules from. The filename should be added to this.
     *
     * @var string
     */
    protected $baseUrl = 'https://raw.githubusercontent.com/silverstripe/supported-modules/gh-pages/';

    /**
     * Returns an array of supported modules, with the GitHub slug as the value and the Composer package
     * name as the key
     *
     * @return string[]
     */
    public function getModules()
    {
        $data = $this->getRemoteData('modules.json');
        $modules = json_decode($data, true) ?: [];

        if ($this->getFilter()) {
            $modules = $this->getFilter()->filter($modules);
        }

        return array_column($modules, 'github');
    }

    /**
     * Get the supported module labels configuration data
     *
     * @return array
     */
    public function getLabels()
    {
        $data = $this->getRemoteData('labels.json');
        return json_decode($data, true) ?: [];
    }

    /**
     * Returns the full URL to a filename on the supported modules repository
     *
     * @param string $filename
     * @return string
     */
    public function getFilePath($filename)
    {
        return $this->getBaseUrl() . ltrim($filename, '/');
    }

    /**
     * Gets data from the remote supported-modules repository by filename
     *
     * @param string $filename
     * @return string
     */
    protected function getRemoteData($filename)
    {
        $data = file_get_contents($this->getFilePath($filename));
        return $data ?: '';
    }

    /**
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function setFilter(FilterInterface $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter()
    {
        return $this->filter;
    }
}
