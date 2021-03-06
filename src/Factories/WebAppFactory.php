<?php
/**
 * Created for plugin-core-logistic
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Factories;


use Leadvertex\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerAction;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use Slim\App;

class WebAppFactory extends \Leadvertex\Plugin\Core\Factories\WebAppFactory
{

    public function build(): App
    {
        $this
            ->addCors()
            ->addBatchActions()
            ->addForm(
                'waybill',
                fn() => WaybillContainer::getForm(),
                new WaybillHandlerAction()
            );

        return parent::build();
    }

}