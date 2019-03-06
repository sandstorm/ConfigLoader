<?php
namespace Sandstorm\ConfigLoader\Transformation;

interface TransformationInterface
{
    /**
     * Expects raw configuration in an arbitrary format, depending on the Transformation's
     * capability - e.g. would expect a raw JSON string for a JsonTransformation.
     * Returns an associative array or a scalar value that is stored as a result
     * of this Transformation.
     *
     * @param $rawConfiguration
     * @return mixed
     */
    public function transform($rawConfiguration);
}
