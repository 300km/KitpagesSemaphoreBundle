<?php
namespace Kitpages\SemaphoreBundle\Transaction;

use Kitpages\SemaphoreBundle\Transaction\Transaction;

/**
 * This service provides a lock system, ie a way to protect data from simultaneous access
 */
interface TransactionManagerInterface
{
	/**
	 * Get the transaction associated to a semaphoreName
	 *
	 * @param $semaphoreName string
	 * @return Transaction
	 */
	public function beginTransaction($semaphoreName);

    /**
     * Save the transaction updates and close the transaction
     *
     * @param Transaction $transaction
     */
    public function commit(Transaction $transaction);

    /**
     * Close the transaction without saving the modifications
     *
     * @param Transaction $transaction
     */
    public function rollback(Transaction $transaction);
}
