<?php
/**
 * Created for plugin-core-logistic
 * Date: 09.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Logistic\Components\Waybill;


use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Form\FormData;
use Leadvertex\Plugin\Core\Logistic\Components\Waybill\Response\WaybillResponse;

interface WaybillHandlerInterface
{

    public function __invoke(Form $form, FormData $data): WaybillResponse;

}