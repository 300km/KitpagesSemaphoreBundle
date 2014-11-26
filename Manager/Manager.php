<?php
/**
 * Created by Philippe Le Van.
 * Date: 04/12/13
 */
namespace Kitpages\SemaphoreBundle\Manager;

use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class Manager
    implements ManagerInterface
{
    /** @var  string */
    protected $fileDirectory;

    /** @var int */
    protected $sleepTimeMicroseconds;

    /** @var  int */
    protected $deadlockMicroseconds;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var  Stopwatch */
    protected $stopwatch;

    public function __construct(
        $sleepTimeMicroseconds,
        $deadLockMicroseconds,
        LoggerInterface $logger,
        $fileDirectory,
        Stopwatch $stopwatch = null
    )
    {
        $this->sleepTimeMicroseconds = $sleepTimeMicroseconds;
        $this->deadlockMicroseconds = $deadLockMicroseconds;
        $this->logger = $logger;
        $this->fileDirectory = $fileDirectory;
        if (!is_dir($this->fileDirectory)) {
            mkdir($this->fileDirectory, 0777, true);
        }
        $this->stopwatch = $stopwatch;
    }

    protected function microSecondsTime()
    {

        $microtime = microtime(true);
        $microtime *= 1000000.0;
        $microtime = intval($microtime);

        return $microtime;
    }

    protected function getFileName($semaphoreName)
    {
        return $this->fileDirectory.'/'.$semaphoreName.".csv";
    }

    /**
     * @param boolean $locked
     * @param int $microtime
     */
    protected function generateFileContent($locked, $microtime = null)
    {
        if (is_null($microtime)) {
            $microtime = $this->microSecondsTime();
        }
        $lockedString = $locked ? '1' : '0';
        return $lockedString.';'.$microtime;
    }

    protected function readFile($fp)
    {
        $content = fread($fp, 100);
        $tab = explode(";", $content);
        return array(
            "locked" => ($tab[0] == "1"),
            "microtime" => intval($tab[1])
        );
    }
    protected function writeAndClose($fp, $content)
    {
        ftruncate($fp, 0);    //Truncate the file to 0
        rewind($fp);
        fwrite($fp, $content);    //Write the new Hit Count
        fflush($fp);
        flock($fp, LOCK_UN);    //Unlock File
        fclose($fp);
    }
    public function deleteFile($semaphoreName)
    {
        @unlink($this->getFileName($semaphoreName));
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

    /**
     * @inheritdoc
     */
    public function aquire($semaphoreName)
    {


        // if aquiredRow is empty and first row is empty => setAquiredRow

        // elseif acquiredRow

        $pid = getmypid();
        $locked = true;
        $this->logger->debug("[$pid] acquire requested, semaphoreName=$semaphoreName");
        $this->stopwatchStart('aquire_requested');


        // get file pointer
        if (!is_file($this->getFileName($semaphoreName))) {
            file_put_contents($this->getFileName($semaphoreName), $this->generateFileContent(true));
            $this->logger->debug("[$pid] aquire obtained loopCount=0, semaphoreName=$semaphoreName");
            $this->stopwatchStop('aquire_requested');
            $this->stopwatchStart('aquire_obtained');
            return;
        }
        $loopCount = 0;

        while ($locked == true) {
            $fp = fopen($this->getFileName($semaphoreName), "r+");
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException("kitpages_semaphore, [$pid] flock failed");
            }
            $content = $this->readFile($fp);
            $locked = $content["locked"];
            $microtime = $content["microtime"];

            if (true == $locked && $this->microSecondsTime() >= $microtime + $this->deadlockMicroseconds) {
                $backtraceList = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $backtrace = $backtraceList[0];
                $now = new \DateTime();
                $this->logger->warning("[$pid] Dead lock detected, loopCount=$loopCount , at ".$now->format(DATE_RFC2822)." in ".$backtrace["file"].'('.$backtrace["line"].')');

                $this->writeAndClose($fp, $this->generateFileContent(true));
                $this->stopwatchStop('aquire_requested');
                $this->stopwatchStart('aquire_obtained');
                return;

            } elseif ($locked == false) {
                $this->writeAndClose($fp, $this->generateFileContent(true));
                $this->logger->debug("[$pid] aquire obtained, loopCount=$loopCount, semaphoreName=$semaphoreName");
                $this->stopwatchStop('aquire_requested');
                $this->stopwatchStart('aquire_obtained');
                return;
            }
            flock($fp, LOCK_UN);    //Unlock File
            fclose($fp);
            $loopCount ++;
            usleep($this->sleepTimeMicroseconds);
        }
    }

    /**
     * @inheritdoc
     */
    public function release($semaphoreName)
    {
        $pid = getmypid();
        $fp = fopen($this->getFileName($semaphoreName), "r+");
        if (!flock($fp, LOCK_EX)) {
            throw new \RuntimeException("kitpages_semaphore, [$pid] flock failed");
        }
        $content = $this->readFile($fp);
        $locked = $content["locked"];
        if (!$locked) {
            $backtraceList = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $backtrace = $backtraceList[0];
            $now = new \DateTime();
            $this->logger->warning("[$pid] Realease requested, but semaphore not locked, at ".$now->format(DATE_RFC2822)." in ".$backtrace["file"].'('.$backtrace["line"].')');
        }
        $this->writeAndClose($fp, $this->generateFileContent(false));
        $this->logger->debug("[$pid] release ok, semaphoreName=$semaphoreName");
        $this->stopwatchStop('aquire_obtained');
        return;
    }
}
