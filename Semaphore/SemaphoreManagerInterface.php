<?php
namespace Kitpages\SemaphoreBundle\Semaphore;

interface SemaphoreManagerInterface
{
    /**
     * @param string $semaphoreName
     */
    public function aquire($semaphoreName);

    /**
     * @param string $semaphoreName
     */
    public function release($semaphoreName);
}
