<?php
namespace Qf\Queue\Jobs;

abstract class Job
{
    protected $deleted;
    protected $failed;
    protected $released;

    /**
     * @var array
     */
    protected $job;

    public function getBody()
    {
        return $this->job['body'] ?? null;
    }

    public function getCallback()
    {
        return $this->job['callback'] ?? null;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function getCreatedAt()
    {
        return $this->job['createdAt'] ?? null;
    }

    public function getName()
    {
        return $this->job['name'] ?? null;
    }

    public function getId()
    {
        return $this->job['id'] ?? null;
    }


    public function getTimeoutAt()
    {
        return $this->job['timeoutAt'] ?? null;
    }

    public function getAttempts()
    {
        return $this->job['attempts'] ?? null;
    }

    public function isDeleted()
    {
        return $this->deleted;
    }

    public function delete()
    {
        $this->deleted = true;
    }

    public function isFailed()
    {
       return $this->failed;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function release($delay = 0)
    {
        $this->released = true;
    }

    public function encodeJob()
    {
        return json_encode($this->job);
    }

    public static function decodeJob($string)
    {
        return json_decode($string, true);
    }
}