<?php
namespace Kitpages\SemaphoreBundle\Manager;

interface ManagerInterface
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