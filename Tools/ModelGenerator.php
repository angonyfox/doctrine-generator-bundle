<?php
namespace Pandora\DoctrineGeneratorBundle\Tools;

use Doctrine\ORM\Tools\EntityGenerator as Generator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class ModelGenerator extends Generator
{
    /**
     * The class all generated models should implement.
     *
     * @var string
     */
    protected $classToImplement;

    public function __construct()
    {
        parent::__construct();
        $this->setFieldVisibility(self::FIELD_VISIBLE_PROTECTED);
    }

    /**
     * Generates a PHP5 Doctrine 2 model class from the given ClassMetadataInfo instance.
     *
     * @param ClassMetadataInfo $metadata
     *
     * @return string
     */
    public function generateModelClass(ClassMetadataInfo $metadata)
    {
        return $this->generateEntityClass($metadata);
    }

    protected function generateEntityClassName(ClassMetadataInfo $metadata)
    {
        return 'abstract class ' . $this->getClassName($metadata)
          .($this->extendsClass() ? ' extends ' . $this->getClassToExtendName() : null)
          .($this->implementsClass() ? ' implements ' . $this->getClassToImplementName() : null)
        ;
    }

    /**
     * @return bool
     */
    protected function implementsClass()
    {
        return $this->classToImplement ? true : false;
    }

    /**
     * @return string
     */
    protected function getClassToImplement()
    {
        return $this->classToImplement;
    }

    /**
     * Sets the name of the class the generated classes should implement from.
     *
     * @param string $classToExtend
     *
     * @return void
     */
    public function setClassToImplement($classToImplement)
    {
        $this->classToImplement = $classToImplement;
    }

    /**
     * @return string
     */
    protected function getClassToImplementName()
    {
        $refl = new \ReflectionClass($this->getClassToImplement());

        // return '\\' . $refl->getName();
        return $refl->getShortName();
    }

}
