<?php
namespace Kitpages\SemaphoreBundle\Transaction;

use Kitpages\SemaphoreBundle\Transaction\TransactionRow;

class Transaction
{
    /**
     * @var array of string
     */
    public $fifo;

    /**
     * @var TransactionRow
     */
    public $aquiredRow;

    /**
     * @var string
     */
    protected $semaphoreName;


    public function __construct($semaphoreName)
    {
        $this->semaphoreName = $semaphoreName;
        $this->fifo = array();
        $this->aquiredRow = null;
    }

    public function getSemaphoreName()
    {
        return $this->semaphoreName;
    }

    /**
     * add a row to the FIFO
     *
     * @param $uid string
     */
    public function addToFifoIfNotPresent($uid)
    {
        if (!in_array($uid, $this->fifo)) {
            $this->fifo[] = $uid;
        }
        return $this;
    }

    /**
     * removes the next value from the fifo and returns it.
     *
     * @return string $uid
     */
    public function removeNextFromFifo()
    {
        return array_shift($this->fifo);
    }

    public function fifoIsEmpty()
    {
        if (count($this->fifo) === 0) {
            return true;
        }
        return false;
    }

    /**
     * Read next value from fifo, without removing it from the fifo
     *
     * @return string $uid
     */
    public function readNextFromFifo()
    {
        if ($this->fifoIsEmpty()) {
            return null;
        }
        return $this->fifo[0];
    }

    /**
     * @return TransactionRow
     */
    public function getAquiredRow()
    {
        return $this->aquiredRow;
    }

    /**
     * @param $aquired TransactionRow | null
     */
    public function setAquiredRow(TransactionRow $row = null)
    {
        $this->aquiredRow = $row;
        return $this;
    }

    public function toString()
    {
        $str = "";
        if ($this->getAquiredRow() === null) {
            $str .= "reserved=null; ";
        } else {
            $str .= "reserved=".$this->getAquiredRow()->getUid().'; ';
        }
        $str .= "fifo=".count($this->fifo)."; ";
        return $str;
    }

}
