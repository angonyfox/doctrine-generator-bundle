<?
namespace Pandora\DoctrineGeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Pandora\DoctrineGeneratorBundle\Generator\DoctrineModelGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\DBAL\Types\Type;

class GenerateDoctrineModelCommand extends GenerateDoctrineCommand
{
    protected function configure()
    {
        $help = <<<HELP
The <info>doctrine:generate:model</info> command generates model class
from your mapping information:

  <info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post</info>

The above command would initialize a new model in the following model namespace
<info>Acme\BlogBundle\Model\Blog\Post</info>

You can also optionally spectify the fields you want to generate in the new model:

  <info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --fields="title:string(255) body:text"</info>

The command can also generate the corresponding entity repository class with the
<comment>--with-repository</comment> option:

<info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --with-repository</info>

By default, the command uses annotations for the mapping information; change it
with <comment>--format</comment>:

<info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --format=yml</info>

To generate or update the corresponding entity class, use the `<comment>--with-entity</comment>` option:
<info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --with-entity</info>

To generate the corresponding interface class, use the `<comment>--with-interface</comment>` option:
<info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --with-interface</info>

To deactivate the interaction mode, simply use the `<comment>--no-interaction</comment>` option
without forgetting to pass all needed options:

<info>php app/console doctrine:generate:model --model=AcmeBlogBundle:Blog/Post --format=annotation --fields="title:string(255) body:text" --with-repository --no-interaction</info>

HELP;
        $this
          ->setName('doctrine:generate:model')
          ->setAliases(array('generate:doctrine:model'))
          ->setDescription('Generate model classes from your mapping information')
          ->addOption('model', null, InputOption::VALUE_REQUIRED, 'The model class name to initialize (shortcut notation)')
          ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'The fields to create with the new model')
          ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Use the format for configuration files (php, xml, yml, or annotation)', 'annotation')
          ->addOption('with-repository', null, InputOption::VALUE_NONE, 'Whether to generate the entity repository or not')
          ->addOption('with-entity', null, InputOption::VALUE_NONE, 'Whether to generate the entity or not')
          ->addOption('with-interface', null, InputOption::VALUE_NONE, 'Whether to generate the interface or not')
          ->setHelp($help)
        ;
    }

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle/MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();

        // if ($input->isInteractive()) {
        //     if (!$dialog->askConfirmation($output, $dialog->getQuestion('Do you confirm generation', 'yes', '?'), true)) {
        //         $output->writeln('<error>Command aborted</error>');
        //
        //         return 1;
        //     }
        // }

        $model = Validators::validateEntityName($input->getOption('model'));
        list($bundle, $model) = $this->parseShortcutNotation($model);
        $format = Validators::validateFormat($input->getOption('format'));
        $fields = $this->parseFields($input->getOption('fields'));

        $dialog->writeSection($output, 'Model generation');

        $bundle = $this->getContainer()->get('kernel')->getBundle($bundle);

        $generator = $this->getGenerator();
        $generator->generate($bundle, $model, $format, array_values($fields), $input->getOption('with-repository'), $input->getOption('with-interface'), $input->getOption('with-entity'));
        $output->writeln('Generating the model code: <info>OK</info>');

        $dialog->writeGeneratorSummary($output, array());
    }

    private function parseFields($input)
    {
        if (is_array($input)) {
            return $input;
        }

        $fields = array();
        foreach (explode(' ', $input) as $value) {
            $elements = explode(':', $value);
            $name = $elements[0];
            if (strlen($name)) {
                $type = isset($elements[1]) ? $elements[1] : 'string';
                preg_match_all('/(.*)\((.*)\)/', $type, $matches);
                $type = isset($matches[1][0]) ? $matches[1][0] : $type;
                $length = isset($matches[2][0]) ? $matches[2][0] : null;

                $fields[$name] = array('fieldName' => $name, 'type' => $type, 'length' => $length);
            }
        }

        return $fields;
    }

    private function addFields(InputInterface $input, OutputInterface $output, DialogHelper $dialog)
    {
        $fields = $this->parseFields($input->getOption('fields'));
        $output->writeln(array(
            '',
            'Instead of starting with a blank entity, you can add some fields now.',
            'Note that the primary key will be added automatically (named <comment>id</comment>).',
            '',
        ));
        $output->write('<info>Available types:</info> ');

        $types = array_keys(Type::getTypesMap());
        $count = 20;
        foreach ($types as $i => $type) {
            if ($count > 50) {
                $count = 0;
                $output->writeln('');
            }
            $count += strlen($type);
            $output->write(sprintf('<comment>%s</comment>', $type));
            if (count($types) != $i + 1) {
                $output->write(', ');
            } else {
                $output->write('.');
            }
        }
        $output->writeln('');

        $fieldValidator = function ($type) use ($types) {
            // FIXME: take into account user-defined field types
            if (!in_array($type, $types)) {
                throw new \InvalidArgumentException(sprintf('Invalid type "%s".', $type));
            }

            return $type;
        };

        $lengthValidator = function ($length) {
            if (!$length) {
                return $length;
            }

            $result = filter_var($length, FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1)
            ));

            if (false === $result) {
                throw new \InvalidArgumentException(sprintf('Invalid length "%s".', $length));
            }

            return $length;
        };

        while (true) {
            $output->writeln('');
            $generator = $this->getGenerator();
            $columnName = $dialog->askAndValidate($output, $dialog->getQuestion('New field name (press <return> to stop adding fields)', null), function ($name) use ($fields, $generator) {
                if (isset($fields[$name]) || 'id' == $name) {
                    throw new \InvalidArgumentException(sprintf('Field "%s" is already defined.', $name));
                }

                // check reserved words
                if ($generator->isReservedKeyword($name)){
                    throw new \InvalidArgumentException(sprintf('Name "%s" is a reserved word.', $name));
                }

                return $name;
            });
            if (!$columnName) {
                break;
            }

            $defaultType = 'string';

            // try to guess the type by the column name prefix/suffix
            if (substr($columnName, -3) == '_at') {
                $defaultType = 'datetime';
            } elseif (substr($columnName, -3) == '_id') {
                $defaultType = 'integer';
            } elseif (substr($columnName, 0, 3) == 'is_') {
                $defaultType = 'boolean';
            } elseif (substr($columnName, 0, 4) == 'has_') {
                $defaultType = 'boolean';
            }

            $type = $dialog->askAndValidate($output, $dialog->getQuestion('Field type', $defaultType), $fieldValidator, false, $defaultType, $types);

            $data = array('columnName' => $columnName, 'fieldName' => lcfirst(Container::camelize($columnName)), 'type' => $type);

            if ($type == 'string') {
                $data['length'] = $dialog->askAndValidate($output, $dialog->getQuestion('Field length', 255), $lengthValidator, false, 255);
            }

            $fields[$columnName] = $data;
        }

        return $fields;
    }

    protected function createGenerator()
    {
        return new DoctrineModelGenerator($this->getContainer()->get('filesystem'), $this->getContainer()->get('doctrine'));
    }
}
