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

    public function generateUpdatedEntityClass(ClassMetadataInfo $metadata, $path)
    {
        $currentCode = file_get_contents($path);

        $placeHolders = array(
            '<namespace>',
            '<entityAnnotation>',
            '<entityClassName>'
        );

        $replacements = array(
            $this->generateEntityNamespace($metadata),
            $this->generateEntityDocBlock($metadata),
            $this->generateEntityClassName($metadata)
        );
        $modifiedTemplate = preg_replace("/\{(\s*.*\s*)\}\s*/i", "{", static::$classTemplate);

        $code = str_replace($placeHolders, $replacements, $modifiedTemplate);

        $first = strrpos($currentCode, '{');

        return $code.substr($currentCode, $first+1);
    }

    protected function generateEntityNamespace(ClassMetadataInfo $metadata)
    {
        $lines = array();
        if ($this->hasNamespace($metadata))
        {
            $lines[] = 'namespace ' . $this->getNamespace($metadata) .';';
        }
        if ($this->extendsClass())
        {
            $extendClassName = ($pos = strrpos($this->getClassToExtendName(), '\\')) ? substr($this->getClassToExtendName(), $pos+1, strlen($this->getClassToExtendName())) : $this->getClassToExtendName();
            $lines[] = "";
            $lines[] = 'use '.ltrim($this->getClassToExtendName(), '\\').' as Base'.$extendClassName.';';
        }
        return implode("\n", $lines);
    }

    protected function generateEntityClassName(ClassMetadataInfo $metadata)
    {
        if ($this->extendsClass())
        {
            $extendClassName = ($pos = strrpos($this->getClassToExtendName(), '\\')) ? 'Base'.substr($this->getClassToExtendName(), $pos+1, strlen($this->getClassToExtendName())) : $this->getClassToExtendName();
        }
        return 'class ' . $this->getClassName($metadata) .
            ($this->extendsClass() ? ' extends ' . $extendClassName : null);
    }

    public function writeClass(ClassMetadataInfo $metadata, $outputDirectory)
    {
        $this->setClassToExtend($metadata->rootEntityName);
        $this->writeEntityClass($metadata, $outputDirectory);
    }

}
