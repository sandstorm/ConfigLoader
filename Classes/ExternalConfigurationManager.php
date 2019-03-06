<?php
namespace Sandstorm\ConfigLoader;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Utility\Arrays;
use Neos\Utility\ObjectAccess;
use Sandstorm\ConfigLoader\Source\SourceInterface;
use Sandstorm\ConfigLoader\Transformation\TransformationInterface;

class ExternalConfigurationManager
{
    protected $externalConfiguration = [];

    /**
     * @param ConfigurationManager $configurationManager
     * @return void
     * @throws \Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException
     * @throws Exception
     */
    public function process(ConfigurationManager $configurationManager)
    {
        // TODO: Make sure this runs only once on boot

        $externalConfigurationConfig = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.ConfigLoader.externalConfig');

        // Just return if no external config needs to be loaded
        if ($externalConfigurationConfig === null) {
            return;
        }

        $this->loadExternalConfiguration($externalConfigurationConfig);
        $this->applyExternalConfiguration($configurationManager);
    }

    /**
     * Iterates through all external config Sources, triggers their load methods,
     * applys Transformations and stores the results.
     *
     * @param array $externalConfigurationConfig
     * @return void
     * @throws Exception
     */
    protected function loadExternalConfiguration(array $externalConfigurationConfig): void
    {
        foreach ($externalConfigurationConfig as $key => $externalConfigElement) {
            // Check if the class exists and implements the required interface
            $sourceClass = $externalConfigElement['source'];
            if (! class_exists($sourceClass) || ! in_array(SourceInterface::class, class_implements($sourceClass))) {
                throw new Exception(sprintf("The class %s does not exist or does not implement SourceInterface.", $sourceClass));
            }

            /** @var SourceInterface $source */
            $source = new $sourceClass();
            $sourceOptions = array_key_exists('sourceOptions', $externalConfigElement) ? $externalConfigElement['sourceOptions'] : null;
            $configuration = $source->load($sourceOptions);

            // If configured, apply the Transformation
            if (array_key_exists('transformation', $externalConfigElement)) {
                // Check if the class exists and implements the required interface
                $transformationClass = $externalConfigElement['transformation'];
                if (! class_exists($transformationClass) || ! in_array(TransformationInterface::class, class_implements($transformationClass))) {
                    throw new Exception(sprintf("The class %s does not exist or does not implement TransformationInterface.", $transformationClass));
                }

                /** @var TransformationInterface $transformation */
                $transformation = new $transformationClass();
                $configuration = $transformation->transform($configuration);
            }

            $this->externalConfiguration[$key] = $configuration;
        }
    }

    /**
     * Injects the loaded external configuration into the configuration manager and triggers a
     * cache refresh
     *
     * @param ConfigurationManager $configurationManager
     * @return void
     */
    protected function applyExternalConfiguration(ConfigurationManager $configurationManager): void
    {
        $configurations = ObjectAccess::getProperty($configurationManager, 'configurations', true);
        $configurations[ConfigurationManager::CONFIGURATION_TYPE_SETTINGS] = $this->processConfigurationLevel($configurations[ConfigurationManager::CONFIGURATION_TYPE_SETTINGS]);
        ObjectAccess::setProperty($configurationManager, 'unprocessedConfiguration', $configurations, true);
        $configurationManager->refreshConfiguration();
    }

    /**
     * Recursively parses the config and replaces values.
     *
     * @param array $config
     * @return array
     */
    protected function processConfigurationLevel(array $config)
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->processConfigurationLevel($value);
            }
            if (is_string($value)) {
                $externalConfig = $this->externalConfiguration;
                $config[$key] = preg_replace_callback('/%EXT:(.*)%/i', function ($matches) use ($externalConfig) {
                    return Arrays::getValueByPath($externalConfig, $matches[1]);
                }, $value);
            }
        }

        return $config;
    }
}
