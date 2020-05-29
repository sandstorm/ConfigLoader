<?php
namespace Sandstorm\ConfigLoader\Transformation;

use Sandstorm\ConfigLoader\Exception;

/**
 * Reads a raw JSON string and returns it as an associative array.
 */
class JsonTransformation implements TransformationInterface
{
    public function transform($rawConfiguration)
    {
        return json_decode($rawConfiguration, true);
    }

    public function setOptions(array $options): void
    {
    }
}
