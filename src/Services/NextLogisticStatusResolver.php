<?php

namespace Leadvertex\Plugin\Core\Logistic\Services;

use Leadvertex\Plugin\Components\Logistic\LogisticStatus;
use Leadvertex\Plugin\Core\Logistic\Components\Track\Track;

class NextLogisticStatusResolver
{
    private Track $track;

    public function __construct(Track $track)
    {
        $this->track = $track;
    }

    public function getNextLogisticStatus(): ?LogisticStatus
    {
        //если отправление удалено или утеряно, то мы сразу отсылаем данный статус
        $unregisteredStatus = $this->getLastLogisticStatus(LogisticStatus::UNREGISTERED);
        if ($this->isNeedToSendLogisticStatus($unregisteredStatus)) {
            return $unregisteredStatus;
        } elseif ($this->isSentNotification($unregisteredStatus)) {
            return null;
        }

        /**
         * В зависимости от того отправляем с cod или нет мы считаем разный конечный статус,
         * если мы нашли конечный статус вручения, то сразу отправляем его или просто идем дальше
         */
        if ($this->track->isCod()) {
            $paidStatus = $this->getLastLogisticStatus(LogisticStatus::PAID);
            if ($this->isNeedToSendLogisticStatus($paidStatus)) {
                return $paidStatus;
            } elseif ($this->isSentNotification($paidStatus)) {
                return null;
            }
        } else {
            $deliveredStatus = $this->getLastLogisticStatus(LogisticStatus::DELIVERED);
            if ($this->isNeedToSendLogisticStatus($deliveredStatus)) {
                return $deliveredStatus;
            } elseif ($this->isSentNotification($deliveredStatus)) {
                return null;
            }
        }

        /**
         * Если мы нашли статус возврата, то мы можем в дальнейшем отдать только 2 варианта:
         * @see LogisticStatus::RETURNING_TO_SENDER
         * @see LogisticStatus::DELIVERED_TO_SENDER
         */
        $returnStatus = $this->getLastLogisticStatus(LogisticStatus::RETURNED);
        //если не отправляли возврат, то отсылаем
        if ($this->isNeedToSendLogisticStatus($returnStatus)) {
            return $returnStatus;
        }

        //Если нашли возврат и отправляли, то отправляем иначе ищем наши конечные статусы возврата
        if ($this->isSentNotification($returnStatus)) {
            //Если пришел конечный статус возврата, то отправляем его
            $deliveredToSender = $this->getLastLogisticStatus(LogisticStatus::DELIVERED_TO_SENDER);
            if ($this->isNeedToSendLogisticStatus($deliveredToSender)) {
                return $deliveredToSender;
            } elseif ($this->isSentNotification($deliveredToSender)) {
                return null;
            }

            /**
             * Далее просто отправляем все новые статусы с кодом @see LogisticStatus::RETURNING_TO_SENDER
             * но оставляем оригинальный текст статуса и его время
             */
            $lastStatus = $this->getLastLogisticStatus();
            if ($lastStatus !== null && $lastStatus->getHash() != $returnStatus->getHash()) {
                $lastStatus = new LogisticStatus(
                    LogisticStatus::RETURNING_TO_SENDER,
                    $lastStatus->getText(),
                    $lastStatus->getTimestamp(),
                );
                if (!$this->isSentNotification($lastStatus) && $this->isNeedToSendLogisticStatus($lastStatus)) {
                    return $lastStatus;
                }
            }

            return null;
        }

        //прибыло
        $arrivedStatus = $this->getLastLogisticStatus(LogisticStatus::ARRIVED);
        if ($this->isNeedToSendLogisticStatus($arrivedStatus)) {
            return $arrivedStatus;
        } elseif ($this->isSentNotification($arrivedStatus)) {
            return null;
        }

        //принято
        $acceptedStatus = $this->getLastLogisticStatus(LogisticStatus::ACCEPTED);
        if ($this->isNeedToSendLogisticStatus($acceptedStatus)) {
            return $acceptedStatus;
        } elseif ($this->isSentNotification($acceptedStatus)) {
            return null;
        }

        //в пути
        $acceptedStatus = $this->getLastLogisticStatus(LogisticStatus::IN_TRANSIT);
        if ($this->isNeedToSendLogisticStatus($acceptedStatus)) {
            return $acceptedStatus;
        } elseif ($this->isSentNotification($acceptedStatus)) {
            return null;
        }

        //если ничего не нашли, то просто ищем последний актуальный статус и отправляем его
        $lastStatus = $this->getLastLogisticStatus();
        if ($this->isNeedToSendLogisticStatus($lastStatus)) {
            return $lastStatus;
        }

        return null;
    }

    protected function getLastLogisticStatus(?int $code = null): ?LogisticStatus
    {
        if ($code === null) {
            $filter = function () {
                return true;
            };
        } else {
            $filter = function (LogisticStatus $status) use ($code) {
                return $status->getCode() === $code;
            };
        }

        $statuses = array_filter($this->track->getStatuses(), $filter);

        if (empty($statuses)) {
            return null;
        }

        usort($statuses, function (LogisticStatus $status_1, LogisticStatus $status_2) {
            if ($status_1->getTimestamp() === $status_2->getTimestamp()) {
                return 0;
            }
            return ($status_1->getTimestamp() > $status_2->getTimestamp()) ? -1 : 1;
        });

        $last = reset($statuses);
        return $last === false ? null : $last;
    }

    protected function isNeedToSendLogisticStatus(?LogisticStatus $status): bool
    {
        return $status !== null && !in_array($status->getHash(), $this->track->getNotificationsHashes());
    }

    private function isSentNotification(?LogisticStatus $status): bool
    {
        return $status !== null && in_array($status->getHash(), $this->track->getNotificationsHashes());
    }


}