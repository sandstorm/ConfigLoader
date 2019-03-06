# External Configuration Loader for Neos Flow

This package is capable of loading configuration of type Settings from external sources, 
such as Files. It can also reformat credentials that are e.g. stored in an env
variable as JSON so that it can be used by Flow. It is 100% extensible so you can
define your own Sources and Transformations.

## Using this package
This package was developed for the following use case: A Flow application needs to be run
on a VM at a cloud provider. This cloud provider stores environment information,
such as the database name and password, in a JSON-formatted environment variable.
Here's a short example:
```
{
  "databases": [
    {
      "label": "mariadb",
      "name": "cf-neos-db",
      "instance_name": "cf-neos-db",
      "credentials": {
        "hostname": "foo.example.com",
        "name": "DBNAME_ASDF1234",
        "username": "username",
        "password": "password"
      }
    }
  ]
}
```
Imagine this information is stored in an environment variable called `SERVICES`.
This package's task is to hook into the boot process of Flow very early, read
the env variable, transform the JSON into an associative array and inject it
into the regular configuration. Here is the configuration needed for this job:

```
Sandstorm:
  ConfigLoader:
    externalConfig:
      # This key can be chosen arbitrarily for each config source
      'MyJson':
        # The source's job is to read config from somewhere.
        # The EnvSource is capable of reading an environment variable.
        source: Sandstorm\ConfigLoader\Source\EnvSource
        sourceOptions:
          name: 'SERVICES'
        # A transformation transforms the value provided by source.
        # The JsonTransformation parses a JSON string and returns an
        # associative array that is stored by this package.
        transformation: Sandstorm\ConfigLoader\Transformation\JsonTransformation

# Now, you can inject the loaded transformation into the place where you want
# it to be. Use this format:
# %EXT:ExternalConfigKey.some.path%
Neos:
  Flow:
    persistence:
      backendOptions:
        host: '%EXT:MyJson.databases.0.credentials.hostname%'
        dbname: '%EXT:MyJson.databases.0.credentials.name%'
        user: '%EXT:MyJson.databases.0.credentials.username%'
        password: '%EXT:MyJson.databases.0.credentials.password%'
```

If you remove the caches and display the configuration, this is what
you would see. The credentials were parsed from the JSON and injected
into the config.
```
./flow configuration:show --path Neos.Flow.persistence.backendOptions
Configuration "Settings: Neos.Flow.persistence.backendOptions":

host: foo.example.com
dbname: DBNAME_ASDF1234
user: username
password: password
``` 

## Extensibility
It is extremely easy to create your own custom Sources and Transformations.
Just have them implement the `SourceInterface` / `TransformationInterface`
provided with this package, and configure them to be used like in the example
above. This way, you can load any configuration format (XML, JSON, ...) from
any source.

## TODOs
This package ignores Application context and injects the same values no matter
whether you are in Development or Production (or any other) context. This
is a missing feature.
