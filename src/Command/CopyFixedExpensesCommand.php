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
use Doctrine\Common\Collections\Criteria;
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
        $criteria = new Criteria();
        $criteria
            ->andWhere( Criteria::expr()->neq('fechaFin', null ) )
            ;

        $twelveMonths = new \DateInterval('P12M');
        $dayNumberToday = date('d');
        foreach ($em->getRepository('App:GastoFijo')->matching( $criteria ) as $gastoFijo) {
            $newDate = (new \DateTimeImmutable())->add($twelveMonths);

            if ( ($d = $gastoFijo->getDia()) > $dayNumberToday) {
                $newDate = $newDate->sub( new \DateInterval('P'.($d - $dayNumberToday).'D' ) );
            } elseif ( $d < $dayNumberToday ) {
                $newDate = $newDate->add( new \DateInterval('P'.($dayNumberToday - $d).'D' ) );
            }

            if ( ($debit = $em->getRepository('App:Movimiento')->findBy(
                [
                    'clonDe' => $gastoFijo,
                    'fecha' => $newDate,
                ]
            ) ) === null ) {
                $output->writeln('Creando gasto de "' . $gastoFijo->getConcepto() . '", dia ' . $newDate->format('d/m/Y'));
                $debito = new Movimiento();
                $debito
                    ->setClonDe($gastoFijo)
                    ->setFecha($newDate)
                    ->setConcepto($gastoFijo->getConcepto())
                    ->setImporte($gastoFijo->getImporte() * -1)
                    ->setBanco($gastoFijo->getBanco())
                ;
                $em->persist($debito);
            } else {
                $output->writeln('Gasto de "' . $gastoFijo->getConcepto() . '", dia ' . $newDate->format('d/m/Y').' pre-existente');
            }
        }

        $em->flush();
    }
}
