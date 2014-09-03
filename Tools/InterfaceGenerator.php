<?php
namespace Pandora\DoctrineGeneratorBundle\Tools;

use Doctrine\ORM\Tools\EntityGenerator as Generator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Util\Inflector;
use Doctrine\DBAL\Types\Type;

class InterfaceGenerator extends Generator
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

    protected static $getMethodTemplate =
'/**
 * <description>
 *
 * @return <variableType>
 */
public function <methodName>();';

    /**
     * @var string
     */
    protected static $setMethodTemplate =
'/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return <entity>
 */
public function <methodName>(<methodTypeHint>$<variableName><variableDefault>);';

    /**
     * @var string
     */
    protected static $addMethodTemplate =
'/**
 * <description>
 *
 * @param <variableType>$<variableName>
 * @return <entity>
 */
public function <methodName>(<methodTypeHint>$<variableName>);';

    /**
     * @var string
     */
    protected static $removeMethodTemplate =
'/**
 * <description>
 *
 * @param <variableType>$<variableName>
 */
public function <methodName>(<methodTypeHint>$<variableName>);';

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

    protected function generateEntityClassName(ClassMetadataInfo $metadata)
    {
        return 'interface ' . $this->getClassName($metadata);
    }

    protected function generateEntityBody(ClassMetadataInfo $metadata)
    {
        $stubMethods = $this->generateEntityStubMethods ? $this->generateEntityStubMethods($metadata) : null;

        $code = array();

        if ($stubMethods) {
            $code[] = $stubMethods;
        }

        return implode("\n", $code);
    }

    protected function generateEntityStubMethod(ClassMetadataInfo $metadata, $type, $fieldName, $typeHint = null,  $defaultValue = null)
    {
        $methodName = $type . Inflector::classify($fieldName);
        if (in_array($type, array("add", "remove"))) {
            $methodName = Inflector::singularize($methodName);
        }

        if ($this->hasMethod($methodName, $metadata)) {
            return '';
        }
        $this->staticReflection[$metadata->name]['methods'][] = $methodName;

        $var = sprintf('%sMethodTemplate', $type);
        $template = static::$$var;

        $methodTypeHint = null;
        $types          = Type::getTypesMap();
        $variableType   = $typeHint ? $this->getType($typeHint) . ' ' : null;

        if ($typeHint && ! isset($types[$typeHint])) {
            $variableType   =  '\\' . ltrim($variableType, '\\');
            $methodTypeHint =  '\\' . $typeHint . ' ';
        }

        $replacements = array(
          '<description>'       => ucfirst($type) . ' ' . $fieldName,
          '<methodTypeHint>'    => $methodTypeHint,
          '<variableType>'      => $variableType,
          '<variableName>'      => Inflector::camelize($fieldName),
          '<methodName>'        => $methodName,
          '<fieldName>'         => $fieldName,
          '<variableDefault>'   => ($defaultValue !== null ) ? (' = '.$defaultValue) : '',
          '<entity>'            => $this->getClassName($metadata)
        );

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        return $this->prefixCodeWithSpaces($method);
    }

    public function writeClass(ClassMetadataInfo $metadata, $outputDirectory)
    {
        $this->writeEntityClass($metadata, $outputDirectory);
    }

}
