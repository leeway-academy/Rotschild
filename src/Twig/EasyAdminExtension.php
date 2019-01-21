<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 1/21/19
 * Time: 3:31 PM
 */

namespace App\Twig;

use App\Entity\Movimiento;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class EasyAdminExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
                new TwigFilter( 'filter_admin_actions', [ $this, 'filterActions'] ),
            ];
    }

    public function filterActions( array $itemActions, $item )
    {
        if ( $item instanceof Movimiento ) {
            if ( $item->isDebit() ) {
                if ( $item->isConcretado() ) {
                    unset($itemActions['delete']);
                    unset($itemActions['edit']);
                    $itemActions['undo'] = [
                        'name' => 'undoDebit',
                        'type' => 'method',
                        'label' => 'action.undo',
                        'title' => null,
                        'css_class' => 'text-danger',
                        'icon' => null,
                        'target' => '_self',
                    ];
                } elseif ( $item->getParentIssuedCheck() ) {
                    unset($itemActions['delete']);
                    unset($itemActions['edit']);
                }
            } elseif ( $item->isCredit() ) {
                if ( $item->isConcretado() ) {
                    unset($itemActions['delete']);
                    unset($itemActions['edit']);
                    $itemActions['undo'] = [
                        'name' => 'undoCredit',
                        'type' => 'method',
                        'label' => 'action.undo',
                        'title' => null,
                        'css_class' => 'text-danger',
                        'icon' => null,
                        'target' => '_self',
                    ];
                }
            }
        }

        return $itemActions;
    }
}