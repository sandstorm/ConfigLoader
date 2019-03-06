<?php
namespace Sandstorm\ConfigLoader\Source;

use Sandstorm\ConfigLoader\Exception;

/**
 * Simply returns the provided value in the sourceOptions.
 */
class RawSource implements SourceInterface
{
    /**
     * @param array $sourceOptions
     * @return string
     * @throws Exception
     */
    public function load(array $sourceOptions): string
    {
        if (! array_key_exists('value', $sourceOptions)) {
            throw new Exception("The RawSource expects the 'value' source option to be set.");
        }

        return $sourceOptions['value'];
    }
}
