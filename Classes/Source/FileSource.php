<?php
namespace Sandstorm\ConfigLoader\Source;

use Sandstorm\ConfigLoader\Exception;

/**
 * Configuration source that reads a file.
 */
class FileSource implements SourceInterface
{
    /**
     * @param array $sourceOptions
     * @return string
     * @throws Exception
     */
    public function load(array $sourceOptions): string
    {
        if (! array_key_exists('path', $sourceOptions)) {
            throw  new Exception("The FileSource expects the 'path' source option to be set.");
        }

        $configValue = file_get_contents($sourceOptions['path']);
        if ($configValue === false) {
            throw new Exception(sprintf("The contents of file %s could not be loaded as configuration.", $sourceOptions['path']));
        }
        return $configValue;
    }
}
