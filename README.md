Symfony2 bundle to generate model or interface class

Installation
------------

1. Add bundle as dependency to the composer.json
``` json
{
    "require": {
        "pandora/doctrine-generator-bundle": "dev-master"
    }
}
```
2. Run "composer update"
3. Make sure to enable PandoraDoctrineGeneratorBundle in AppKernel.php .
``` php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Pandora\DoctrineGeneratorBundle\PandoraDoctrineGeneratorBundle(),
    );
}
```
