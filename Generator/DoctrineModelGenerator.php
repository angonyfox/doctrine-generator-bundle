<?
namespace Pandora\DoctrineGeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Pandora\DoctrineGeneratorBundle\Tools\ModelGenerator;
use Pandora\DoctrineGeneratorBundle\Tools\InterfaceGenerator;
use Pandora\DoctrineGeneratorBundle\Tools\EntityGenerator;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;

class DoctrineModelGenerator extends Generator
{
    private $filesystem;
    private $registry;

    public function __construct(Filesystem $filesystem, RegistryInterface $registry)
    {
        $this->filesystem = $filesystem;
        $this->registry = $registry;
    }

    public function generate(BundleInterface $bundle, $model, $format, array $fields, $withRepository, $withInterface, $withEntity)
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getManager(null)->getConfiguration();
        $config->setEntityNamespaces(array_merge(
            array($bundle->getName() => $bundle->getNamespace().'\\Model'),
            $config->getEntityNamespaces()
        ));
        $path = $bundle->getPath().str_repeat('/..', substr_count(get_class($bundle), '\\'));

        $entityClassName = $this->registry->getAliasNamespace($bundle->getName()).'\\'.$model;
        $modelNamespace = str_replace('\Entity', '\Model', $this->registry->getAliasNamespace($bundle->getName()));
        $modelClass = $modelNamespace.'\\'.$model;
        $modelPath = $bundle->getPath().'/Model/'.str_replace('\\', '/', $model).'.php';
        // if (file_exists($modelPath)) {
        //     throw new \RuntimeException(sprintf('Model "%s" already exists.', $modelClass));
        // }

        $class = new ClassMetadataInfo($modelClass);
        if ($withRepository) {
            $class->customRepositoryClassName = $entityClassName.'Repository';
        }
        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);
        foreach ($fields as $field) {
            $class->mapField($field);
        }

        $modelGenerator = $this->getModelGenerator();
        if ($withInterface)
        {
            $modelGenerator->setClasstoImplement($modelClass."Interface");
            $interfaceClass = clone($class);
            $interfaceClass->name .= "Interface";
            $interfaceClass->rootEntityName .= "Interface";
            $this->getInterfaceGenerator()->writeClass($interfaceClass, $path);
        }

        $entityClass = new ClassMetadataInfo($entityClassName);
        $entityClass->setParentClasses(array($modelClass));

        if ('annotation' === $format) {
            $modelGenerator->setGenerateAnnotations(true);
            $modelCode = $modelGenerator->generateModelClass($class);
            $mappingPath = $mappingCode = false;
        } else {
            $cme = new ClassMetadataExporter();
            $exporter = $cme->getExporter('yml' == $format ? 'yaml' : $format);
            $mappingPath = $bundle->getPath().'/Resources/config/doctrine/'.str_replace('\\', '.', $model).'.orm.'.$format;

            if (file_exists($mappingPath)) {
                throw new \RuntimeException(sprintf('Cannot generate entity when mapping "%s" already exists.', $mappingPath));
            }

            $entityClass->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
            $entityClass->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);
            foreach ($fields as $field) {
                $entityClass->mapField($field);
            }

            $mappingCode = $exporter->exportClassMetadata($entityClass);
            $modelGenerator->setGenerateAnnotations(false);
            $modelCode = $modelGenerator->generateModelClass($class);
        }

        $this->filesystem->mkdir(dirname($modelPath));
        file_put_contents($modelPath, $modelCode);

        if ($mappingPath) {
            $this->filesystem->mkdir(dirname($mappingPath));
            file_put_contents($mappingPath, $mappingCode);
        }

        if ($withRepository)
        {
            $this->getRepositoryGenerator()->writeEntityRepositoryClass($class->customRepositoryClassName, $path);
        }
        if ($withEntity)
        {
            $this->getEntityGenerator()->writeClass($entityClass, $path);
        }
    }

    public function isReservedKeyword($keyword)
    {
        return $this->registry->getConnection()->getDatabasePlatform()->getReservedKeywordsList()->isKeyword($keyword);
    }

    protected function getModelGenerator()
    {
        $modelGenerator = new ModelGenerator();
        $modelGenerator->setGenerateAnnotations(false);
        $modelGenerator->setGenerateStubMethods(true);
        $modelGenerator->setRegenerateEntityIfExists(false);
        $modelGenerator->setUpdateEntityIfExists(true);
        $modelGenerator->setNumSpaces(4);
        $modelGenerator->setAnnotationPrefix('ORM\\');

        return $modelGenerator;
    }

    protected function getRepositoryGenerator()
    {
        return new EntityRepositoryGenerator();
    }

    protected function getInterfaceGenerator()
    {
        $interfaceGenerator = new InterfaceGenerator();
        $interfaceGenerator->setGenerateStubMethods(true);
        $interfaceGenerator->setRegenerateEntityIfExists(false);
        $interfaceGenerator->setUpdateEntityIfExists(true);
        $interfaceGenerator->setNumSpaces(4);
        return $interfaceGenerator;
    }

    protected function getEntityGenerator()
    {
        $entityGenerator = new EntityGenerator();
        $entityGenerator->setGenerateStubMethods(false);
        $entityGenerator->setRegenerateEntityIfExists(false);
        $entityGenerator->setUpdateEntityIfExists(true);
        $entityGenerator->setNumSpaces(4);
        return $entityGenerator;
    }

}
