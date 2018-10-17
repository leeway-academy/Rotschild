<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/17/18
 * Time: 8:39 AM
 */

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChequesController
{
    /**
     * @Route(name="cargar_cheques_propios",path="/cargarChequesPropios")
     */
    public function cargarChequesPropios( Request $request )
    {
        if ( 'Banco' !== $request->get('entity') ) {

            throw new InvalidArgumentException();
        }

        if ( empty( $request->get('id') ) ) {

            throw new InvalidArgumentException();
        }

        $em = $this->getDoctrine()->getManager();
        $repository = $this->getDoctrine()->getRepository('App:Banco');

        $id = $request->query->get('id');
        $banco = $repository->find($id);

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
            /** @todo Procesar upload */
            $file = $form['File']->getData();
            $spreadsheet = IOFactory::load( $file );

            $xlsStructure = $banco->getXLSStructure();
            $shit = $spreadsheet->getActiveSheet();
            $row = $xlsStructure['firstRow'];
            $dateContents = $shit->getCellByColumnAndRow( $xlsStructure['dateCol'], $row )->getValue();
            while ( ( !empty( $xlsStructure['stopWord'] ) && substr( $dateContents, 0, strlen( $xlsStructure['stopWord'] ) ) !== $xlsStructure['stopWord'] ) || ( empty($xlsStructure['stopWord'] ) && !empty( $dateContents ) ) ) {
                $date = \DateTime::createFromFormat( $xlsStructure['dateFormat'], $dateContents );
                $amount = $shit->getCellByColumnAndRow( $xlsStructure['amountCol'], $row )->getValue();
                $concept = $shit->getCellByColumnAndRow( $xlsStructure['conceptCol'], $row )->getValue();
                $transaction = new Movimiento();
                $transaction->setBanco( $banco );
                $transaction->setImporte( $amount );
                $transaction->setFecha( $date );
                $transaction->setConcepto( $concept );

                $em->persist( $transaction );
                $row++;
                $dateContents = $shit->getCellByColumnAndRow( $xlsStructure['dateCol'], $row )->getValue();
            }

            $em->flush();
        }

        return $this->render(
            'admin/cargar_extracto.html.twig',
            [
                'form' => $form->createView(),
                'banco' => $banco->getNombre(),
            ]
        );
    }
}