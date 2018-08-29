<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 8/21/18
 * Time: 6:13 PM
 */

namespace App\Command;

use App\Entity\Movimiento;
use App\Entity\GastoFijo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Doctrine\ORM\EntityManagerInterface;

class CopyFixedExpensesCommand extends ContainerAwareCommand
{
    private $em;

    public function __construct(?string $name = null, EntityManagerInterface $em)
    {
        parent::__construct($name);

        $this->em = $em;
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:copy-fixed-expenses')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates new concrete instances of fixed expenses for the upcoming month.')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command creates new expenses based on the fixed expenses templates');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->em;
        $firstDay = new \DateTimeImmutable('First day of next month');

        foreach ($em->getRepository('App:GastoFijo')->findAll() as $gastoFijo) {
            $fecha = $firstDay->add(new \DateInterval('P' . $gastoFijo->getDia() . 'D'));
            $output->writeln('Creando gasto de "' . $gastoFijo->getConcepto() . '", dia ' . $fecha->format('d/m/Y'));
            $debito = new Movimiento();
            $debito->setClonDe($gastoFijo);
            $debito->setFecha($fecha);
            $debito->setConcepto($gastoFijo->getConcepto());
            $debito->setImporte($gastoFijo->getImporte() * -1);
            $debito->setBanco($gastoFijo->getBanco());
            $em->persist($debito);
        }

        $em->flush();
    }
}
