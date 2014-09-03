<?
namespace Pandora\DoctrineGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateDoctrineModelsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $help = <<<HELP
The <info>doctrine:generate:models</info> command generates model classes
from your mapping information:

  <info>php app/console doctrine:generate:models YourBundle</info>

HELP;
        $this
            ->setName('doctrine:generate:models')
            ->setAliases(array('generate:doctrine:models'))
            ->setDescription('Generate model classes from your mapping information')
            // ->addArgument('bundle', null, 'A bundle name')
            // ->addOption('without-listeners', null, InputOption::VALUE_OPTIONAL, '', true)
            ->setHelp($help)
            ->addArgument('name', InputArgument::OPTIONAL, 'Who do you want to greet?')
            ->addOption('yell', null, InputOption::VALUE_NONE, 'If set, the task will yell in uppercase letters')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        if ($name) {
            $text = 'Hello '.$name;
        } else {
            $text = 'Hello';
        }

        if ($input->getOption('yell')) {
            $text = strtoupper($text);
        }

        $output->writeln($text);
    }
}
