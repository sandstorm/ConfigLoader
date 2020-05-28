<?php
namespace Sandstorm\ConfigLoader;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Utility\Arrays;
use Neos\Utility\ObjectAccess;
use Sandstorm\ConfigLoader\Source\SourceInterface;
use Sandstorm\ConfigLoader\Transformation\TransformationInterface;

class ExternalConfigurationManager
{
    const EXTERNAL_CONFIGURATION_PATTERN = '/%EXT:(.*)%/i';

    protected $externalConfiguration = [];

    /**
     * @param ConfigurationManager $configurationManager
     * @return void
     * @throws \Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException
     * @throws Exception
     */
    public function process(ConfigurationManager $configurationManager)
    {
        $externalConfigurationConfig = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.ConfigLoader.externalConfig');

        // Just return if no external config needs to be loaded
        if ($externalConfigurationConfig === null) {
            return;
        }

        // only load and process external configuration if directive existent
        $settings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS);
        if ($this->hasExternalConfigurationDirective($settings)) {
            $this->loadExternalConfiguration($externalConfigurationConfig);
            $this->applyExternalConfiguration($configurationManager);
        }
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

                $transformationOptions = array_key_exists('transformationOptions', $externalConfigElement) ? $externalConfigElement['transformationOptions'] : null;

                /** @var TransformationInterface $transformation */
                $transformation = new $transformationClass();
                if (is_array($transformationOptions)) {
                    $transformation->setOptions($transformationOptions);
                }
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
        // clear config cache to be able to load unprocessed configuration values such as env vars '%env:MY_ENV_VAR%%'
        $configurationManager->flushConfigurationCache();

        // load all available configuration (unprocessed)
        $configurationManager->warmup();

        // get unprocessed settings from configuration
        $unprocessedSettings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS);

        // replace external configuration values
        $rawConfigurations = ObjectAccess::getProperty($configurationManager, 'configurations', true);
        $rawConfigurations[ConfigurationManager::CONFIGURATION_TYPE_SETTINGS] = $this->processConfigurationLevel($unprocessedSettings);

        // process configuration by ConfigurationManager
        ObjectAccess::setProperty($configurationManager, 'unprocessedConfiguration', $rawConfigurations, true);
        $configurationManager->refreshConfiguration();
    }

    /**
     * Recursively parses the config and replaces values.
     *
     * @param array $config
     * @return array
     */
    protected function processConfigurationLevel(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = $this->processConfigurationLevel($value);
            }
            if (is_string($value)) {
                $externalConfig = $this->externalConfiguration;
                $config[$key] = preg_replace_callback(self::EXTERNAL_CONFIGURATION_PATTERN, function ($matches) use ($externalConfig) {
                    return Arrays::getValueByPath($externalConfig, $matches[1]);
                }, $value);
            }
        }

        return $config;
    }

    /**
     * Recursively parses the config and check for configuration directive
     *
     * @param array $config
     * @return bool
     */
    protected function hasExternalConfigurationDirective(array $config): bool
    {
        $hasDirective = false;
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $hasDirective = $this->hasExternalConfigurationDirective($value);
                if ($hasDirective) {
                    break;
                }
            }
            if (is_string($value)) {
                $result = preg_match(self::EXTERNAL_CONFIGURATION_PATTERN, $value);
                $hasDirective = $result === 1;
                if ($hasDirective) {
                    break;
                }
            }
        }

        return $hasDirective;
    }
}
