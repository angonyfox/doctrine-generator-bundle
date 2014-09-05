<?
namespace Pandora\DoctrineGeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Pandora\DoctrineGeneratorBundle\Generator\DoctrineModelGenerator;
use Pandora\DoctrineGeneratorBundle\Tools\ModelGenerator;
use Pandora\DoctrineGeneratorBundle\Tools\InterfaceGenerator;
use Pandora\DoctrineGeneratorBundle\Tools\EntityGenerator;

class GenerateDoctrineModelsCommand extends GenerateDoctrineCommand
{
    protected function configure()
    {
        $help = <<<HELP
The <info>doctrine:generate:models</info> command generates model classes
from your mapping information:

* To a bundle:

  <info>php app/console doctrine:generate:models YourBundle</info>

* To a single model:

  <info>php app/console doctrine:generate:models YourBundle:User</info>
  <info>php app/console doctrine:generate:models Your/Bundle/Model/User</info>

* To a namespace:

  <info>php app/console doctrine:generate:models YourBundle/Model</info>

If the entities are not stored in a bundle, and if the classes do not exist,
the command has no way to guess where they should be generated. In this case,
you must provide the <comment>--path</comment> option:

  <info>php app/console doctrine:generate:models Your/Bundle/Model --path=src/</info>

By default, the unmodified version of each model is backed up and saved
(e.g. Product.php~). To prevent this task from creating the backup file,
pass the <comment>--no-backup</comment> option:

  <info>php app/console doctrine:generate:models Your/Bundle/Model --no-backup</info>

To generate or update the corresponding entity class, use the <comment>--with-entity</comment> option:

  <info>php app/console doctrine:generate:models YourBundle --with-entity</info>

To generate the corresponding interface class, use the <comment>--with-interface</comment> option:

  <info>php app/console doctrine:generate:models YourBundle --with-interface</info>

<error>Important:</error> Even if you specified Inheritance options in your
XML or YAML Mapping files the generator cannot generate the base and
child classes for you correctly, because it doesnot know which
class is supposed to extend which. You have to adjust the model
code manually for inheritance to work!

HELP;
        $this
            ->setName('doctrine:generate:models')
            ->setAliases(array('generate:doctrine:models'))
            ->setDescription('Generate model classes from your mapping information')
            ->addArgument('name', InputArgument::REQUIRED, 'A bundle name, a namespace, or a class name')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The path where to generate entities when it cannot be guessed')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing entities files.')
            ->addOption('with-repository', null, InputOption::VALUE_NONE, 'Whether to generate the entity repository or not')
            ->addOption('with-entity', null, InputOption::VALUE_NONE, 'Whether to generate the entity or not')
            ->addOption('with-interface', null, InputOption::VALUE_NONE, 'Whether to generate the interface or not')
            ->setHelp($help)
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));

        try {
            $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('name'));

            $output->writeln(sprintf('Generating models for bundle "<info>%s</info>"', $bundle->getName()));
            $metadata = $manager->getBundleMetadata($bundle);
        } catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');

            if (false !== $pos = strpos($name, ':')) {
                $name = $this->getContainer()->get('doctrine')->getAliasNamespace(substr($name, 0, $pos)).'\\'.substr($name, $pos + 1);
            }

            if (class_exists($name)) {
                $modelName = str_replace('\Entity', '\Model', $name);
                $output->writeln(sprintf('Generating model "<info>%s</info>"', $modelName));
                $metadata = $manager->getClassMetadata($name, $input->getOption('path'));
            } else {
                $output->writeln(sprintf('Generating models for namespace "<info>%s</info>"', $name));
                $entityNamespace = str_replace('\Model', '\Entity', $name);
                $metadata = $manager->getNamespaceMetadata($entityNamespace, $input->getOption('path'));
            }
        }

        $backupExisting = !$input->getOption('no-backup');
        $withRepository = $input->getOption('with-repository');
        $withInterface = $input->getOption('with-interface');
        $withEntity = $input->getOption('with-entity');

        $modelGenerator = $this->getModelGenerator();
        $modelGenerator->setBackupExisting($backupExisting);

        foreach ($metadata->getMetadata() as $m)
        {
            // Getting the metadata for the entity class once more to get the correct path if the namespace has multiple occurrences
            try {
                $entityMetadata = $manager->getClassMetadata($m->getName(), $input->getOption('path'));
            } catch (\RuntimeException $e) {
                // fall back to the bundle metadata when no entity class could be found
                $entityMetadata = $metadata;
            }

            $path = $entityMetadata->getPath();

            $modelMetadata = clone($m);
            $modelMetadata->name = str_replace('\Entity', '\Model', $modelMetadata->name);
            $modelMetadata->namespace = str_replace('\Entity', '\Model', $modelMetadata->namespace);
            $modelMetadata->rootEntityName = str_replace('\Entity', '\Model', $modelMetadata->rootEntityName);
            // var_dump($modelMetadata);

            if ($withInterface)
            {
                $modelGenerator->setClasstoImplement($modelMetadata->name."Interface");
                $interfaceMetadata = clone($modelMetadata);
                $interfaceMetadata->name .= "Interface";
                $interfaceMetadata->rootEntityName .= "Interface";
                $output->writeln(sprintf('  > generating <comment>%s</comment>', $interfaceMetadata->name));
                $interfaceGenerator = $this->getInterfaceGenerator();
                $interfaceGenerator->setBackupExisting($backupExisting);
                $interfaceGenerator->writeClass($interfaceMetadata, $path);
            }
            $output->writeln(sprintf('  > generating <comment>%s</comment>', $modelMetadata->name));
        //     $generator->generate(array($m), $entityMetadata->getPath());
            $modelGenerator->writeClass($modelMetadata, $path);

            if ($withRepository)
            {
                $repositoryClassName = $m->name.'Repository';
                $output->writeln(sprintf('  > generating <comment>%s</comment>', $repositoryClassName));
                $this->getRepositoryGenerator()->writeEntityRepositoryClass($repositoryClassName, $path);
            }
            if ($withEntity)
            {
                $entityMetadata = clone($m);
                $entityMetadata->setParentClasses(array($modelMetadata->name));
                $output->writeln(sprintf('  > generating <comment>%s</comment>', $entityMetadata->name));
                $entityGenerator = $this->getEntityGenerator();
                $entityGenerator->setBackupExisting($backupExisting);
                $entityGenerator->writeClass($entityMetadata, $path);
            }

        }
    }

    protected function createGenerator()
    {
        return null;
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
