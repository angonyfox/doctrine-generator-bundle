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
Available Commands
------------------

* use doctrine:generate:model or generate:doctrine:model to generate single model class
* use doctrine:generate:models or generate:doctrine:models to generate model in bundle

Usage
-----

### doctrine:generate:model

```
php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post
```

The above command would initialize a new model in the following model namespace
*Acme\BlogBundle\Model\Blog\Post*

You can also optionally spectify the fields you want to generate in the new model:

```
php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --fields="title:string(255) body:text"
```

The command can also generate the corresponding entity repository class with the
*--with-repository* option:

```
php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --with-repository
```

By default, the command uses annotations for the mapping information; change it
with *--format*:

```
php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --format=yml
```

To generate or update the corresponding entity class, use the *--with-entity* option:

```
php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --with-entity
```

To generate the corresponding interface class, use the *--with-interface* option:

```
php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --with-interface
```

### doctrine:generate:models

* To a bundle:

```
php app/console doctrine:generate:models YourBundle
```

* To a single model:

```
php app/console doctrine:generate:models YourBundle:User
php app/console doctrine:generate:models Your/Bundle/Model/User
```

* To a namespace:

```
php app/console doctrine:generate:models YourBundle/Model
```

If the entities are not stored in a bundle, and if the classes do not exist,
the command has no way to guess where they should be generated. In this case,
you must provide the *--path* option:


```
php app/console doctrine:generate:models Your/Bundle/Model --path=src/
```

By default, the unmodified version of each model is backed up and saved
(e.g. Product.php~). To prevent this task from creating the backup file,
pass the *--no-backup* option:

```
php app/console doctrine:generate:models Your/Bundle/Model --no-backup
```

To generate or update the corresponding entity class, use the *--with-entity* option:

```
php app/console doctrine:generate:models YourBundle --with-entity
```

To generate the corresponding interface class, use the *--with-interface* option:

```
php app/console doctrine:generate:models YourBundle --with-interface
```

**Important:** Even if you specified Inheritance options in your
XML or YAML Mapping files the generator cannot generate the base and
child classes for you correctly, because it doesnot know which
class is supposed to extend which. You have to adjust the model
code manually for inheritance to work!
