<?php
/**
 * Created for plugin-core-logistic
 * Date: 09.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Components\Waybill;


use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\Exception\ShippingContainerException;

final class WaybillContainer
{

    /** @var callable */
    private static $form;

    private static WaybillHandlerInterface $handler;

    public static function __config(callable $form, WaybillHandlerInterface $handler): void
    {
        self::$form = $form;
        self::$handler = $handler;
    }

    /**
     * @return Form
     * @throws ShippingContainerException
     */
    public static function getForm(): Form
    {
        if (!isset(self::$form)) {
            throw new ShippingContainerException('Waybill form was not configured', 100);
        }

        return (self::$form)();
    }

    /**
     * @return WaybillHandlerInterface
     * @throws ShippingContainerException
     */
    public static function getHandler(): WaybillHandlerInterface
    {
        if (!isset(self::$handler)) {
            throw new ShippingContainerException('Waybill handler was not configured', 200);
        }

        return self::$handler;
    }

}