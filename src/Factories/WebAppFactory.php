<?php
/**
 * Created for plugin-core-logistic
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Factories;


use Leadvertex\Plugin\Core\Logistic\Components\Actions\Shipping\Exception\ShippingContainerException;
use Leadvertex\Plugin\Core\Logistic\Components\Actions\Shipping\ShippingContainer;
use Leadvertex\Plugin\Core\Logistic\Components\Actions\Track\TrackGetStatusesAction;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\WaybillContainer;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\WaybillHandlerAction;
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

        $this->app
            ->get('/protected/track/statuses/{trackNumber:[A-z\d\-_]{6,25}}', TrackGetStatusesAction::class)
            ->add($this->protected);

        try {
            $this
                ->addCors()
                ->addSpecialRequestAction(ShippingContainer::getShippingCancelAction());
        } catch (ShippingContainerException $e) {
        }

        return parent::build();
    }

}