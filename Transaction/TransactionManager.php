<?php
namespace Kitpages\SemaphoreBundle\Transaction;

use Kitpages\SemaphoreBundle\Transaction\TransactionManagerInterface;
use Kitpages\SemaphoreBundle\Transaction\Transaction;

use Psr\Log\LoggerInterface;

class TransactionManager
    implements TransactionManagerInterface
{
    /** @var  string */
    protected $fileDirectory;

    /** @var LoggerInterface */
    protected $logger;

    /** @var array of handlers */
    protected $fileHandlerList = array();

    public function __construct(
        $fileDirectory,
        LoggerInterface $logger
    )
    {
        $this->fileDirectory = $fileDirectory;
        if (!is_dir($this->fileDirectory)) {
            mkdir($this->fileDirectory, 0777, true);
        }
        $this->logger = $logger;
    }

    /**
     * Get the transaction associated to a semaphoreName
     *
     * @param $semaphoreName string
     * @return Transaction
     */
    public function beginTransaction($semaphoreName)
    {
        $pid = getmypid();
        $this->logger->debug("[$pid] beginTransaction");
        $fp = $this->getFileHandler($semaphoreName);
        $content = fread($fp, 10000);
        if (!empty($content)) {
            $transaction = unserialize($content);
            if (! $transaction instanceof Transaction) {
                throw new \Exception("not a Transaction");
            }
        } else {
            $transaction = new Transaction($semaphoreName);
        }
        $this->logger->debug("[$pid] beginTransaction returned=".$transaction->toString());
        return $transaction;
    }



    /**
     * Save the transaction updates and close the transaction
     *
     * @param Transaction $transaction
     */
    public function commit(Transaction $transaction)
    {
        $fp = $this->getFileHandler($transaction->getSemaphoreName());
        ftruncate($fp, 0);    //Truncate the file to 0
        rewind($fp);
        fwrite($fp, serialize($transaction));    //Write the new Hit Count
        fflush($fp);
        flock($fp, LOCK_UN);    //Unlock File
        fclose($fp);
        $this->logger->debug($transaction->toString());
        $this->logger->debug(serialize($transaction));
        $this->logger->debug(file_get_contents($this->getFileName($transaction->getSemaphoreName())));
        unset($this->fileHandlerList[$transaction->getSemaphoreName()]);
    }

    /**
     * Close the transaction without saving the modifications
     *
     * @param Transaction $transaction
     */
    public function rollback(Transaction $transaction)
    {
        $fp = $this->getFileHandler($transaction->getSemaphoreName());
        flock($fp, LOCK_UN);    //Unlock File
        fclose($fp);
        unset($this->fileHandlerList[$transaction->getSemaphoreName()]);
    }

    protected function getFileHandler($semaphoreName)
    {
        // open and lock file
        if (!isset($this->fileHandlerList[$semaphoreName])) {
            touch($this->getFileName($semaphoreName));
            // lock file
            $fp = fopen($this->getFileName($semaphoreName), "r+");
            if (!flock($fp, LOCK_EX)) {
                $pid = getmypid();
                throw new \RuntimeException("kitpages_semaphore, [$pid] flock failed");
            }
            $this->fileHandlerList[$semaphoreName] = $fp;
        }
        // return file pointer
        return $this->fileHandlerList[$semaphoreName];
    }


    protected function getFileName($semaphoreName)
    {
        return $this->fileDirectory.'/'.$semaphoreName.".serialized";
    }

    public function deleteFile($semaphoreName)
    {
        @unlink($this->getFileName($semaphoreName));
    }

}
