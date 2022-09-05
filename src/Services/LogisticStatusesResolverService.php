<?php

namespace Leadvertex\Plugin\Core\Logistic\Services;

use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;

class LogisticStatusesResolverService
{
    private Track $track;

    public function __construct(Track $track)
    {
        $this->track = $track;
    }

    public function getLastStatusForNotify(): ?LogisticStatus
    {
        $statuses = $this->sort();
        foreach ($statuses as $i => $status) {
            if ($this->isSentNotify($status)) {
                continue;
            }

            //проверяем есть ли статусы дальше, если есть значит почта проставила статус задним числом и его мы не отправляем
            $nextStatusKey = $i + 1;
            if (array_key_exists($nextStatusKey, $statuses)) {
                continue;
            }

            return $status;
        }
        return null;
    }


    /**
     * @return LogisticStatus[]
     */
    private function sort(): array
    {
        $result = [];
        foreach (LogisticStatus::values() as $code) {
            $codeStatuses = array_filter($this->track->getStatuses(), fn(LogisticStatus $status) => $status->getCode() === $code);
            usort($codeStatuses, function (LogisticStatus $status_1, LogisticStatus $status_2) {
                if ($status_1->getTimestamp() === $status_2->getTimestamp()) {
                    return 0;
                }
                return ($status_1->getTimestamp() < $status_2->getTimestamp()) ? -1 : 1;
            });
            $result = array_merge($result, $codeStatuses);
        }
        return $result;
    }

    private function isSentNotify(LogisticStatus $status): bool
    {
        return in_array($status->getHash(), $this->track->getNotificationsHashes());
    }

}