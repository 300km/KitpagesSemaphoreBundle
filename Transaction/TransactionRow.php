<?php
namespace Kitpages\SemaphoreBundle\Transaction;

/**
 *
 */
class TransactionRow
{
	/**
	 * @var string
	 */
	protected $uid;
	/**
	 * @var integer
	 */
	protected $microtime;

	/**
	 * create a line of data
	 */
	public function __construct($uid, $microtime)
	{
		$this->uid = $uid;
		$this->microtime = $microtime;
	}

	public function getUid()
	{
		return $this->uid;
	}

	public function getMicrotime()
	{
		return $this->microtime;
	}
}
