<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Actions\Shipping;

use Leadvertex\Plugin\Core\Actions\SpecialRequestAction;

abstract class ShippingCancelAction extends SpecialRequestAction
{
    final public function getName(): string
    {
        return 'shippingCancel';
    }
}