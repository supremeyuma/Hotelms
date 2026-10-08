<?php

namespace App\Sync;

class Hlc
{
    public string $timestamp;
    public int $counter;
    public string $nodeId;

    public function __construct(string $timestamp, int $counter, string $nodeId)
    {
        $this->timestamp = $timestamp;
        $this->counter = $counter;
        $this->nodeId = $nodeId;
    }

    public static function fromString(string $hlc): self
    {
        $parts = explode("-", $hlc);

        if (count($parts) === 2) {
            // Legacy offset-package stamp: "timestamp-nodeId", no counter.
            return new self($parts[0], 0, $parts[1]);
        }

        // "timestamp-counter-nodeId" where the node id itself may contain
        // dashes ("node-a", "front-desk-02"). The counter is numeric, so the
        // node id is everything after the second segment.
        if (count($parts) >= 3 && is_numeric($parts[1])) {
            return new self($parts[0], (int) $parts[1], implode("-", array_slice($parts, 2)));
        }

        throw new \InvalidArgumentException("Invalid HLC format");
    }

    public function toString(): string
    {
        return implode("-", [$this->timestamp, $this->counter, $this->nodeId]);
    }

    public static function now(string $nodeId): self
    {
        $timestamp = (string) floor(microtime(true) * 1000);
        return new self($timestamp, 0, $nodeId);
    }

    public static function max(self $a, self $b): self
    {
        if ($a->timestamp > $b->timestamp) {
            return $a;
        }
        if ($b->timestamp > $a->timestamp) {
            return $b;
        }
        if ($a->counter > $b->counter) {
            return $a;
        }
        if ($b->counter > $a->counter) {
            return $b;
        }
        if ($a->nodeId >= $b->nodeId) {
            return $a;
        }
        return $b;
    }

    public function increment(): self
    {
        return new self($this->timestamp, $this->counter + 1, $this->nodeId);
    }

    public function update(self $other): self
    {
        if ($other->timestamp > $this->timestamp) {
            return new self($other->timestamp, $other->counter, $this->nodeId);
        }
        if ($this->timestamp > $other->timestamp) {
            return $this->increment();
        }
        return new self($this->timestamp, max($this->counter, $other->counter) + 1, $this->nodeId);
    }
}
