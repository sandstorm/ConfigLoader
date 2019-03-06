<?php
namespace Sandstorm\ConfigLoader\Source;

use Sandstorm\ConfigLoader\Exception;

interface SourceInterface
{
    /**
     * @param array $sourceOptions
     * @return string
     * @throws Exception
     */
    public function load(array $sourceOptions): string;
}
