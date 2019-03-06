<?php
namespace Sandstorm\ConfigLoader\Source;

use Sandstorm\ConfigLoader\Exception;

/**
 * Returns an environment variable.
 */
class EnvSource implements SourceInterface
{
    /**
     * @param array $sourceOptions
     * @return string
     * @throws Exception
     */
    public function load(array $sourceOptions): string
    {
        if (! array_key_exists('name', $sourceOptions)) {
            throw new Exception("The EnvSource expects the 'name' source option to be set.");
        }

        return getenv($sourceOptions['name']);
    }
}
