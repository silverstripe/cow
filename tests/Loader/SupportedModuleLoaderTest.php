<?php

namespace SilverStripe\Cow\Tests\Loader;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Loader\SupportedModuleLoader;

class SupportedModuleLoaderTest extends PHPUnit_Framework_TestCase
{
    public function testGetModules()
    {
        /** @var SupportedModuleLoader|PHPUnit_Framework_MockObject_MockObject $loader */
        $loader = $this->getMockBuilder(SupportedModuleLoader::class)
            ->setMethods(['getRemoteData'])
            ->getMock();

        $loader->expects($this->once())->method('getRemoteData')->willReturn(<<<JSON
[{
    "github": "silverstripe\/silverstripe-framework",
    "gitlab": null,
    "composer": "silverstripe\/framework",
    "scrutinizer": true,
    "addons": true,
    "type": "supported-module"
}, {
    "github": "silverstripe\/silverstripe-fulltextsearch",
    "gitlab": null,
    "composer": "silverstripe\/fulltextsearch",
    "scrutinizer": true,
    "addons": true,
    "type": "supported-module"
}]
JSON
        );

        $result = $loader->getModules();
        $this->assertContains('silverstripe/silverstripe-framework', $result, 'Supported modules are returned');
        $this->assertContains('silverstripe/silverstripe-fulltextsearch', $result, 'Supported modules are returned');
    }

    public function testGetLabels()
    {
        /** @var SupportedModuleLoader|PHPUnit_Framework_MockObject_MockObject $loader */
        $loader = $this->getMockBuilder(SupportedModuleLoader::class)
            ->setMethods(['getRemoteData'])
            ->getMock();

        $loader->expects($this->once())->method('getRemoteData')->willReturn(<<<JSON
{
  "default_labels": {
    "affects/v3": "ff0000"
  },
  "rename_labels": {
    "bug": "type/bug"
  },
  "remove_labels": [
    "good first issue"
  ]
}
JSON
        );

        $result = $loader->getLabels();
        $this->assertNotEmpty($result['default_labels']);
        $this->assertSame('ff0000', $result['default_labels']['affects/v3']);

        $this->assertNotEmpty($result['rename_labels']);
        $this->assertSame('type/bug', $result['rename_labels']['bug']);

        $this->assertNotEmpty($result['remove_labels']);
        $this->assertSame('good first issue', reset($result['remove_labels']));
    }

    public function testBrokenApiResponses()
    {
        /** @var SupportedModuleLoader|PHPUnit_Framework_MockObject_MockObject $loader */
        $loader = $this->getMockBuilder(SupportedModuleLoader::class)
            ->setMethods(['getRemoteData'])
            ->getMock();

        $loader->expects($this->exactly(2))->method('getRemoteData')->willReturn(false);

        $this->assertSame([], $loader->getModules(), 'Broken HTTP response still returns an empty array');
        $this->assertSame([], $loader->getLabels(), 'Broken HTTP response still returns an empty array');
    }

    public function testGetFilePath()
    {
        $loader = new SupportedModuleLoader();

        $this->assertSame(
            'https://raw.githubusercontent.com/silverstripe/supported-modules/gh-pages/modules.json',
            $loader->getFilePath('modules.json')
        );
        $this->assertSame(
            'https://raw.githubusercontent.com/silverstripe/supported-modules/gh-pages/labels.json',
            $loader->getFilePath('/labels.json')
        );
    }
}
