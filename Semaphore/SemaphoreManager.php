<?php
namespace Kitpages\SemaphoreBundle\Semaphore;

use Kitpages\SemaphoreBundle\Transaction\TransactionManagerInterface;
use Kitpages\SemaphoreBundle\Transaction\Transaction;
use Kitpages\SemaphoreBundle\Transaction\TransactionRow;

use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;

/**
 * @inheritdoc
 */
class SemaphoreManager
    implements SemaphoreManagerInterface
{
    /** @var TransactionManagerInterface */
    protected $transactionManager;

    /** @var int */
    protected $sleepTimeMicroseconds;

    /** @var  int */
    protected $deadlockMicroseconds;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var  Stopwatch */
    protected $stopwatch;

    public function __construct(
        TransactionManagerInterface $transactionManager,
        $sleepTimeMicroseconds,
        $deadLockMicroseconds,
        LoggerInterface $logger,
        Stopwatch $stopwatch = null
    )
    {
        $this->transactionManager = $transactionManager;
        $this->sleepTimeMicroseconds = $sleepTimeMicroseconds;
        $this->deadlockMicroseconds = $deadLockMicroseconds;
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * @param string $semaphoreName
     */
    public function aquire($semaphoreName)
    {
        $this->stopwatchStart('aquire_requested');
        $uid = $this->createUid($semaphoreName);
        $pid = getmypid();
        $this->logger->debug("[$pid] acquire requested, semaphoreName=$semaphoreName, uid=$uid");

        $loopCount = 0;

        while (true) {
            $transaction = $this->transactionManager->beginTransaction($semaphoreName);
            $this->cleanUpDeadLockInTransaction($transaction, $loopCount);

            // if not aquired and fifo empty or next, semaphore is acquired
            if (
                ($transaction->getAquiredRow() === null) &&
                (
                    ($transaction->fifoIsEmpty() === true) ||
                    ($transaction->readNextFromFifo() === $uid)
                )
            ) {
                $transaction->removeNextFromFifo();
                $transaction->setAquiredRow(
                    new TransactionRow($uid, $this->microSecondsTime())
                );
                $this->transactionManager->commit($transaction);
                $pid = getmypid();
                $this->logger->debug("[$pid] aquire obtained, loopCount=$loopCount, semaphoreName=$semaphoreName, uid=$uid");
                $this->stopwatchStop('aquire_requested');
                $this->stopwatchStart('aquire_obtained');
                return;
            }
            // acquired refused
            // add $uid in fifo
            $transaction->addToFifoIfNotPresent($uid);
            // save and close transaction
            $this->transactionManager->commit($transaction);
            // sleep for pooling time
            $loopCount ++;
            usleep($this->sleepTimeMicroseconds);
        }
    }

    /**
     * @param string $semaphoreName
     */
    public function release($semaphoreName)
    {
        $pid = getmypid();
        $this->logger->debug("[$pid] release start, semaphoreName=$semaphoreName");
        $now = new \DateTime;
        $transaction = $this->transactionManager->beginTransaction($semaphoreName);
        if ($transaction->getAquiredRow() === null) {
            $backtraceList = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = $backtraceList[0];
            $now = new \DateTime();
            $this->logger->warning("[$pid] Realease requested, but semaphore not locked, at ".$now->format(DATE_RFC2822)." in ".$backtrace["file"].'('.$backtrace["line"].')');
        }
        $uid = $transaction->getAquiredRow()->getUid();
        $transaction->setAquiredRow(null);
        $this->transactionManager->commit($transaction);
        $this->logger->debug("[$pid] release ok, semaphoreName=$semaphoreName, uid=$uid");
        $this->stopwatchStop('aquire_obtained');
        return;

    }

    protected function createUid($semaphoreName)
    {
        return $semaphoreName.'-'.uniqid();
    }

    protected function cleanUpDeadLockInTransaction(Transaction $transaction, $loopCount)
    {

        $microTime = $this->microSecondsTime();
        $row = $transaction->getAquiredRow();

        // deadlock detected => log and remove aquiredRow
        if ( ($row instanceof Transaction) &&  ($microTime >= $row->getMicrotime() + $this->deadlockMicroseconds)) {

            $backtraceList = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = $backtraceList[1];
            $pid = getmypid();
            $now = new \DateTime();
            $this->logger->warning(
                sprintf(
                    '[%s] Dead lock detected, semaphore=%s ; uid=%s ; loopCount=%s , at %s in %s (%s)',
                    $pid,
                    $transaction->getSemaphoreName(),
                    $row->getUid(),
                    $loopCount,
                    $now->format(DATE_RFC2822),
                    $backtrace["file"],
                    $backtrace["line"]
                )
            );
            $transaction->setAcquiredRow(null);
        }
    }

    protected function microSecondsTime()
    {

        $microtime = microtime(true);
        $microtime *= 1000000.0;
        $microtime = intval($microtime);

        return $microtime;
    }

    protected function stopwatchStart($message)
    {
        if ($this->stopwatch) {
            $this->stopwatch->start("Semaphore::".$message);
        }
    }

    protected function stopwatchStop($message)
    {
        if ($this->stopwatch) {
            $this->stopwatch->stop("Semaphore::".$message);
        }
    }

}
