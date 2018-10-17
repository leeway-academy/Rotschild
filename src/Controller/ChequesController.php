<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/17/18
 * Time: 8:39 AM
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Movimiento;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ChequesController extends Controller
{
    /**
     * @Route(name="cargar_cheques_propios",path="/cargarChequesPropios")
     */
    public function cargarChequesPropios( Request $request )
    {
        $em = $this->getDoctrine()->getManager();

        $form = $this
            ->createFormBuilder( null, [ 'data_class' => null ] )
            ->setAttribute('class', 'form-horizontal new-form')
            ->add(
                'File',
                FileType::class,
                [
                    'label' => 'Archivo',
                    'required' => true,
                ]
            )
            ->add('Subir', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ( $form->isSubmitted() && $form->isValid() ) {
            $file = $form['File']->getData();
            $spreadsheet = IOFactory::load( $file );

            $shit = $spreadsheet->getActiveSheet();
            $row = 2;
            $checkNbr = $shit->getCellByColumnAndRow( 1, $row )->getValue();
            if ( ( $bank = $em->getRepository('App:Banco')->findOneBy(['codigo' => $shit->getCellByColumnAndRow( 7, $row )->getValue()] ) ) === null ) {
                // @todo Fill in with error handling code

                return;
            }
            while ( !empty( $checkNbr ) ) {
                $transaction = new Movimiento();
                $transaction->setBanco( $bank );
                $transaction->setImporte( $shit->getCellByColumnAndRow( 8, $row )->getValue() * -1 );
                $value = $shit->getCellByColumnAndRow(3, $row)->getValue();
                $transaction->setFecha( \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value) );
                $transaction->setConcepto( 'Cheque '.$shit->getCellByColumnAndRow( 2, $row )->getValue() . ' ('.$shit->getCellByColumnAndRow( 5, $row )->getValue().')' );

                $em->persist( $transaction );
                $row++;
                $checkNbr = $shit->getCellByColumnAndRow( 1, $row )->getValue();
            }

            $em->flush();
        }

        return $this->render(
            'admin/cargar_cheques_propios.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }
}