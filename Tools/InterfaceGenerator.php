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

    public function writeClass(ClassMetadataInfo $metadata, $outputDirectory)
    {
        $this->writeEntityClass($metadata, $outputDirectory);
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

    protected function parseTokensInEntityFile($src)
    {
        $tokens = token_get_all($src);
        $lastSeenNamespace = "";
        $lastSeenClass = false;

        $inNamespace = false;
        $inClass = false;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) {
                continue;
            }

            if ($inNamespace) {
                if ($token[0] == T_NS_SEPARATOR || $token[0] == T_STRING) {
                    $lastSeenNamespace .= $token[1];
                } elseif (is_string($token) && in_array($token, array(';', '{'))) {
                    $inNamespace = false;
                }
            }

            if ($inClass) {
                $inClass = false;
                $lastSeenClass = $lastSeenNamespace . ($lastSeenNamespace ? '\\' : '') . $token[1];
                $this->staticReflection[$lastSeenClass]['properties'] = array();
                $this->staticReflection[$lastSeenClass]['methods'] = array();
            }

            if ($token[0] == T_NAMESPACE) {
                $lastSeenNamespace = "";
                $inNamespace = true;
            } elseif (in_array($token[0], array(T_CLASS, T_INTERFACE))) {
                $inClass = true;
            } elseif ($token[0] == T_FUNCTION) {
                if ($tokens[$i+2][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i+2][1];
                } elseif ($tokens[$i+2] == "&" && $tokens[$i+3][0] == T_STRING) {
                    $this->staticReflection[$lastSeenClass]['methods'][] = $tokens[$i+3][1];
                }
            } elseif (in_array($token[0], array(T_VAR, T_PUBLIC, T_PRIVATE, T_PROTECTED)) && $tokens[$i+2][0] != T_FUNCTION) {
                $this->staticReflection[$lastSeenClass]['properties'][] = substr($tokens[$i+2][1], 1);
            }
        }
    }
}
