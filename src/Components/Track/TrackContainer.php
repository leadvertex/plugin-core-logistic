<?php

namespace Leadvertex\Plugin\Core\Logistic\Components\Track;

use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Exception\TrackContainerException;

class TrackContainer
{
    private static TrackLogisticStatusMapperInterface $mapper;

    /**
     * @param TrackLogisticStatusMapperInterface $mapper
     * @throws TrackContainerException
     */
    public function __construct(TrackLogisticStatusMapperInterface $mapper)
    {
        $this->checkRequiredLogisticStatusesCodes($mapper::map());
        self::$mapper = $mapper;
    }

    /**
     * @return TrackLogisticStatusMapperInterface
     * @throws TrackContainerException
     */
    public static function getMapper(): TrackLogisticStatusMapperInterface
    {
        if (!isset(self::$mapper)) {
            throw new TrackContainerException('Logistic Status Mapper handler was not configured', 500);
        }
        return self::$mapper;
    }

    /**
     * @param array $mapped
     * @return void
     * @throws TrackContainerException
     */
    private function checkRequiredLogisticStatusesCodes(array $mapped): void
    {
        $requiredCodes = [
            LogisticStatus::DELIVERED,
            LogisticStatus::PAID,
            LogisticStatus::DELIVERED_TO_SENDER,
            LogisticStatus::UNREGISTERED,
        ];

        $found = false;
        foreach ($mapped as $code) {
            if (!in_array($code, $requiredCodes)) continue;
            $found = true;
        }

        if ($found === false) {
            throw new TrackContainerException(
                'Not provided one of required codes of Logistic status: ' . implode(',', $requiredCodes),
                500,
            );
        }
    }

}