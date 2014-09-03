<?php
namespace Pandora\DoctrineGeneratorBundle\Tools;

use Doctrine\ORM\Tools\EntityGenerator as Generator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class EntityGenerator extends Generator
{
    protected static $classTemplate =
'<?php
<namespace>

<entityAnnotation>
<entityClassName>
{
<entityBody>
}
';

    public function generateEntityClass(ClassMetadataInfo $metadata)
    {
        $placeHolders = array(
            '<namespace>',
            '<entityAnnotation>',
            '<entityClassName>',
            '<entityBody>'
        );

        $replacements = array(
            $this->generateEntityNamespace($metadata),
            $this->generateEntityDocBlock($metadata),
            $this->generateEntityClassName($metadata),
            $this->generateEntityBody($metadata)
        );

        $code = str_replace($placeHolders, $replacements, static::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    public function writeClass(ClassMetadataInfo $metadata, $outputDirectory)
    {
        $this->setClassToExtend($metadata->rootEntityName);
        $this->writeEntityClass($metadata, $outputDirectory);
    }

}
